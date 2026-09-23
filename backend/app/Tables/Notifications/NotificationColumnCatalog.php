<?php

declare(strict_types=1);

namespace App\Tables\Notifications;

use App\DataObjects\Enums\EnumMeta;
use App\Enums\NotificationLevelEnum;

/**
 * Declarative column/filter/action catalogue for the `notifications` domain
 * (spec 0150). Column order is FROZEN by the spec's data_contract (D-5) — the
 * frontend grid renders them in this exact sequence.
 *
 * `status`/`title`/`message`/`level` are DERIVED: no real `notifications`
 * column carries them (status comes from `read_at`'s nullity, the other
 * three from the JSON `data` payload) — resolved per row by
 * NotificationRowMapper and, for filter/sort/search, by
 * NotificationsTableDefinition's applyDerivedFilter/applyDerivedSort/
 * applyDerivedSearch overrides. `created_at`/`read_at` ARE real columns.
 * `action_url` is display-only (no filter/sort hook): a formatted link has no
 * discrete value list, mirroring every other COMPUTED text column in this
 * codebase.
 */
final class NotificationColumnCatalog
{
    /**
     * Badge color per `status` value — kept here (not on an enum, unlike
     * `level`) since read/unread is a two-value derivation of `read_at`, not
     * a domain enum of its own.
     *
     * @var array<string, string>
     */
    private const array STATUS_COLORS = [
        'unread' => 'blue',
        'read' => 'slate',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'status',
                'label' => 'notifications.columns.status',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => self::statusValues(),
            ],
            [
                'id' => 'title',
                'label' => 'notifications.columns.title',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                // Free text with no finite value list (like WorkOrderColumnCatalog's
                // contract_number/quote): the Excel-like Set Filter's /values
                // endpoint never reaches this column.
                'hasFilterValues' => false,
            ],
            [
                'id' => 'message',
                'label' => 'notifications.columns.message',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                'hasFilterValues' => false,
            ],
            [
                'id' => 'level',
                'label' => 'notifications.columns.level',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => self::levelValues(),
            ],
            [
                'id' => 'created_at',
                'label' => 'notifications.columns.createdAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'read_at',
                'label' => 'notifications.columns.readAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'action_url',
                'label' => 'notifications.columns.actionUrl',
                'type' => 'link',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'filterType' => null,
            ],
        ];
    }

    /**
     * `status`/`title`/`message`/`level`/`created_at`/`read_at` — every
     * column but `action_url` (D-5: "tutte filterable tranne action_url").
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'status', 'type' => 'set', 'options' => self::statusValues()],
            ['columnId' => 'title', 'type' => 'text'],
            ['columnId' => 'message', 'type' => 'text'],
            ['columnId' => 'level', 'type' => 'set', 'options' => self::levelValues()],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'read_at', 'type' => 'date'],
        ];
    }

    /**
     * The two row actions (D-2): each gated PER ROW by actionsFor() (the
     * opposite of the other's read state, never both at once) — no
     * `permission` key, so both are advertised to every authenticated actor
     * (D-1: no new Spatie permission for this resource).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'mark-read',
                'label' => 'notifications.actions.markRead',
                'icon' => 'check-circle',
                'type' => 'link',
                'confirm' => false,
            ],
            [
                'key' => 'mark-unread',
                'label' => 'notifications.actions.markUnread',
                'icon' => 'circle',
                'type' => 'link',
                'confirm' => false,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function statusBadges(): array
    {
        return array_map(
            static fn (string $value): array => (new EnumMeta(
                value: $value,
                label: self::statusFallbackLabel($value),
                color: self::STATUS_COLORS[$value],
            ))->toArray(),
            self::statusValues(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function levelBadges(): array
    {
        return array_map(
            static fn (EnumMeta $meta): array => $meta->toArray(),
            NotificationLevelEnum::options(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function statusValues(): array
    {
        return ['unread', 'read'];
    }

    /**
     * @return array<int, string>
     */
    private static function levelValues(): array
    {
        return array_map(
            static fn (NotificationLevelEnum $case): string => $case->value,
            NotificationLevelEnum::cases(),
        );
    }

    private static function statusFallbackLabel(string $value): string
    {
        return $value === 'unread' ? 'Unread' : 'Read';
    }
}
