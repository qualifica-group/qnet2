<?php

declare(strict_types=1);

namespace App\Tables\FieldChangeRequests;

use App\DataObjects\Enums\EnumMeta;
use App\Enums\FieldChangeRequestStatus;

/**
 * Declarative column/filter catalogue for the `field-change-requests` domain
 * (spec 0078): a read-only browse over every proposed change, any resource/
 * field. Column order is FROZEN by the spec's data_contract — the frontend
 * grid renders them in this exact sequence.
 *
 * `resource_label`/`subject_label`/`field_label`/`requested_by`/`handled_by`
 * are DERIVED (no single real DB column carries them): resolved per row by
 * FieldChangeRequestRowMapper. `current_label`/`requested_label`/`reason`/
 * `handling_note` ARE real `field_change_requests` columns, display-only
 * here (no inline editing on this table — a request is immutable once
 * handled, D-4). `status` is the only badge column, mirroring
 * LeadImportColumnCatalog's own `status` (ImportStatus): here the color
 * mapping is built locally rather than via App\Enums\Concerns\HasMeta,
 * since FieldChangeRequestStatus (owned by another microtask) declares no
 * #[Color]/#[Label] attributes.
 */
final class FieldChangeRequestColumnCatalog
{
    /**
     * Badge color per status value — kept here (not on the enum) since this
     * class alone owns the table's presentation concern.
     *
     * @var array<string, string>
     */
    private const array STATUS_COLORS = [
        'pending' => 'amber',
        'approved' => 'green',
        'rejected' => 'red',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::derivedColumn('resource_label', 'fieldChangeRequests.columns.resource'),
            self::derivedColumn('subject_label', 'fieldChangeRequests.columns.subject'),
            self::derivedColumn('field_label', 'fieldChangeRequests.columns.field'),
            [
                'id' => 'current_label',
                'label' => 'fieldChangeRequests.columns.currentValue',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'requested_label',
                'label' => 'fieldChangeRequests.columns.requestedValue',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'reason',
                'label' => 'fieldChangeRequests.columns.reason',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            self::derivedColumn('requested_by', 'fieldChangeRequests.columns.requestedBy'),
            [
                'id' => 'created_at',
                'label' => 'fieldChangeRequests.columns.createdAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'status',
                'label' => 'fieldChangeRequests.columns.status',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => self::statusValues(),
            ],
            self::derivedColumn('handled_by', 'fieldChangeRequests.columns.handledBy'),
            [
                'id' => 'handled_at',
                'label' => 'fieldChangeRequests.columns.handledAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'handling_note',
                'label' => 'fieldChangeRequests.columns.handlingNote',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
        ];
    }

    /**
     * `reason` (text) and `status` (set, three values) — the only two
     * filterable columns declared above, matching AC-036's "filtro status
     * sui tre stati".
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'reason', 'type' => 'text'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'status', 'type' => 'set', 'options' => self::statusValues()],
        ];
    }

    /**
     * The only row action: open the request's detail, where the Approve/
     * Reject buttons live (spec 0078, AC-047). Nothing is mutated from this
     * grid — a request is immutable once handled (D-4) and approve/reject go
     * through their own dedicated endpoints, never the generic engine's
     * updateCell()/deleteModel(). Gated by `field-change-requests.view`; the
     * per-row visibility (the requester reading their OWN request without
     * that permission, AC-039) is decided by the Policy in actionsFor().
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
                'permission' => 'field-change-requests.view',
            ],
        ];
    }

    /**
     * Badge metadata for the `status` column: color only — the label is a
     * server-side fallback, overridden client-side by enumKeyFor()'s i18n
     * resolution (`enums.field_change_request_status.<value>`), the same
     * contract every other badge column in this codebase follows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function statusBadges(): array
    {
        return array_map(
            static fn (FieldChangeRequestStatus $case): array => (new EnumMeta(
                value: $case->value,
                label: self::fallbackLabel($case),
                color: self::STATUS_COLORS[$case->value],
            ))->toArray(),
            FieldChangeRequestStatus::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    private static function statusValues(): array
    {
        return array_map(
            static fn (FieldChangeRequestStatus $case): string => $case->value,
            FieldChangeRequestStatus::cases(),
        );
    }

    private static function fallbackLabel(FieldChangeRequestStatus $case): string
    {
        return match ($case) {
            FieldChangeRequestStatus::Pending => 'Pending',
            FieldChangeRequestStatus::Approved => 'Approved',
            FieldChangeRequestStatus::Rejected => 'Rejected',
        };
    }

    /**
     * A DERIVED (no single real DB column) label column: display-only, no
     * sort/filter hook implemented for it in this microtask.
     *
     * @return array<string, mixed>
     */
    private static function derivedColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => false,
        ];
    }
}
