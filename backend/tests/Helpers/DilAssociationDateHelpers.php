<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Illuminate\Support\Facades\DB;

/**
 * Rewinds "DIL" to before "Data associazione" joined it (task 427520): the
 * Offerta assignment gone and the item gone from its Offerta layout, the rest
 * of the layout untouched. The Commessa context is left alone: there DIL
 * always inherited the field from Formazione. Shared by the tests that rewind
 * to an older revision, which composed their layouts without the field.
 */
function rewindDilBeforeAssociationDate(ProductCategory $dil): void
{
    $service = app(AttributeLayoutService::class);
    $attributeId = Attribute::query()->where('code', 'stato_assoc_cpi')->value('id');
    DB::table('attribute_category')->where('category_id', $dil->id)->where('context', AttributeContext::Quote->value)->where('attribute_id', $attributeId)->delete();

    $blob = $service->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All);

    if ($blob !== null) {
        $blob['sections'] = array_map(static function (array $section): array {
            $section['rows'] = array_values(array_filter(
                array_map(static function (array $row): array {
                    $row['items'] = array_values(array_filter($row['items'], static fn (array $item): bool => $item['attribute_code'] !== 'stato_assoc_cpi'));

                    return $row;
                }, $section['rows']),
                static fn (array $row): bool => $row['items'] !== [],
            ));

            return $section;
        }, $blob['sections']);
        $service->upsert($dil, AttributeContext::Quote, LayoutFormScope::All, $blob);
    }
}
