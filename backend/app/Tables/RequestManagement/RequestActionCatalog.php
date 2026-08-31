<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

/**
 * Declarative ACTION catalogue for the `request-management` domain, split out
 * of RequestColumnCatalog (file-size budget, engineering.md §6) when the
 * "Stato di lavorazione" column pushed that file past the hard limit. Pure
 * data, no logic — the same shape RequestColumnCatalog keeps for columns and
 * filters; RequestManagementTableDefinition::actions() delegates here.
 */
final class RequestActionCatalog
{
    /**
     * `view` ("Lavora") and `documents` — no edit/delete (the CRUD boundary
     * stays on `opportunities.*`, never request-management). `documents`
     * reuses the polymorphic Attachment subsystem on the same Opportunity
     * record as the opportunities module, but is gated by this module's OWN
     * permission (`request-management.viewDocuments`, D-2) and carries the
     * per-row `documents_count` badge. `activity` (D-7, amended) opens this
     * module's OWN activity surface: the generic framework used to resolve its
     * Policy by MODEL CLASS (Opportunity), which is why the action did not
     * exist — it now goes through RequestManagementActivityAuthorizer, gated by
     * `request-management.viewActivity`. Declared LAST on purpose: with the
     * shared `INLINE_ACTION_LIMIT`, the fourth action falls into the overflow
     * (three-dots) menu, which is where consultation belongs.
     * `notes` (spec 0052 B4b) opens the collaborative-notes dialog: gated by
     * `request-management.view`, NOT a notes permission — reading a record's
     * notes is inherited from the ability to open the record (D-6), while
     * writing is separately authorized server-side by `notes.create` inside
     * the dialog itself. `count_field` (spec 0052 B4c, reversing the earlier
     * "out of scope" call) carries `notes_count` — dalla direttiva utente
     * 2026-08-07 le note della SINGOLA Offerta della riga, roots AND replies,
     * soft-deleted escluse: esattamente il thread che il dialog apre
     * (`lockedQuoteId`), come per le Offerte — mirroring `documents`'
     * `documents_count` badge.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'request-management.view',
            ],
            [
                'key' => 'documents',
                'label' => 'actions.documents',
                'icon' => 'paperclip',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.viewDocuments',
                'count_field' => 'documents_count',
            ],
            [
                'key' => 'notes',
                'label' => 'actions.notes',
                'icon' => 'messages-square',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.view',
                'count_field' => 'notes_count',
            ],
            // "Trasferisci contatto" (spec 0079): declared AFTER the first
            // three so it falls into the overflow (three-dots) menu
            // (INLINE_ACTION_LIMIT = 3, row-actions.tsx:33) — not frequent
            // enough for an inline slot. Opens AssignOperatorsDialog in its
            // `lockedMode="single"` shape, gated by its OWN ability
            // (transferContact), on top of `request-management.update`.
            [
                'key' => 'transfer-contact',
                'label' => 'actions.transferContact',
                'icon' => 'arrow-right-left',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.transferContact',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'request-management.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.viewActivity',
            ],
        ];
    }
}
