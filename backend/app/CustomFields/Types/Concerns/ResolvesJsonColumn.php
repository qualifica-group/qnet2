<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

/**
 * Builds the bound JSON-path column expression for a JSON storage column
 * (e.g. `custom_field_values.values`, `opportunities.attribute_values`).
 * `$column` (the base JSON column) and `$jsonKey` (the key inside it) MUST
 * always be resolved server-side by the caller — an allow-listed definition
 * key/column, never raw request input (backend.md §8 / security.md §8).
 *
 * `$column` is a caller-supplied parameter (spec 0064, T-M1) rather than a
 * hardcoded constant, so the SAME per-type grid logic (filter/sort/distinct)
 * serves any JSON-column-backed field system, not only the custom-fields
 * subsystem this trait originated in (spec 0021).
 */
trait ResolvesJsonColumn
{
    private function jsonColumn(string $column, string $jsonKey): string
    {
        return "{$column}->{$jsonKey}";
    }
}
