<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Registry;
use App\Tables\Shared\WordPrefixMatcher;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quick-search over the client columns of Gestione Richieste / Gestione
 * Iscritti (spec 0179): every word of the term must START a word of the
 * client's card (the four card columns together) or of one of its primary
 * phone/email contacts.
 *
 * Two passes, because a single correlated query over `quotes` -> opportunity
 * -> registry -> card ran for minutes on 1M rows (D-3): (1) the matching
 * registry ids, matched by WordPrefixMatcher and capped; (2) one
 * `opportunity_id IN` branch of the engine's OR group.
 */
final class RequestClientSearch
{
    /** The word-length threshold exposed as `searchMinLength` (spec 0179 D-5). */
    public const int MIN_WORD_LENGTH = WordPrefixMatcher::MIN_WORD_LENGTH;

    public function __construct(private readonly WordPrefixMatcher $matcher) {}

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
        $words = $this->matcher->words($term);
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
     * @param  array<int, string>  $words
     * @return array<int, int>
     */
    private function cardRegistryIds(array $words): array
    {
        $query = PersonalData::query()->where('personable_type', (new Registry)->getMorphClass());
        $this->matcher->whereWordsStart($query, array_values(RequestClientColumns::CARD_COLUMNS), $words);

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
        $this->matcher->whereWordsStart($query, ['contacts.value'], $words);

        return $query->limit($this->cap())->pluck('personal_data.personable_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    private function cap(): int
    {
        return (int) config('table-search.request_client_match_cap');
    }
}
