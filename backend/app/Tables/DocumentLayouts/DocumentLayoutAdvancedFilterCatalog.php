<?php

namespace App\Tables\DocumentLayouts;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `document-layouts` domain (spec 0069,
 * MT-5): `module` (Enum, backed by `App\Enums\DocumentLayoutModule`) and the
 * two D-7 boolean flags `is_active`/`is_default` (Switch) — every entry is a
 * direct-column filter, handled entirely by the generic default
 * (AdvancedFilterApplier), mirroring PaymentMethodAdvancedFilterCatalog's
 * shape. `enumKey` names the FE-facing options lookup
 * (`data.enums.document_layout_module`, spec ADR 0008's app-wide enum
 * catalog): registering that lookup in `config/config.php`'s `form_enums`
 * allowlist is a separate concern from this server-side filter, which only
 * needs `target` + `type` to apply the equality match (AC-072) — out of this
 * microtask's file ownership (config/tables.php is the only config file in
 * scope here).
 */
final class DocumentLayoutAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'module',
                'label' => 'documentLayouts.advancedFilters.module',
                'type' => AdvancedFilterType::Enum,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'module',
                'enumKey' => 'document_layout_module',
            ],
            [
                'name' => 'is_active',
                'label' => 'documentLayouts.advancedFilters.isActive',
                'type' => AdvancedFilterType::Switch,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_active',
            ],
            [
                'name' => 'is_default',
                'label' => 'documentLayouts.advancedFilters.isDefault',
                'type' => AdvancedFilterType::Switch,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_default',
            ],
        ];
    }
}
