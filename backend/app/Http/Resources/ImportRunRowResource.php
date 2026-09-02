<?php

namespace App\Http\Resources;

use App\Models\ImportRunRow;
use App\Models\OperationalSite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportRunRow
 *
 * The frozen `row` shape (spec 0033 data_contract — POST .../rows SSRM review
 * and PATCH .../rows/{row}): `values` merges the mapped field values with the
 * `__extra__` ones (both already keyed the way the review grid/edit form
 * expect — field id for mapped, original column name for extra), so the
 * frontend never has to know which store a given key came from. Never the
 * raw `import_run_id`/timestamps — those are server-only bookkeeping.
 * `duplicate_meta`/`resolution` (spec 0036, additive) are null on any row
 * that never matched an existing record. `operator_id`/`operator` and
 * `operational_site_id`/`operational_site` (spec 0045, additive) carry the
 * per-row Operator/Operational Site overrides — null on a row that still
 * defers to the run's global value.
 *
 * `product_ids`/`products` (spec 0094, D-4/AC-054): the per-row "Prodotti di
 * interesse" override, same null-defers-to-global semantics — `products` is
 * null when `product_ids` is null (nothing of THIS row's own to label), an
 * empty array when `product_ids` is `[]`. Labels are resolved in ONE batched
 * query for the whole page via collection() below (never per-row N+1) — a
 * single `new ImportRunRowResource($row)` (PATCH .../rows/{row}) falls back
 * to its own single-row query, an acceptable one-off.
 */
class ImportRunRowResource extends JsonResource
{
    /** Product id => name, primed once per collection() call (never leaks across instances — instance property, not static). */
    private ?array $productNamesByPage = null;

    /**
     * @param  mixed  $resource
     */
    public static function collection($resource)
    {
        $productNames = self::batchProductNames($resource);

        return tap(parent::collection($resource), function ($collection) use ($productNames): void {
            /** @var self $item */
            foreach ($collection->collection as $item) {
                $item->productNamesByPage = $productNames;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImportRunRow $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'row_number' => $row->row_number,
            'status' => $row->status->value,
            'is_edited' => $row->is_edited,
            'duplicate_of_id' => $row->duplicate_of_id,
            'duplicate_meta' => $row->duplicate_meta,
            'resolution' => $row->resolution?->value,
            'operator_id' => $row->operator_id,
            'operator' => $row->operator === null ? null : ['id' => $row->operator->id, 'name' => $row->operator->name],
            'operational_site_id' => $row->operational_site_id,
            'operational_site' => $row->operationalSite === null ? null : ['id' => $row->operationalSite->id, 'name' => $this->siteLabel($row->operationalSite)],
            'product_ids' => $row->product_ids,
            'products' => $row->product_ids === null ? null : $this->resolveProducts($row->product_ids),
            'values' => [...($row->mapped_values ?? []), ...($row->extra_values ?? [])],
            'messages' => $row->messages ?? [],
        ];
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, array{id: int, label: string}>
     */
    private function resolveProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $names = $this->productNamesByPage ?? Product::query()->whereIn('id', $productIds)->pluck('name', 'id')->all();

        return collect($productIds)
            ->map(static fn (int $id): ?array => isset($names[$id]) ? ['id' => $id, 'label' => $names[$id]] : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * One query for every product id across the whole page's rows, keyed by
     * id — collection()'s N+1 guard (constraints.md: "nessuna query N+1").
     *
     * @return array<int, string>
     */
    private static function batchProductNames(mixed $resource): array
    {
        $ids = collect($resource)
            ->flatMap(static fn (ImportRunRow $row): array => $row->product_ids ?? [])
            ->unique()
            ->values();

        return $ids->isEmpty() ? [] : Product::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /**
     * OperationalSite has no own `name` column (identity = its address, see
     * App\Models\OperationalSite) — mirrors OperationalSiteForSelectResource's
     * label composition: primary address line1, plus " - {city}" when the
     * address has a city, falling back to `alias` when the site has no
     * address at all.
     */
    private function siteLabel(OperationalSite $site): string
    {
        $address = $site->addresses->firstWhere('is_primary', true) ?? $site->addresses->first();

        if ($address === null) {
            return (string) $site->alias;
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }
}
