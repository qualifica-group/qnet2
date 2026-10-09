<?php

declare(strict_types=1);

namespace App\Tables\Registries;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Tables\Shared\WordPrefixMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Extends the Anagrafiche quick-search (spec 0211) beyond `name`: a registry
 * also matches by its card (VAT number, tax code, names), by one of its phone
 * numbers, or by the name or phone number of a linked referent (D-1).
 *
 * Two passes like spec 0179 D-3: (1) each lookup reads at most the capped
 * matching ids through an index (FULLTEXT via WordPrefixMatcher, the
 * `(type, normalized_value)` index for phones); (2) one `registries.id IN`
 * branch of the engine's OR group. `name` itself is NOT covered: it keeps the
 * engine's "contains" LIKE, unchanged.
 *
 * SECURITY: words contain only letters/digits and the phone prefix only digits
 * and a leading `+`, so no LIKE wildcard or MATCH operator from the user
 * reaches the query; every value is bound.
 */
final class RegistrySearch
{
    /** The searchable column whose quick-search this branch extends. */
    private const string NAME_COLUMN = 'name';

    /** Exactly the column list of the `personal_data_search_fulltext` index (spec 0179). */
    private const array CARD_COLUMNS = ['first_name', 'last_name', 'tax_code', 'vat_number'];

    /** Separators a typed phone number may carry (D-3), stripped before the digit check. */
    private const string PHONE_SEPARATORS = '/[\s().-]+/';

    /** A phone prefix: an optional leading `+`, then at least 3 digits (D-3). */
    private const string PHONE_PREFIX = '/^\+?\d{3,}$/';

    public function __construct(private readonly WordPrefixMatcher $matcher) {}

    /**
     * Adds the extra OR-branch when `name` is searched. Covers no column, so
     * the engine still runs its LIKE on `name`.
     *
     * @param  Builder<Registry>  $query
     * @param  array<int, string>  $columnIds
     * @return array<int, string>
     */
    public function apply(Builder $query, array $columnIds, string $term): array
    {
        if (! in_array(self::NAME_COLUMN, $columnIds, true)) {
            return [];
        }

        // Step 1: the term as words (card, referent names) and as a phone prefix
        $words = $this->matcher->words($term);
        $phonePrefix = $this->phonePrefix($term);

        // Step 2: the registries matched directly or through a linked referent
        $registryIds = array_values(array_unique([
            ...$this->registryIdsMatching($words, $phonePrefix),
            ...$this->registryIdsOfReferents($this->referentIdsMatching($words, $phonePrefix)),
        ]));

        // Step 3: one branch of the engine's OR group
        if ($registryIds !== []) {
            $query->orWhereIn($query->qualifyColumn('id'), $registryIds);
        }

        return [];
    }

    /**
     * The digits (with an optional leading `+`) of a term that is a phone
     * number, null for any other term (D-3).
     */
    private function phonePrefix(string $term): ?string
    {
        $compact = (string) preg_replace(self::PHONE_SEPARATORS, '', $term);

        return preg_match(self::PHONE_PREFIX, $compact) === 1 ? $compact : null;
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, int>
     */
    private function registryIdsMatching(array $words, ?string $phonePrefix): array
    {
        $registryMorph = (new Registry)->getMorphClass();

        return [
            ...($words === [] ? [] : $this->cardOwnerIds($words, $registryMorph)),
            ...($phonePrefix === null ? [] : $this->phoneOwnerIds($phonePrefix, $registryMorph)),
        ];
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, int>
     */
    private function referentIdsMatching(array $words, ?string $phonePrefix): array
    {
        $nameIds = [];
        if ($words !== []) {
            $nameQuery = Referent::query();
            $this->matcher->whereWordsStart($nameQuery, [self::NAME_COLUMN], $words);
            $nameIds = $this->ids($nameQuery->limit($this->cap())->pluck('id')->all());
        }

        return [
            ...$nameIds,
            ...($phonePrefix === null ? [] : $this->phoneOwnerIds($phonePrefix, (new Referent)->getMorphClass())),
        ];
    }

    /**
     * Ids of the `$personableType` owners whose card matches every word.
     *
     * @param  array<int, string>  $words
     * @return array<int, int>
     */
    private function cardOwnerIds(array $words, string $personableType): array
    {
        $query = PersonalData::query()->where('personable_type', $personableType);
        $this->matcher->whereWordsStart($query, self::CARD_COLUMNS, $words);

        return $this->ids($query->limit($this->cap())->pluck('personable_id')->all());
    }

    /**
     * Ids of the `$personableType` owners with a phone (primary or not)
     * starting with `$prefix`, through the `(type, normalized_value)` index.
     *
     * @return array<int, int>
     */
    private function phoneOwnerIds(string $prefix, string $personableType): array
    {
        $ids = Contact::query()
            ->join('personal_data', 'personal_data.id', '=', 'contacts.contactable_id')
            ->where('contacts.contactable_type', (new PersonalData)->getMorphClass())
            ->where('personal_data.personable_type', $personableType)
            ->where('contacts.type', ContactTypeEnum::Phone->value)
            ->where('contacts.normalized_value', 'like', $prefix.'%')
            ->limit($this->cap())
            ->pluck('personal_data.personable_id')
            ->all();

        return $this->ids($ids);
    }

    /**
     * @param  array<int, int>  $referentIds
     * @return array<int, int>
     */
    private function registryIdsOfReferents(array $referentIds): array
    {
        if ($referentIds === []) {
            return [];
        }

        return $this->ids(DB::table('referent_registry')
            ->whereIn('referent_id', array_values(array_unique($referentIds)))
            ->limit($this->cap())
            ->pluck('registry_id')
            ->all());
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function ids(array $ids): array
    {
        return array_map(static fn (mixed $id): int => (int) $id, $ids);
    }

    private function cap(): int
    {
        return (int) config('table-search.registry_match_cap');
    }
}
