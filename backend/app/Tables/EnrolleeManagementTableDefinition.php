<?php

declare(strict_types=1);

namespace App\Tables;

use App\RequestManagement\RequestModule;

/**
 * Table definition for the `enrollee-management` domain (spec 0130): the
 * SAME operative grid as `request-management` — identical query shape,
 * columns, filters, advanced filters, default sort and action catalogue —
 * restricted to `RequestModule::Enrollees`'s own D-2 row-state filter
 * (`RequestManagementScope::scopeToActor()`, inherited `baseQuery()`) and
 * D-4 permission prefix (`enrollee-management.*`).
 *
 * THE only override (goal: "nessuna logica duplicata", constraints: "nessun
 * file copiato"): every other method — including `actions()`,
 * `authorizeViewAny()`/`authorizeUpdate()`/`authorizeDelete()`, `baseQuery()`
 * and `actionsFor()` — is inherited UNCHANGED from
 * RequestManagementTableDefinition and reads its module off `module()`.
 */
class EnrolleeManagementTableDefinition extends RequestManagementTableDefinition
{
    protected function module(): RequestModule
    {
        return RequestModule::Enrollees;
    }
}
