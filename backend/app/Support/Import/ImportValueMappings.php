<?php

namespace App\Support\Import;

use App\Imports\ImportDefinition;
use App\Imports\Support\SpreadsheetReader;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Storage;

/**
 * Value-mapping plumbing shared by the generic import endpoints (spec 0348):
 * reads the distinct values of the mapped value-mapping columns straight from
 * the stored file, and normalizes / filters the submitted decisions. Domain
 * semantics (which records are selectable, suggestions) stay in the
 * ImportDefinition.
 */
final class ImportValueMappings
{
    public function __construct(private readonly SpreadsheetReader $reader) {}

    /**
     * Case/whitespace-insensitive comparison key: file values that differ
     * only by letter case or spacing are ONE value.
     */
    public static function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    /**
     * Distinct values (first-seen spelling, trimmed; blank cell = "") with
     * their counts, for each value-mapping field currently mapped to a file
     * column. Reads the file only when at least one such field is mapped.
     *
     * @param  array<string, string>  $columnMapping  column key => target
     * @param  array<int, string>|null  $fields  fields to read; defaults to the definition's value-mapping fields
     * @return array<string, array{column_key: string, values: array<int, array{value: string, count: int}>}>
     */
    public function mappedColumns(ImportDefinition $definition, ImportRun $run, array $columnMapping, ?array $fields = null): array
    {
        $fields ??= $definition->valueMappingFields();

        // Step 1: pick the first column mapped to each value-mapping field
        $columnByField = [];

        foreach ($columnMapping as $columnKey => $target) {
            if (in_array($target, $fields, true) && ! isset($columnByField[$target])) {
                $columnByField[$target] = (string) $columnKey;
            }
        }

        if ($columnByField === []) {
            return [];
        }

        // Step 2: stream the file once, tallying every needed column
        $tally = array_fill_keys(array_values($columnByField), []);

        foreach ($this->reader->rows(Storage::disk('local')->path($run->stored_path)) as $cells) {
            foreach (array_keys($tally) as $columnKey) {
                $value = trim((string) ($cells[$columnKey] ?? ''));
                $key = self::normalize($value);

                $tally[$columnKey][$key] ??= ['value' => $value, 'count' => 0];
                $tally[$columnKey][$key]['count']++;
            }
        }

        // Step 3: shape per field, in $fields order
        $mapped = [];

        foreach ($fields as $field) {
            if (isset($columnByField[$field])) {
                $mapped[$field] = [
                    'column_key' => $columnByField[$field],
                    'values' => array_values($tally[$columnByField[$field]]),
                ];
            }
        }

        return $mapped;
    }

    /**
     * Keep only the reusable `{id}` decisions (a "create" decision is run
     * specific and must never reach a template); null when nothing is left.
     *
     * @param  array<string, mixed>|null  $valueMappings
     * @return array<string, array<string, array{id: int}>>|null
     */
    public static function reusableEntries(?array $valueMappings): ?array
    {
        $reusable = [];

        foreach ($valueMappings ?? [] as $field => $decisions) {
            foreach ((array) $decisions as $value => $decision) {
                if (is_array($decision) && isset($decision['id'])) {
                    $reusable[$field][$value] = ['id' => (int) $decision['id']];
                }
            }
        }

        return $reusable === [] ? null : $reusable;
    }
}
