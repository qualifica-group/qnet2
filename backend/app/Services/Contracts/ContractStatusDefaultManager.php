<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\ContractStatus;
use Illuminate\Validation\ValidationException;

/**
 * BR-5 (spec 0072): the exclusive-default invariant on `contract_statuses`,
 * not expressible as a portable partial/filtered unique index on
 * MySQL+SQLite — same shape and precedent as
 * App\Services\DocumentLayouts\DocumentLayoutDefaultManager (spec 0069), but
 * GLOBAL rather than scoped to a module: `contract_statuses` has no
 * dimension equivalent to `document_layouts.module`, so every check here
 * considers the whole table.
 *
 *  (a) the FIRST row ever created is default, even unrequested —
 *      resolveIsDefaultForCreate(). In practice this branch is unreachable
 *      post-migration (the create migration always seeds 7 rows, "Da
 *      validare" already `is_default = true`), kept for shape parity with
 *      the precedent and defense in depth on a hand-rolled test DB;
 *  (b) setting `is_default = true` unsets the flag on every other row, in
 *      the SAME transaction — clearOtherDefaults();
 *  (c) `is_default = true` requires `is_active = true` — checked by both
 *      resolveIsDefaultForCreate() and assertUpdateTransitionValid();
 *  (d) a default row cannot be deactivated — assertUpdateTransitionValid();
 *  (e) `is_default` cannot go from true to false via a direct PATCH — same
 *      (reassign another row as default instead).
 *
 * Every check throws a 422 ValidationException with a message from
 * `lang/{it,en}/contract_statuses.php`, keyed on the field the client would
 * need to change (`is_active`/`is_default`) — the same shape a FormRequest's
 * own validator produces, so the controller's generic exception handling
 * (BaseApiController::handleControllerException) needs no special case.
 */
final class ContractStatusDefaultManager
{
    /**
     * BR-5a/c for create, resolved BEFORE the row is inserted: the first row
     * ever created always becomes default regardless of $requestedDefault;
     * otherwise the actor's request is respected. The resolved default must
     * be active — checked here so create() never persists an invalid state.
     */
    public function resolveIsDefaultForCreate(bool $requestedDefault, bool $isActive): bool
    {
        $isFirstRow = ! ContractStatus::query()->exists();
        $isDefault = $isFirstRow || $requestedDefault;

        if ($isDefault && ! $isActive) {
            throw ValidationException::withMessages([
                'is_default' => [__('contract_statuses.default_requires_active')],
            ]);
        }

        return $isDefault;
    }

    /**
     * BR-5b: unsets `is_default` on every OTHER row. Called AFTER
     * $contractStatus itself has been persisted/updated with
     * `is_default = true`, inside the same transaction.
     */
    public function clearOtherDefaults(ContractStatus $contractStatus): void
    {
        ContractStatus::query()
            ->whereKeyNot($contractStatus->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * BR-5c/d/e for update: validates the {is_active, is_default} transition
     * against $contractStatus's CURRENTLY PERSISTED state, before anything is
     * written. $requestedIsActive/$requestedIsDefault are null when the field
     * was not submitted in this (partial) PATCH.
     */
    public function assertUpdateTransitionValid(ContractStatus $contractStatus, ?bool $requestedIsActive, ?bool $requestedIsDefault): void
    {
        $wasDefault = $contractStatus->is_default;

        // BR-5e: is_default cannot be turned off directly — reassign another
        // row as default instead. Checked before (d): a client trying to
        // undefault while also deactivating gets THIS message, the more
        // specific rule of the two.
        if ($wasDefault && $requestedIsDefault === false) {
            throw ValidationException::withMessages([
                'is_default' => [__('contract_statuses.default_must_be_reassigned')],
            ]);
        }

        // BR-5d: a default row cannot be deactivated (is_default stays true
        // here — the only way it could have become false was rejected
        // above).
        if ($wasDefault && $requestedIsActive === false) {
            throw ValidationException::withMessages([
                'is_active' => [__('contract_statuses.default_cannot_be_deactivated')],
            ]);
        }

        // BR-5c: a row newly promoted to default in this same PATCH must
        // resolve active (either already active, or explicitly reactivated
        // in the same payload).
        $effectiveIsActive = $requestedIsActive ?? $contractStatus->is_active;

        if ($requestedIsDefault === true && ! $effectiveIsActive) {
            throw ValidationException::withMessages([
                'is_default' => [__('contract_statuses.default_requires_active')],
            ]);
        }
    }
}
