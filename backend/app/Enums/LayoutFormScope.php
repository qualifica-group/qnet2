<?php

namespace App\Enums;

/**
 * Which form modes a configured attribute layout applies to (spec 0062, D3
 * revised): `All` is the SHARED layout — a single configuration driving
 * create/edit/view together — while the three FormMode-mirroring cases are
 * per-mode OVERRIDES of it. Stored in `attribute_layouts.form_mode`;
 * resolution for a concrete FormMode is "that mode's own row, else the
 * shared `all` row, else flat rendering".
 *
 * Deliberately distinct from App\Enums\FormMode, which stays the lifecycle
 * stage a record is actually being rendered in and therefore never `all`:
 * only the layout CONFIGURATION knows about the shared scope.
 */
enum LayoutFormScope: string
{
    case All = 'all';
    case Create = 'create';
    case Edit = 'edit';
    case View = 'view';

    public static function fromFormMode(FormMode $formMode): self
    {
        return self::from($formMode->value);
    }
}
