<?php

namespace App\Tables\Shared;

use App\Enums\PersonalDataTypeEnum;
use App\Models\PersonalData;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The DERIVED person-vs-company badge column (personalData.type), shared by
 * every domain whose model owns a personal-data card (`HasPersonalData`
 * morph) — Users (`user_type`) and Registries (`registry_type`) today.
 * Mirrors PrimaryContactColumn: the option catalogue, the badge metadata, the
 * set filter (whereHas on `personalData`), the correlated sort scalar and the
 * distinct values live here once; the owner table and its morph alias are the
 * only per-domain parameters.
 */
final class PersonalDataTypeColumn
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of values honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Plain string[] of the PersonalDataTypeEnum values: the set-filter
     * options and the badge value tokens.
     *
     * @return array<int, string>
     */
    public function values(): array
    {
        return array_map(
            static fn (PersonalDataTypeEnum $case): string => $case->value,
            PersonalDataTypeEnum::cases(),
        );
    }

    /**
     * Badge metadata (value/label/color/icon) for each PersonalDataTypeEnum
     * case, so the frontend renders the badge without any domain knowledge.
     *
     * @return array<int, array<string, mixed>>
     */
    public function badges(): array
    {
        return array_map(
            static fn ($meta): array => $meta->toArray(),
            PersonalDataTypeEnum::options(),
        );
    }

    /**
     * Excel-like distinct values (spec 0004): the enum catalogue, optionally
     * narrowed by a case-insensitive substring search and capped to `$limit`.
     *
     * @return array<int, string|null>
     */
    public function distinctValues(?string $search, int $limit): array
    {
        $values = $this->values();

        if ($search !== null && $search !== '') {
            return array_slice(array_values(array_filter(
                $values,
                static fn (string $value): bool => stripos($value, $search) !== false,
            )), 0, $limit);
        }

        // The blank entry ("(Vuoti)") sits beside the catalogue (itself
        // offered unscoped): an owner may have no personal-data card at all.
        return array_merge([null], array_slice($values, 0, $limit));
    }

    /**
     * Set filter on personalData.type. Only valid enum values are honoured;
     * whereHas implicitly excludes owners without a card.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $values = array_values(array_filter(
            $this->setFilterValues($filter),
            static fn (string $value): bool => PersonalDataTypeEnum::tryFrom($value) !== null,
        ));

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas('personalData', static function (Builder $cardQuery) use ($values): void {
                    $cardQuery->whereIn('type', $values);
                });
            }

            // The blank entry ("(Vuoti)"): no card, or a card with no type.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('personalData', static function (Builder $cardQuery): void {
                    $cardQuery->whereNotNull('type');
                });
            }
        });
    }

    /**
     * Correlated ORDER BY scalar: the owner's card type. `personable_type`
     * uses the morph alias (enforced morphMap), never the FQCN.
     *
     * @return Builder<Model>
     */
    public function sortSubquery(string $ownerTable, string $ownerMorphClass): Builder
    {
        return PersonalData::query()
            ->select('personal_data.type')
            ->whereColumn('personal_data.personable_id', "{$ownerTable}.id")
            ->where('personal_data.personable_type', $ownerMorphClass)
            ->limit(1);
    }

    /**
     * Extract, sanitize and cap the string values of a set filter payload.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setFilterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        $clean = array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        ));

        return array_slice($clean, 0, self::MAX_FILTER_VALUES);
    }
}
