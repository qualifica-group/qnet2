<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

use App\Models\Attribute;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One grid column for a current field and the legacy fields linked to it
 * (spec 0429). A merged column is the current field's row carrying
 * `linked_codes`: its value is the first non-empty among its codes (D-4), and
 * filter and sort follow the same rule. A record carries the fields of its own
 * category, so the codes never compete on one row.
 *
 * Every code reaching a query comes from the server-resolved set (allow-list,
 * backend.md §8): `linked_codes` is built here from the declared links.
 */
final class LinkedAttributeColumns
{
    public const string LINKED_CODES = 'linked_codes';

    /** @var array<string, string>|null legacy code => current code */
    private ?array $currentCodes = null;

    /**
     * The shown set with each legacy field folded into its current one when
     * both are in it (D-3). A legacy field shown alone keeps its own column.
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return Collection<int, array<string, mixed>>
     */
    public function fold(Collection $attributes): Collection
    {
        $linked = $this->linkedWithin($attributes);
        $folded = array_merge(...array_values($linked ?: [[]]));

        return $this->withLinkedCodes($attributes, $linked)
            ->reject(static fn (array $row): bool => in_array($row['code'], $folded, true))
            ->values();
    }

    /**
     * Same links as fold(), every legacy row kept: the union a filter, sort or
     * distinct request looks a column up in, whichever set showed it.
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return Collection<int, array<string, mixed>>
     */
    public function enrich(Collection $attributes): Collection
    {
        return $this->withLinkedCodes($attributes, $this->linkedWithin($attributes));
    }

    /**
     * A saved record's own set as a merged grid shows it (D-5): a legacy field
     * also answers under its current field's column, which the record lacks.
     *
     * @param  Collection<int, array<string, mixed>>  $own
     * @return Collection<int, array<string, mixed>>
     */
    public function withCurrentAliases(Collection $own): Collection
    {
        $ownCodes = $own->pluck('code')->all();
        $aliases = [];

        foreach ($own as $row) {
            $current = $this->currentCodeOf($row['code']);

            if ($current !== null && ! in_array($current, $ownCodes, true)) {
                $aliases[$current] ??= [...$row, 'code' => $current, self::LINKED_CODES => []];
                $aliases[$current][self::LINKED_CODES][] = $row['code'];
            }
        }

        return $own->concat(array_values($aliases))->values();
    }

    /**
     * The code a write to the column of $code lands on for a record whose own
     * fields are $own (D-5): $code itself, else the record's field linked to it.
     * Unchanged when none applies, so the record's validation answers 422.
     *
     * @param  Collection<int, array<string, mixed>>  $own
     */
    public function writableCode(string $code, Collection $own): string
    {
        if ($own->contains('code', $code)) {
            return $code;
        }

        $legacy = $own->first(fn (array $row): bool => $this->currentCodeOf($row['code']) === $code);

        return $legacy['code'] ?? $code;
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, string> the column's codes, the current one first
     */
    public function codes(array $attributeRow): array
    {
        return [$attributeRow['code'], ...($attributeRow[self::LINKED_CODES] ?? [])];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $attributeRow
     */
    public function firstFilled(array $values, array $attributeRow): mixed
    {
        foreach ($this->codes($attributeRow) as $code) {
            if (($values[$code] ?? null) !== null) {
                return $values[$code];
            }
        }

        return null;
    }

    /**
     * The filter of a merged column (D-4): on the first code when it holds a
     * value, otherwise on the rest. Same rows as filtering a COALESCE, through
     * the per-type filter of each code ($single).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @param  array<string, mixed>  $filter
     * @param  Closure(Builder<Model>, array<string, mixed>, array<string, mixed>): void  $single
     */
    public function applyFilter(Builder $query, string $valuesColumn, array $attributeRow, array $filter, Closure $single): void
    {
        [$first, $rest] = $this->split($attributeRow);
        $path = $valuesColumn.'->'.$first['code'];

        $query->where(static fn (Builder $either) => $either
            ->where(static fn (Builder $filled) => $filled->whereNotNull($path)->where(static fn (Builder $nested) => $single($nested, $first, $filter)))
            ->orWhere(static fn (Builder $empty) => $empty->whereNull($path)->where(static fn (Builder $nested) => $single($nested, $rest, $filter))));
    }

    /**
     * ORDER BY the first non-empty code (D-4). A JSON null reads as the string
     * 'null' through MySQL's unquote, hence the NULLIF.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     */
    public function applySort(Builder $query, string $valuesColumn, array $attributeRow, string $direction): void
    {
        $grammar = $query->getQuery()->getGrammar();
        $expressions = array_map(
            static fn (string $code): string => "NULLIF({$grammar->wrap($valuesColumn.'->'.$code)}, 'null')",
            $this->codes($attributeRow),
        );

        $query->orderByRaw('COALESCE('.implode(', ', $expressions).') '.($direction === 'desc' ? 'desc' : 'asc'));
    }

    /**
     * The row of one code of a merged column, without links.
     *
     * @param  array<string, mixed>  $attributeRow
     * @return array<string, mixed>
     */
    public function single(array $attributeRow, string $code): array
    {
        return [...$attributeRow, 'code' => $code, self::LINKED_CODES => []];
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    public function isMerged(array $attributeRow): bool
    {
        return ($attributeRow[self::LINKED_CODES] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} the first code alone, the rest still merged
     */
    private function split(array $attributeRow): array
    {
        $codes = $this->codes($attributeRow);

        return [
            $this->single($attributeRow, $codes[0]),
            [...$attributeRow, 'code' => $codes[1], self::LINKED_CODES => array_slice($codes, 2)],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<string, array<int, string>> current code => legacy codes linked to it, both in $attributes
     */
    private function linkedWithin(Collection $attributes): array
    {
        $codes = $attributes->pluck('code')->all();
        $linked = [];

        foreach ($codes as $code) {
            $current = $this->currentCodeOf($code);

            if ($current !== null && in_array($current, $codes, true)) {
                $linked[$current][] = $code;
            }
        }

        return $linked;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @param  array<string, array<int, string>>  $linked
     * @return Collection<int, array<string, mixed>>
     */
    private function withLinkedCodes(Collection $attributes, array $linked): Collection
    {
        return $attributes->map(static fn (array $row): array => isset($linked[$row['code']])
            ? [...$row, self::LINKED_CODES => $linked[$row['code']]]
            : $row);
    }

    private function currentCodeOf(string $code): ?string
    {
        $this->currentCodes ??= Attribute::query()
            ->join('attributes as current_attributes', 'current_attributes.id', '=', 'attributes.current_attribute_id')
            ->toBase()
            ->select(['attributes.code as legacy_code', 'current_attributes.code as current_code'])
            ->pluck('current_code', 'legacy_code')
            ->all();

        return $this->currentCodes[$code] ?? null;
    }
}
