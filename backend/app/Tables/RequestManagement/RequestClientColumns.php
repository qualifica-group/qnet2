<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\ContactTypeEnum;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The CLIENT anagraphic columns of the `request-management` domain — Nome,
 * Cognome, Codice fiscale, Telefono — as a single column contract:
 * quick-search (spec 0009), column filter, sort and Excel-like distinct values
 * (spec 0004/0005). Grew out of the former RequestClientSearch (which owned
 * the search branch alone) when the four columns became filterable+sortable
 * like every other column of the grid (user directive 2026-08-03): the
 * relation path and the column allow-list are the same for all four hooks, so
 * they live here ONCE instead of drifting across two collaborators.
 *
 * None of them is a real `opportunities` column: RequestRowMapper reads them
 * from the client Registry's PersonalData card (`phone` = its primary
 * phone/mobile contact), so the generic engine would target a non-existent
 * column. Each hook translates instead into the relation the mapper reads —
 * `whereHas` for search/filter, a correlated subquery for the sort, a scoped
 * `SELECT DISTINCT` over the related table for the value list.
 *
 * The per-type filter SHAPES (text conditions, set, `multi` envelope, combined
 * `{operator, conditions}`) are NOT re-implemented here: the generic
 * FilterApplier is pointed at the real card/contact column INSIDE the
 * `whereHas` closure — the same delegation CustomFieldAwareTableDefinition
 * already uses for its JSON-path columns. Consequence to know: since the match
 * is an EXISTS, a request with no registry/card never matches, negations
 * (`notContains`/`notEqual`) included.
 *
 * SECURITY: column ids come from the definition's server-side allow-list
 * (never the request), every value stays a bound parameter and LIKE wildcards
 * are escaped — no whereRaw/orderByRaw on input (backend.md §8).
 */
final class RequestClientColumns
{
    /**
     * PersonalData card columns, keyed by the table column id they back
     * (identical names, but the map keeps the allow-list explicit).
     *
     * @var array<string, string>
     */
    private const array CARD_COLUMNS = [
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'tax_code' => 'tax_code',
    ];

    private const string PHONE_COLUMN = 'phone';

    /**
     * The card relation path from an Opportunity, as read by RequestRowMapper.
     */
    private const string CARD_RELATION = 'registry.personalData';

    private const string CONTACTS_RELATION = self::CARD_RELATION.'.contacts';

    private const string CARD_TABLE = 'personal_data';

    private const string CONTACTS_TABLE = 'contacts';

    private const string OPPORTUNITY_REGISTRY_FK = 'opportunities.registry_id';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Add the OR-branch for one searchable client column to the engine's
     * search group. Returns false for any column this collaborator does not
     * own, so the generic engine handles it.
     *
     * @param  Builder<Opportunity>  $query
     */
    public function applySearch(Builder $query, string $columnId, string $pattern): bool
    {
        if (isset(self::CARD_COLUMNS[$columnId])) {
            $column = self::CARD_COLUMNS[$columnId];
            $query->orWhereHas(
                self::CARD_RELATION,
                static fn (Builder $cardQuery) => $cardQuery->where($column, 'like', $pattern),
            );

            return true;
        }

        if ($columnId === self::PHONE_COLUMN) {
            $query->orWhereHas(
                self::CONTACTS_RELATION,
                static fn (Builder $contactQuery) => self::scopeToPrimaryPhone($contactQuery)
                    ->where('value', 'like', $pattern),
            );

            return true;
        }

        return false;
    }

    /**
     * Column filter: the whole payload is handed to the generic FilterApplier
     * inside the `whereHas` on the relation that OWNS the value, so text
     * conditions, the Set checklist and the `multi`/combined envelopes all
     * behave exactly as they do on a real column.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (isset(self::CARD_COLUMNS[$columnId])) {
            $column = self::CARD_TABLE.'.'.self::CARD_COLUMNS[$columnId];
            $query->whereHas(self::CARD_RELATION, function (Builder $cardQuery) use ($column, $columnConfig, $filter): void {
                $this->filterApplier->apply($cardQuery, $column, $columnConfig, $filter);
            });

            return true;
        }

        if ($columnId === self::PHONE_COLUMN) {
            $query->whereHas(self::CONTACTS_RELATION, function (Builder $contactQuery) use ($columnConfig, $filter): void {
                self::scopeToPrimaryPhone($contactQuery);
                $this->filterApplier->apply($contactQuery, self::CONTACTS_TABLE.'.value', $columnConfig, $filter);
            });

            return true;
        }

        return false;
    }

    /**
     * ORDER BY the card value via a correlated subquery on the row's own
     * registry — never a JOIN on the main query (which would multiply rows
     * against the to-many contacts).
     *
     * @param  Builder<Opportunity>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if (isset(self::CARD_COLUMNS[$columnId])) {
            $query->orderBy($this->cardSortSubquery(self::CARD_COLUMNS[$columnId]), $direction);

            return true;
        }

        if ($columnId === self::PHONE_COLUMN) {
            $query->orderBy($this->phoneSortSubquery(), $direction);

            return true;
        }

        return false;
    }

    /**
     * Excel-like distinct values, scoped to the registries of the rows
     * matching $query (already narrowed by every OTHER active filter).
     * Returns null for a column this collaborator does not own.
     *
     * @param  Builder<Opportunity>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, Builder $query, ?string $search, int $limit): ?array
    {
        if (isset(self::CARD_COLUMNS[$columnId])) {
            $column = self::CARD_COLUMNS[$columnId];
            $needle = $this->needle($search);

            return $this->cardQueryFor($query)
                ->whereNotNull($column)
                ->when($needle !== null, fn (QueryBuilder $builder) => $builder->where($column, 'like', $needle))
                ->distinct()
                ->orderBy($column)
                ->limit($limit)
                ->pluck($column)
                ->map(static fn (mixed $value): string => (string) $value)
                ->all();
        }

        if ($columnId === self::PHONE_COLUMN) {
            return $this->distinctPhones($query, $search, $limit);
        }

        return null;
    }

    /**
     * @param  Builder<Opportunity>  $query
     * @return array<int, string>
     */
    private function distinctPhones(Builder $query, ?string $search, int $limit): array
    {
        $cardIds = $this->cardQueryFor($query)->select(self::CARD_TABLE.'.id');
        $needle = $this->needle($search);

        return DB::table(self::CONTACTS_TABLE)
            ->where('contactable_type', (new PersonalData)->getMorphClass())
            ->whereIn('contactable_id', $cardIds)
            ->where('is_primary', true)
            ->whereIn('type', self::phoneTypes())
            ->whereNotNull('value')
            ->when($needle !== null, fn (QueryBuilder $builder) => $builder->where('value', 'like', $needle))
            ->distinct()
            ->orderBy('value')
            ->limit($limit)
            ->pluck('value')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * The PersonalData cards of the registries behind the rows matching
     * $query — the shared starting point of both distinct-value lists.
     *
     * @param  Builder<Opportunity>  $query
     */
    private function cardQueryFor(Builder $query): QueryBuilder
    {
        $registryIds = (clone $query)
            ->whereNotNull(self::OPPORTUNITY_REGISTRY_FK)
            ->select(self::OPPORTUNITY_REGISTRY_FK);

        return DB::table(self::CARD_TABLE)
            ->where('personable_type', (new Registry)->getMorphClass())
            ->whereIn('personable_id', $registryIds);
    }

    private function cardSortSubquery(string $column): QueryBuilder
    {
        return DB::table(self::CARD_TABLE)
            ->select($column)
            ->where('personable_type', (new Registry)->getMorphClass())
            ->whereColumn(self::CARD_TABLE.'.personable_id', self::OPPORTUNITY_REGISTRY_FK)
            ->limit(1);
    }

    /**
     * The smallest primary phone/mobile value of the row's client — `min()`
     * so a card carrying both a phone AND a mobile still orders
     * deterministically (mirrors PrimaryContactColumn::sortSubquery).
     */
    private function phoneSortSubquery(): QueryBuilder
    {
        return DB::table(self::CONTACTS_TABLE)
            ->selectRaw('min(contacts.value)')
            ->join(self::CARD_TABLE, self::CARD_TABLE.'.id', '=', self::CONTACTS_TABLE.'.contactable_id')
            ->where(self::CONTACTS_TABLE.'.contactable_type', (new PersonalData)->getMorphClass())
            ->where(self::CONTACTS_TABLE.'.is_primary', true)
            ->whereIn(self::CONTACTS_TABLE.'.type', self::phoneTypes())
            ->where(self::CARD_TABLE.'.personable_type', (new Registry)->getMorphClass())
            ->whereColumn(self::CARD_TABLE.'.personable_id', self::OPPORTUNITY_REGISTRY_FK)
            ->limit(1);
    }

    /**
     * A bound `%needle%` LIKE pattern with the wildcards of user input
     * escaped, or null when the value-list search carries no term.
     */
    private function needle(?string $search): ?string
    {
        if ($search === null || $search === '') {
            return null;
        }

        return '%'.$this->filterApplier->escapeLike($search).'%';
    }

    /**
     * Narrow a contacts query to the PRIMARY telephone contacts (phone or
     * mobile) — the exact set RequestRowMapper::primaryPhone() displays.
     *
     * @param  Builder<Model>  $contactQuery
     * @return Builder<Model>
     */
    private static function scopeToPrimaryPhone(Builder $contactQuery): Builder
    {
        return $contactQuery->where('is_primary', true)->whereIn('type', self::phoneTypes());
    }

    /**
     * @return array<int, string>
     */
    private static function phoneTypes(): array
    {
        return [ContactTypeEnum::Phone->value, ContactTypeEnum::Mobile->value];
    }
}
