<?php

namespace App\Services\ProductCategories;

use App\Enums\PersonalDataTypeEnum;

/**
 * Single authority on the `request_registry_type` value of the
 * product-category tree (spec 0410): the kind of anagrafica the Gestione
 * Richieste create form starts the new-client card on. Root-owned and
 * subtree-mirrored: see RootOwnedCategorySetting for the semantics, the
 * rationale of the denormalisation and the sync contract.
 */
final class RequestRegistryTypeInheritance extends RootOwnedCategorySetting
{
    /**
     * The type a child of $parentId INHERITS — its branch root's value. Null
     * means "nothing to inherit" (the category is a root and owns the type).
     */
    public function inheritedValueFor(?int $parentId): ?PersonalDataTypeEnum
    {
        $inherited = $this->inheritedRawValueFor($parentId);

        return $inherited instanceof PersonalDataTypeEnum ? $inherited : null;
    }

    protected function column(): string
    {
        return 'request_registry_type';
    }
}
