<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\Attribute;
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
 * phone/email contacts. A card match also finds the requests whose flexible
 * field links that registry (a `relation` attribute to registries, e.g.
 * "Anagrafica Utente" of Orientamento specialistico, user directive
 * 2026-11-26): there the person is not the client.
 *
 * Two passes, because a single correlated query over `quotes` -> opportunity
 * -> registry -> card ran for minutes on 1M rows (D-3): (1) the matching
 * registry ids, matched by WordPrefixMatcher and capped; (2) one
 * `opportunity_id IN` branch of the engine's OR group, plus one `id IN`
 * branch for the requests linking a card-matched registry in a field.
 */
final class RequestClientSearch
{
    /** The word-length threshold exposed as `searchMinLength` (spec 0179 D-5). */
    public const int MIN_WORD_LENGTH = WordPrefixMatcher::MIN_WORD_LENGTH;

    private const string RELATION_TYPE = 'relation';

    private const string REGISTRY_ENTITY_TYPE = 'registries';

    /** Defence in depth on the attribute codes reaching a JSON path. */
    private const string CODE_PATTERN = '/^[a-z0-9_]{1,64}$/';

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
        $searchesCard = array_intersect($columnIds, array_keys(RequestClientColumns::SEARCHABLE_CARD_COLUMNS)) !== [];
        $contactTypes = $this->contactTypes($columnIds);
        if (! $searchesCard && $contactTypes === []) {
            return [];
        }

        // Step 2: the registries whose card or primary contact matches every word
        $words = $this->matcher->words($term);
        $cardRegistryIds = $words === [] || ! $searchesCard ? [] : $this->cardRegistryIds($words);
        $registryIds = array_values(array_unique([
            ...$cardRegistryIds,
            ...($words === [] || $contactTypes === [] ? [] : $this->contactRegistryIds($words, $contactTypes)),
        ]));

        // Step 3: one branch of the engine's OR group (an empty list matches nothing)
        $query->orWhereIn(
            $query->qualifyColumn('opportunity_id'),
            Opportunity::query()->select('id')->whereIn('registry_id', $registryIds),
        );

        // Step 4: the requests linking a card-matched registry in a flexible field
        $linkedQuoteIds = $this->registryFieldQuoteIds($cardRegistryIds);
        if ($linkedQuoteIds !== []) {
            $query->orWhereIn($query->qualifyColumn('id'), $linkedQuoteIds);
        }

        return array_values(array_intersect($columnIds, [
            ...array_keys(RequestClientColumns::SEARCHABLE_CARD_COLUMNS),
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
        $this->matcher->whereWordsStart($query, array_values(RequestClientColumns::SEARCHABLE_CARD_COLUMNS), $words);

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

    /**
     * The requests whose `attribute_values` hold one of `$registryIds` under a
     * registry-relation attribute, capped like the first pass.
     *
     * @param  array<int, int>  $registryIds
     * @return array<int, int>
     */
    private function registryFieldQuoteIds(array $registryIds): array
    {
        $codes = $registryIds === [] ? [] : $this->registryFieldCodes();
        if ($codes === []) {
            return [];
        }

        return Quote::query()
            ->where(function (Builder $fields) use ($codes, $registryIds): void {
                foreach ($codes as $code) {
                    $fields->orWhereIn("attribute_values->{$code}", $registryIds);
                }
            })
            ->limit($this->cap())
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function registryFieldCodes(): array
    {
        return Attribute::query()
            ->where('type', self::RELATION_TYPE)
            ->where('relation_target->entity_type', self::REGISTRY_ENTITY_TYPE)
            ->pluck('code')
            ->filter(static fn (mixed $code): bool => is_string($code) && preg_match(self::CODE_PATTERN, $code) === 1)
            ->values()
            ->all();
    }

    private function cap(): int
    {
        return (int) config('table-search.request_client_match_cap');
    }
}
