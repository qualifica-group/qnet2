<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use Illuminate\Validation\ValidationException;

/**
 * The D-7 invariant (spec 0069, data_contract): a module has 0 or 1
 * predefinito `DocumentLayout`, never 2 — not expressible as a portable
 * partial/filtered unique index on MySQL+SQLite, so it lives here instead,
 * called by DocumentLayoutService from inside a DB::transaction:
 *
 *  (a) the FIRST layout created for a module always becomes default, even
 *      unrequested — resolveIsDefaultForCreate();
 *  (b) setting `is_default = true` unsets the flag on every other layout of
 *      the same module, in the SAME transaction — clearOtherDefaults();
 *  (c) `is_default = true` requires `is_active = true` — checked by both
 *      resolveIsDefaultForCreate() and assertUpdateTransitionValid();
 *  (d) a predefinito layout cannot be deactivated — assertUpdateTransitionValid();
 *  (e) `is_default` cannot go from true to false via a direct PATCH — same;
 *  delete guard (predefinito + not the module's only layout) — assertDeletable().
 *
 * Every check throws a 422 ValidationException with a message from
 * `lang/{it,en}/document_layouts.php`, keyed on the field the client would
 * need to change (`is_active`/`is_default`) — the same shape a FormRequest's
 * own validator produces, so the controller's generic exception handling
 * (BaseApiController::handleControllerException) needs no special case.
 */
final class DocumentLayoutDefaultManager
{
    /**
     * D-7a/c for create, resolved BEFORE the row is inserted: the first
     * layout in $module always becomes default regardless of $requestedDefault;
     * otherwise the actor's request is respected. The resolved default must
     * be active — checked here so create() never persists an invalid state.
     */
    public function resolveIsDefaultForCreate(DocumentLayoutModule $module, bool $requestedDefault, bool $isActive): bool
    {
        $isFirstInModule = ! DocumentLayout::query()->where('module', $module->value)->exists();
        $isDefault = $isFirstInModule || $requestedDefault;

        if ($isDefault && ! $isActive) {
            throw ValidationException::withMessages([
                'is_default' => [__('document_layouts.default_requires_active')],
            ]);
        }

        return $isDefault;
    }

    /**
     * D-7b: unsets `is_default` on every OTHER layout of $layout's module.
     * Called AFTER $layout itself has been persisted/updated with
     * `is_default = true`, inside the same transaction.
     */
    public function clearOtherDefaults(DocumentLayout $layout): void
    {
        DocumentLayout::query()
            ->where('module', $layout->module->value)
            ->whereKeyNot($layout->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * D-7c/d/e for update: validates the {is_active, is_default} transition
     * against $layout's CURRENTLY PERSISTED state, before anything is
     * written. $requestedIsActive/$requestedIsDefault are null when the
     * field was not submitted in this (partial) PATCH.
     */
    public function assertUpdateTransitionValid(DocumentLayout $layout, ?bool $requestedIsActive, ?bool $requestedIsDefault): void
    {
        $wasDefault = $layout->is_default;

        // D-7e: is_default cannot be turned off directly — reassign another
        // layout as default instead. Checked before (d): a client trying to
        // undefault while also deactivating gets THIS message, the more
        // specific rule of the two.
        if ($wasDefault && $requestedIsDefault === false) {
            throw ValidationException::withMessages([
                'is_default' => [__('document_layouts.default_must_be_reassigned')],
            ]);
        }

        // D-7d: a predefinito layout cannot be deactivated (is_default stays
        // true here — the only way it could have become false was rejected
        // above).
        if ($wasDefault && $requestedIsActive === false) {
            throw ValidationException::withMessages([
                'is_active' => [__('document_layouts.default_cannot_be_deactivated')],
            ]);
        }

        // D-7c: a layout newly promoted to default in this same PATCH must
        // resolve active (either already active, or explicitly reactivated
        // in the same payload).
        $effectiveIsActive = $requestedIsActive ?? $layout->is_active;

        if ($requestedIsDefault === true && ! $effectiveIsActive) {
            throw ValidationException::withMessages([
                'is_default' => [__('document_layouts.default_requires_active')],
            ]);
        }
    }

    /**
     * Delete guard (AC-025..027): a predefinito layout blocks deletion UNLESS
     * it is the only layout left in its module (the module is then allowed
     * to drop back to zero layouts). No usage guard here — spec 0070 (D-10).
     */
    public function assertDeletable(DocumentLayout $layout): void
    {
        if (! $layout->is_default) {
            return;
        }

        $hasSiblings = DocumentLayout::query()
            ->where('module', $layout->module->value)
            ->whereKeyNot($layout->getKey())
            ->exists();

        if ($hasSiblings) {
            throw ValidationException::withMessages([
                'is_default' => [__('document_layouts.default_cannot_be_deleted')],
            ]);
        }
    }
}
