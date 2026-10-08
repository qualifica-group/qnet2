<?php

namespace App\Services\Table;

use App\Models\User;
use App\Tables\TableDefinition;

/**
 * Cross-field checks for `rowGroupCols` / `groupKeys` (spec 0197, D-4) that a
 * static rule cannot express: the domain opt-in, the actor's allow-list of
 * groupable columns, the key count against the opened depth, and the
 * exclusion with tree / Kanban modes. The shape (arrays of bounded strings,
 * distinct columns, max depth) is declared in TableRowsRequest::rules().
 */
final class RowGroupValidator
{
    /**
     * @param  array<int, string>  $rowGroupCols
     * @param  array<int, string>  $groupKeys
     * @return array<string, string> error message keyed by request field
     */
    public function errors(TableDefinition $definition, User $actor, array $rowGroupCols, array $groupKeys, bool $treeOrKanban): array
    {
        if ($rowGroupCols === []) {
            return $groupKeys === [] ? [] : ['groupKeys' => 'groupKeys requires rowGroupCols.'];
        }

        if (! $definition->supportsRowGroups()) {
            return ['rowGroupCols' => 'Row grouping is not supported for this domain.'];
        }

        if ($treeOrKanban) {
            return ['rowGroupCols' => 'rowGroupCols cannot be combined with tree or Kanban mode.'];
        }

        $groupable = array_keys($definition->groupableColumns($actor));

        foreach ($rowGroupCols as $position => $columnId) {
            if (! in_array($columnId, $groupable, true)) {
                return ["rowGroupCols.{$position}" => "Grouping is not allowed on column [{$columnId}]."];
            }
        }

        if (count($groupKeys) > count($rowGroupCols)) {
            return ['groupKeys' => 'groupKeys cannot be longer than rowGroupCols.'];
        }

        return [];
    }
}
