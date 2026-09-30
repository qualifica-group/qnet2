<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quick-search over the client columns of Gestione Richieste / Gestione
 * Iscritti (spec 0179): every word of the term must START a word of the
 * client's card (the four card columns together) or of one of its primary
 * phone/email contacts.
 *
 * Two passes, because a single correlated query over `quotes` -> opportunity
 * -> registry -> card ran for minutes on 1M rows (D-3): (1) the matching
 * registry ids, read through the FULLTEXT indexes on MySQL/MariaDB (a
 * word-start LIKE on SQLite, D-4) and capped; (2) one `opportunity_id IN`
 * branch of the engine's OR group.
 *
 * SECURITY: words contain only letters and digits, so no boolean-mode
 * operator typed by the user reaches MATCH; the value is always bound.
 */
final class RequestClientSearch
{
    /** Mirrors InnoDB's default `innodb_ft_min_token_size`: shorter words are not indexed (D-2). */
    public const int MIN_WORD_LENGTH = 3;

    /**
     * InnoDB's default stopwords of 3+ characters: with `+` a stopword empties
     * the whole match, so they are dropped from the term (D-2).
     *
     * @var array<int, string>
     */
    private const array STOPWORDS = [
        'about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this',
        'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www',
    ];

    /** Characters after which a word starts, for the SQLite emulation (D-4). */
    private const array WORD_DELIMITERS = [' ', '.', '@', '-', "'"];

    private const array FULLTEXT_DRIVERS = ['mysql', 'mariadb'];

    /**
     * Adds the single OR-branch for the client columns among `$columnIds` and
     * returns the ids it covered (none when the domain searches none of them).
     *
     * @param  Builder<Quote>  $query
     * @param  array<int, string>  $columnIds
     * @return array<int, string>
     */
    public function apply(Builder $query, array $columnIds, string $term): array
    {
        // Step 1: which client columns the engine asked for
        $searchesCard = array_intersect($columnIds, array_keys(RequestClientColumns::CARD_COLUMNS)) !== [];
        $contactTypes = $this->contactTypes($columnIds);
        if (! $searchesCard && $contactTypes === []) {
            return [];
        }

        // Step 2: the registries whose card or primary contact matches every word
        $words = $this->words($term);
        $registryIds = $words === [] ? [] : array_values(array_unique([
            ...($searchesCard ? $this->cardRegistryIds($words) : []),
            ...($contactTypes === [] ? [] : $this->contactRegistryIds($words, $contactTypes)),
        ]));

        // Step 3: one branch of the engine's OR group (an empty list matches nothing)
        $query->orWhereIn(
            $query->qualifyColumn('opportunity_id'),
            Opportunity::query()->select('id')->whereIn('registry_id', $registryIds),
        );

        return array_values(array_intersect($columnIds, [
            ...array_keys(RequestClientColumns::CARD_COLUMNS),
            ...array_keys(RequestClientColumns::CONTACT_COLUMNS),
        ]));
    }

    /**
     * @param  array<int, string>  $columnIds
     * @return array<int, string>
     */
    private function contactTypes(array $columnIds): array
    {
        $types = [];

        foreach (RequestClientColumns::CONTACT_COLUMNS as $columnId => $columnTypes) {
            if (in_array($columnId, $columnIds, true)) {
                $types = [...$types, ...$columnTypes];
            }
        }

        return $types;
    }

    /**
     * @return array<int, string>
     */
    private function words(string $term): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH
                && ! in_array($word, self::STOPWORDS, true),
        )));
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, int>
     */
    private function cardRegistryIds(array $words): array
    {
        $query = PersonalData::query()->where('personable_type', (new Registry)->getMorphClass());
        $this->whereWordsStart($query, array_values(RequestClientColumns::CARD_COLUMNS), $words);

        return $query->limit($this->cap())->pluck('personable_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param  array<int, string>  $words
     * @param  array<int, string>  $types
     * @return array<int, int>
     */
    private function contactRegistryIds(array $words, array $types): array
    {
        $query = Contact::query()
            ->join('personal_data', 'personal_data.id', '=', 'contacts.contactable_id')
            ->where('contacts.contactable_type', (new PersonalData)->getMorphClass())
            ->where('personal_data.personable_type', (new Registry)->getMorphClass())
            ->where('contacts.is_primary', true)
            ->whereIn('contacts.type', $types);
        $this->whereWordsStart($query, ['contacts.value'], $words);

        return $query->limit($this->cap())->pluck('personal_data.personable_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Every word must start a word of one of `$columns` (together).
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $words
     */
    private function whereWordsStart(Builder $query, array $columns, array $words): void
    {
        if (in_array($query->getConnection()->getDriverName(), self::FULLTEXT_DRIVERS, true)) {
            $booleanTerm = implode(' ', array_map(static fn (string $word): string => '+'.$word.'*', $words));
            $query->whereFullText($columns, $booleanTerm, ['mode' => 'boolean']);

            return;
        }

        foreach ($words as $word) {
            $query->where(static function (Builder $wordQuery) use ($columns, $word): void {
                foreach ($columns as $column) {
                    $wordQuery->orWhere($column, 'like', $word.'%');

                    foreach (self::WORD_DELIMITERS as $delimiter) {
                        $wordQuery->orWhere($column, 'like', '%'.$delimiter.$word.'%');
                    }
                }
            });
        }
    }

    private function cap(): int
    {
        return (int) config('table-search.request_client_match_cap');
    }
}
