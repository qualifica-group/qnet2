<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\Models\CustomFieldDefinition;

/**
 * Optional companion of FieldTypeHandler for structured values (spec 0180):
 * rules on keys RELATIVE to the field value (e.g. `rows.*.id`), which the
 * caller prefixes with the field path (`custom_fields.<key>.`), so validation
 * errors point at the single nested cell.
 */
interface ProvidesNestedValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function nestedValidationRules(CustomFieldDefinition $definition): array;
}
