<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Services\RequestManagement\RequestOperatorWriter;
use App\Support\ManagerPositions;

/**
 * The Gestori Account columns of the `request-management` grid: one column per
 * `quote_user` POSITION (spec 0382, replacing the fixed Tutor/Operatore/
 * Operatore APL columns) plus the whole team as a list (`managers`, spec 0373).
 *
 * The catalogue declares every position up to ManagerPositions::MAX, so the
 * structural PATCH lookup and the preference allow-lists always know them;
 * RequestManagerPositionLayout trims, names and hides them per tab.
 *
 * Column ids stay stable for saved views and filters (D-5): position 2 is the
 * former Operatore `operator_ga2`, every other position `manager_ga{n}`.
 * Position 2 keeps everything the Operatore did: its picker is scoped to the
 * row's Sede and required categories, its write projects
 * `quotes.operator_id` and notifies the new Operatore, and its sort/filter
 * read that denormalized FK (RequestRelationColumns). Every other position
 * reads the pivot (RequestManagerSlotColumn) and writes under its own field
 * key (`manager_ga{n}_id`), gated by the TEAM permission (`permissionField`,
 * D-6): `editableField` is also the key `updateCell()` receives, so the
 * positions need distinct write keys while sharing one row of the role matrix.
 */
final class RequestManagerColumns
{
    /** The position-2 column id — the former "Operatore", kept for saved views (spec 0382 D-5). */
    public const string OPERATOR_COLUMN_ID = 'operator_ga2';

    /** The whole team as a list of people, the twin of the Offerte column (spec 0373). */
    public const string MANAGERS_COLUMN_ID = 'managers';

    /** The team's permission key, shared by every position column (spec 0382 D-6). */
    public const string TEAM_FIELD = 'manager_slots';

    private const string POSITION_COLUMN_ID = 'manager_ga%d';

    private const int OPERATOR_WIDTH = 201;

    private const int POSITION_WIDTH = 177;

    private const int MANAGERS_WIDTH = 140;

    public static function columnId(int $position): string
    {
        return $position === ManagerPositions::OPERATOR ? self::OPERATOR_COLUMN_ID : sprintf(self::POSITION_COLUMN_ID, $position);
    }

    /**
     * The position a column id names, null for any other column.
     */
    public static function positionOf(string $columnId): ?int
    {
        $position = array_search($columnId, self::positions(), true);

        return $position === false ? null : $position;
    }

    /**
     * The position a cell write key names, null for any other key. The
     * Operatore is not here: its cell writes under the team key (spec 0097 D-3b).
     */
    public static function positionOfField(string $fieldKey): ?int
    {
        foreach (array_keys(self::positions()) as $position) {
            if ($position !== ManagerPositions::OPERATOR && RequestOperatorWriter::positionField($position) === $fieldKey) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Every position the catalogue declares, as position => column id.
     *
     * @return array<int, string>
     */
    public static function positions(): array
    {
        $positions = [];

        for ($position = 1; $position <= ManagerPositions::MAX; $position++) {
            $positions[$position] = self::columnId($position);
        }

        return $positions;
    }

    /**
     * The position columns, in position order, then the team column.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            ...array_map(
                static fn (int $position): array => $position === ManagerPositions::OPERATOR
                    ? self::operatorColumn()
                    : self::positionColumn($position),
                array_keys(self::positions()),
            ),
            self::managersColumn(),
        ];
    }

    /**
     * The catalogue header of a position: "Gestore account N", which a
     * category tab replaces with its own label (spec 0382 D-2/D-3).
     */
    public static function defaultLabel(int $position): string
    {
        return __('Account manager :position', ['position' => $position]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function operatorColumn(): array
    {
        // Spec 0055 D-6 / 0087 D-9: the offer's own Operatore slot,
        // denormalized onto `quotes.operator_id`; clearing the cell
        // un-assigns the request. `editableField` is the team key (spec 0097
        // D-3a): WritesInlineEditableCells turns a single id under it back
        // into `operator_id` before `updateWork()` (D-3b), the only reader
        // that knows the Operatore write. `relation.scope` (direttive utente
        // 2026-07-23 and 2026-09-10): the picker lists the operators of the
        // row's own Sede competent for the categories the offer requires; an
        // offer missing either keeps the full list on that half.
        return [
            ...self::positionColumn(ManagerPositions::OPERATOR),
            'width' => self::OPERATOR_WIDTH,
            'editableField' => self::TEAM_FIELD,
            'relation' => [
                'resource' => 'users',
                'scope' => [
                    'operational_site_id' => 'operational_site',
                    'competence_category_ids' => RequestAssignmentScope::CATEGORIES_KEY,
                ],
            ],
        ];
    }

    /**
     * Any other position: an unscoped picker over every user (only the
     * Operatore is bound to the Sede), no denormalized column and no
     * notification (spec 0382 D-7).
     *
     * @return array<string, mixed>
     */
    private static function positionColumn(int $position): array
    {
        return [
            'id' => self::columnId($position),
            'label' => self::defaultLabel($position),
            'type' => 'text',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'set',
            'editable' => true,
            'editableField' => RequestOperatorWriter::positionField($position),
            'permissionField' => self::TEAM_FIELD,
            'relation' => ['resource' => 'users'],
            'nullable' => true,
            'width' => self::POSITION_WIDTH,
        ];
    }

    /**
     * Spec 0373 D-7/D-8: shares the team key with the Operatore slot;
     * WritesInlineEditableCells tells the two apart by the value.
     *
     * @return array<string, mixed>
     */
    private static function managersColumn(): array
    {
        return [
            'id' => self::MANAGERS_COLUMN_ID,
            'label' => 'requestManagement.columns.managers',
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'set',
            'editable' => true,
            'editor' => 'multiselect',
            'relation' => ['resource' => 'users'],
            'editableField' => self::TEAM_FIELD,
            'width' => self::MANAGERS_WIDTH,
        ];
    }
}
