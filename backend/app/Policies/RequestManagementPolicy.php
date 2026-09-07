<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Dedicated policy for the `request-management` resource (spec 0049,
 * decision D-2): the "Request Management" module is an operative view over
 * Opportunity records, but access is authorized through its OWN permission
 * set (`request-management.*`), never `opportunities.*`.
 *
 * Two additions beyond BasePolicy: `viewAll` lifts the manager-scoping guard
 * (spec 0049 D-3, `RequestManagementScope`) so the actor sees every
 * opportunity instead of only the ones where they are Account Manager, and
 * `viewDocuments` gates the documents surface (the reused polymorphic
 * Attachment subsystem) with this module's OWN permission, exactly as
 * OpportunityPolicy does for `opportunities.viewDocuments`.
 */
class RequestManagementPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'request-management';
    }

    /**
     * Resource-level gate lifting the manager-scoping guard (spec 0049 D-3):
     * with this ability the actor sees every opportunity in
     * request-management, not only the ones they manage.
     */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission('viewAll'));
    }

    /**
     * Resource-level gate for the documents surface of this module (row
     * action + dialog). The per-attachment boundary stays with
     * AttachmentPolicy (`attachments.*`) on each attachment endpoint.
     */
    public function viewDocuments(User $user): bool
    {
        return $user->can($this->permission('viewDocuments'));
    }

    /**
     * Resource-level gate for assigning the GA2 "Operatore" AT CREATION time
     * (user directive 2026-07-29): the supervisor ability. Creating a request
     * is `request-management.create`, but deciding WHO works it is a
     * supervisory act, so the create form's Operatore field — and the
     * `operator_id` key of POST /api/request-management — need this on top.
     * Reassigning an EXISTING request stays governed by the per-field matrix
     * (RequestManagementAuthorization) on the work panel's PATCH.
     */
    public function assignOperator(User $user): bool
    {
        return $user->can($this->permission('assignOperator'));
    }

    /**
     * Resource-level gate for the row/bulk "Trasferisci contatto" action
     * (spec 0079): moves a request to another Sede operativa, assigning that
     * site's own GA2 "Operatore" in the same call. Required ON TOP OF
     * `update` (RequestManagementController::transfer()), mirroring
     * `assignOperator` above: a combined write of Sede + Operatore resolves
     * no per-field permission, so this ability is the only gate on it.
     */
    public function transferContact(User $user): bool
    {
        return $user->can($this->permission('transferContact'));
    }

    /**
     * Resource-level gate for the BULK "Assegna GA3" action (spec 0104,
     * direttiva utente 2026-09-07): moves the GA3 slot of many Offerte at
     * once, the slot the grid labels with the scoped category's
     * `manager_labels[3]` ("Tutor" where configured so). Required ON TOP OF
     * `update`, exactly as `assignOperator` and `transferContact` above.
     *
     * Its own ability and not a reuse of `assignOperator` (D-3): deciding who
     * tutors a batch of requests is a separate grant from deciding who
     * operates them, and a role must be able to hold one without the other.
     * Restricting the `manager_ga3_id` FIELD does not gate this endpoint —
     * a bulk write resolves no per-field permission, the same hole this
     * module already closes for the Sede+Operatore pair.
     */
    public function assignManagerGa3(User $user): bool
    {
        return $user->can($this->permission('assignManagerGa3'));
    }

    /**
     * Distribution-list ability (spec 0081, decisione utente 2026-08-04):
     * who is copied on the "contatto trasferito" notifications. It authorizes
     * NO endpoint — it decides recipients, which is why nothing calls it
     * through `$this->authorize()`; RequestTransferService resolves the set
     * with `User::permission(...)`.
     *
     * A permission and not the spatie role `supervisor` the spec 0079
     * implementation hardcoded: "chi ha massimo accesso su gestione
     * richieste" is a grant, and a grant survives roles being renamed, split
     * or added from the roles UI. Declared here rather than in a config array
     * for the same reason — this is the only vocabulary the roles screen can
     * edit.
     */
    public function receiveTransferNotifications(User $user): bool
    {
        return $user->can($this->permission('receiveTransferNotifications'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewAll', 'viewDocuments', 'assignOperator', 'assignManagerGa3', 'transferContact', 'receiveTransferNotifications'];
    }
}
