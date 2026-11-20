<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

/**
 * The single Gestori Account column of the `request-management` grid
 * (spec 0406, superseding the per-position columns of spec 0382): the whole
 * `quote_user` team as a list of people, the twin of the Offerte column
 * (spec 0373). It is the same in every tab.
 */
final class RequestManagerColumns
{
    /** The whole team as a list of people (spec 0373). */
    public const string MANAGERS_COLUMN_ID = 'managers';

    /** The team's permission and write key (spec 0097). */
    public const string TEAM_FIELD = 'manager_slots';

    private const int MANAGERS_WIDTH = 140;

    /**
     * Spec 0373 D-7/D-8: the cell edits the team as a list of people,
     * RequestTeamCellWriter turns it into the work panel's `manager_slots`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [[
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
        ]];
    }
}
