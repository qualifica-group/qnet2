<?php

namespace App\Imports\Support;

use Illuminate\Support\Str;

/**
 * Pure auto-mapping proposal for the wizard's mapping step (spec 0033,
 * AC-003): compares the file's detected column names (the same
 * ColumnAnalysis::$columns shape SpreadsheetReader::analyze() produces)
 * against an ImportDefinition's fields() catalogue and proposes column key
 * => field id. No I/O, no side effect — the actor can still override the
 * proposal in the UI before PUT .../configure persists the final
 * `column_mapping`; StageImportJob is what actually consumes it.
 *
 * Matching is normalized-exact (lower/trim/accent-strip/underscore-and-dash-
 * to-space), never fuzzy similarity — that is GeoResolver's job for geo
 * names. Each field also matches on a curated alias list
 * (`config('imports.column_aliases')`), centralized there rather than
 * scattered across every ImportDefinition; `config('imports.
 * contextual_aliases')` re-targets an alias whose meaning depends on another
 * matched field ("Nome" beside "Cognome" is the given name, not the full one).
 */
final class ColumnMapper
{
    /**
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $fileColumns
     * @param  array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>  $fields
     */
    public function suggest(array $fileColumns, array $fields): MappingSuggestion
    {
        $columnKeys = ColumnAnalysis::columnKeys($fileColumns);
        $lookup = $this->buildLookup($fields);

        // Step 1: match every column against the normalized field/alias lookup
        $matchesByField = $this->matchColumns($fileColumns, $columnKeys, $lookup);

        // Step 2: re-target the contextual aliases whose sibling field matched too
        $matchesByField = $this->applyContextualAliases($fileColumns, $columnKeys, $matchesByField, $fields);

        // Step 3: a field matched by 2+ columns is a conflict, not a mapping
        [$mapping, $conflicts] = $this->splitConflicts($matchesByField);

        // Step 4: derive the remaining diagnostics from the resolved mapping
        return new MappingSuggestion(
            mapping: $mapping,
            missingRequired: $this->missingRequiredFieldIds($fields, $mapping),
            duplicateColumns: $this->duplicateColumnKeys($fileColumns, $columnKeys),
            unusedColumns: $this->unusedColumnKeys($columnKeys, $mapping, $conflicts),
            conflicts: $conflicts,
        );
    }

    /**
     * @param  array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>  $fields
     * @return array<string, string> normalized candidate string => field id
     */
    private function buildLookup(array $fields): array
    {
        $aliases = config('imports.column_aliases', []);
        $lookup = [];

        foreach ($fields as $field) {
            $candidates = [$field['id'], $field['label'], ...($aliases[$field['id']] ?? [])];

            foreach ($candidates as $candidate) {
                $key = $this->normalize((string) $candidate);

                if ($key !== '') {
                    $lookup[$key] ??= $field['id'];
                }
            }
        }

        return $lookup;
    }

    /**
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $fileColumns
     * @param  array<int, string>  $columnKeys
     * @param  array<string, string>  $lookup
     * @return array<string, array<int, string>> field id => matched column keys, in file order
     */
    private function matchColumns(array $fileColumns, array $columnKeys, array $lookup): array
    {
        $matches = [];

        foreach (array_values($fileColumns) as $index => $column) {
            $fieldId = $lookup[$this->normalize($column['name'])] ?? null;

            if ($fieldId !== null) {
                $matches[$fieldId][] = $columnKeys[$index];
            }
        }

        return $matches;
    }

    /**
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $fileColumns
     * @param  array<int, string>  $columnKeys
     * @param  array<string, array<int, string>>  $matchesByField
     * @param  array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>  $fields
     * @return array<string, array<int, string>>
     */
    private function applyContextualAliases(array $fileColumns, array $columnKeys, array $matchesByField, array $fields): array
    {
        $fieldIds = array_column($fields, 'id');

        foreach (config('imports.contextual_aliases', []) as $fieldId => $rule) {
            if (! in_array($fieldId, $fieldIds, true) || ! isset($matchesByField[$rule['when_matched']])) {
                continue;
            }

            $aliases = array_map(fn (string $alias): string => $this->normalize($alias), $rule['aliases']);

            foreach (array_values($fileColumns) as $index => $column) {
                if (in_array($this->normalize($column['name']), $aliases, true)) {
                    $matchesByField = $this->withoutColumn($matchesByField, $columnKeys[$index]);
                    $matchesByField[$fieldId][] = $columnKeys[$index];
                }
            }
        }

        return $matchesByField;
    }

    /**
     * @param  array<string, array<int, string>>  $matchesByField
     * @return array<string, array<int, string>>
     */
    private function withoutColumn(array $matchesByField, string $columnKey): array
    {
        $remaining = [];

        foreach ($matchesByField as $fieldId => $matchedColumnKeys) {
            $kept = array_values(array_diff($matchedColumnKeys, [$columnKey]));

            if ($kept !== []) {
                $remaining[$fieldId] = $kept;
            }
        }

        return $remaining;
    }

    /**
     * @param  array<string, array<int, string>>  $matchesByField
     * @return array{0: array<string, string>, 1: array<string, array<int, string>>}
     */
    private function splitConflicts(array $matchesByField): array
    {
        $mapping = [];
        $conflicts = [];

        foreach ($matchesByField as $fieldId => $matchedColumnKeys) {
            if (count($matchedColumnKeys) === 1) {
                $mapping[$matchedColumnKeys[0]] = $fieldId;
            } else {
                $conflicts[$fieldId] = $matchedColumnKeys;
            }
        }

        return [$mapping, $conflicts];
    }

    /**
     * @param  array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>  $fields
     * @param  array<string, string>  $mapping
     * @return array<int, string>
     */
    private function missingRequiredFieldIds(array $fields, array $mapping): array
    {
        $mappedFieldIds = array_values($mapping);
        $missing = [];

        foreach ($fields as $field) {
            if (($field['required'] ?? false) && ! in_array($field['id'], $mappedFieldIds, true)) {
                $missing[] = $field['id'];
            }
        }

        return $missing;
    }

    /**
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $fileColumns
     * @param  array<int, string>  $columnKeys
     * @return array<int, string>
     */
    private function duplicateColumnKeys(array $fileColumns, array $columnKeys): array
    {
        $duplicates = [];

        foreach (array_values($fileColumns) as $index => $column) {
            if ($column['duplicate']) {
                $duplicates[] = $columnKeys[$index];
            }
        }

        return $duplicates;
    }

    /**
     * @param  array<int, string>  $columnKeys
     * @param  array<string, string>  $mapping
     * @param  array<string, array<int, string>>  $conflicts
     * @return array<int, string>
     */
    private function unusedColumnKeys(array $columnKeys, array $mapping, array $conflicts): array
    {
        $used = array_keys($mapping);

        foreach ($conflicts as $conflictingColumnKeys) {
            $used = array_merge($used, $conflictingColumnKeys);
        }

        return array_values(array_diff($columnKeys, $used));
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
