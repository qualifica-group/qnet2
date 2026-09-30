<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Table\TableFieldSchema;
use App\CustomFields\Types\Concerns\AppliesTextFilter;
use App\CustomFields\Types\Concerns\DerivesRequiredRule;
use App\CustomFields\Types\Concerns\OrdersByJsonPath;
use App\CustomFields\Types\Concerns\ResolvesJsonColumn;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Repeatable table (spec 0180): `{rows:[...], summary}` stored as JSON. The
 * grid sorts/filters on the server-computed `summary` only; row/cell rules
 * live in TableFieldSchema.
 */
class TableFieldType implements FieldTypeHandler, ProvidesNestedValidationRules
{
    use AppliesTextFilter {
        applyFilter as private applySummaryFilter;
    }
    use DerivesRequiredRule;
    use OrdersByJsonPath {
        applySort as private applySummarySort;
    }
    use ResolvesJsonColumn;

    private const string SUMMARY_PATH = 'summary';

    public function key(): string
    {
        return 'table';
    }

    public function storageType(): string
    {
        return 'json';
    }

    public function columnType(): string
    {
        return 'table';
    }

    public function filterType(): string
    {
        return 'text';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'array'];
    }

    public function nestedValidationRules(CustomFieldDefinition $definition): array
    {
        return $this->schema($definition)->rules();
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        return $this->schema($definition)->normalize($value);
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $this->schema($definition)->resolve($stored);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $column, string $jsonKey, array $filter): void
    {
        $this->applySummaryFilter($query, $column, $this->summaryKey($jsonKey), $filter);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $column, string $jsonKey, string $direction): void
    {
        $this->applySummarySort($query, $column, $this->summaryKey($jsonKey), $direction);
    }

    public function distinctValues(Builder $query, string $column, string $jsonKey): array
    {
        return [];
    }

    public function toMeta(CustomFieldDefinition $definition): array
    {
        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
        ];
    }

    private function summaryKey(string $jsonKey): string
    {
        return $jsonKey.'->'.self::SUMMARY_PATH;
    }

    private function schema(CustomFieldDefinition $definition): TableFieldSchema
    {
        return TableFieldSchema::fromConfig($definition->config ?? [], $this->isRequired($definition));
    }
}
