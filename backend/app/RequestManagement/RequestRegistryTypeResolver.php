<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Enums\PersonalDataTypeEnum;
use App\Models\ProductCategory;

/**
 * The kind of anagrafica the Gestione Richieste create form starts the
 * new-client card on (spec 0410): the `request_registry_type` of the category
 * of the FIRST product line (D-2), already mirrored from its branch root.
 */
final class RequestRegistryTypeResolver
{
    /**
     * @param  array<int, int>  $categoryIds  the product-line categories, in form order
     */
    public function forCategories(array $categoryIds): ?PersonalDataTypeEnum
    {
        if ($categoryIds === []) {
            return null;
        }

        return ProductCategory::query()->find($categoryIds[0], ['id', 'request_registry_type'])?->request_registry_type;
    }
}
