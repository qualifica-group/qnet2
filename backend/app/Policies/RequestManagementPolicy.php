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
 * Three additions beyond BasePolicy: `viewAll` lifts the manager-scoping
 * guard (spec 0049 D-3, `RequestManagementScope`) so the actor sees every
 * opportunity instead of only the ones where they are Account Manager,
 * `viewSite` widens that same guard to the actor's own Sedi operative without
 * lifting it (spec 0105), and `viewDocuments` gates the documents surface
 * (the reused polymorphic Attachment subsystem) with this module's OWN
 * permission, exactly as OpportunityPolicy does for
 * `opportunities.viewDocuments`.
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
     * Resource-level gate for the THIRD visibility tier (spec 0105, D-1): the
     * actor sees the requests whose Sede operativa
     * (`quotes.operational_site_id`) is one of their own memberships —
     * physical or remote alike (spec 0103 D-1) — even where they are not the
     * GA2 "Operatore".
     *
     * Independent of `viewAll`, not a weaker degree of it (D-5): holding one
     * implies nothing about the other, and an actor who holds both is served
     * by `viewAll`, which RequestManagementScope evaluates first. Like
     * `viewAll` it is a ROW gate and grants no action: every write still asks
     * for its own ability on top (D-2).
     */
    public function viewSite(User $user): bool
    {
        return $user->can($this->permission('viewSite'));
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
     * Resource-level gate for the BULK "Assegna GA1" action (spec 0104,
     * direttiva utente 2026-09-07, moved onto position 1 by the direttiva
     * utente 2026-09-08): moves the GA1 slot of many Offerte at once, the
     * slot the grid labels with the scoped category's `manager_labels[1]`. Required ON TOP OF
     * `update`, exactly as `assignOperator` and `transferContact` above.
     *
     * Its own ability and not a reuse of `assignOperator` (D-3): deciding who
     * tutors a batch of requests is a separate grant from deciding who
     * operates them, and a role must be able to hold one without the other.
     * Restricting the `manager_ga1_id` FIELD does not gate this endpoint —
     * a bulk write resolves no per-field permission, the same hole this
     * module already closes for the Sede+Operatore pair.
     */
    public function assignManagerGa1(User $user): bool
    {
        return $user->can($this->permission('assignManagerGa1'));
    }

    /**
     * Resource-level gate for the THIRD state of the work panel's team block
     * (direttiva utente 2026-09-08): the actor SEES the squadra and may only
     * ADD members to it — the ones already there stay untouchable, neither
     * reassigned, nor moved to another slot, nor removed.
     *
     * An ability of its own and not a third degree of the per-field matrix
     * (`role_field_permissions` only expresses visible/editable/required):
     * widening FieldPermission would change the shape EVERY module's matrix
     * speaks, for a rule that belongs to this one panel. Required ON TOP OF
     * `update`, exactly as `assignOperator`/`transferContact` above, and
     * meaningful only WHILE `manager_slots` is visible but not editable for
     * the actor — an editable team already allows strictly more, and a hidden
     * one grants nothing to append to (UpdateRequestRequest checks both).
     */
    public function appendTeamMember(User $user): bool
    {
        return $user->can($this->permission('appendTeamMember'));
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
     * Resource-level gate for generating/downloading the CSV report (spec
     * 0106): a standalone capability, independent of `viewAll`/`viewSite`
     * (which widen ROW visibility) — the report aggregates over the actor's
     * OWN visibility scope (RequestManagementScope), it grants no wider read
     * than the grid already does. NOT implied by, nor implying,
     * `request-management.export` (grid row export): two distinct
     * capabilities (data_contract, permission.semantics).
     */
    public function report(User $user): bool
    {
        return $user->can($this->permission('report'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewAll', 'viewSite', 'viewDocuments', 'assignOperator', 'assignManagerGa1', 'transferContact', 'appendTeamMember', 'receiveTransferNotifications', 'report'];
    }
}
