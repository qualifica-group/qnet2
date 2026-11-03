<?php

namespace Tests\Stubs;

use App\Enums\ImportDedupMode;
use App\Imports\AbstractImportDefinition;
use App\Imports\ImportRowContext;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Support\Import\ImportValueMappings;
use Illuminate\Validation\ValidationException;

/**
 * Test-only wizard definition exercising the GENERIC value-mapping /
 * attribute-target engine (spec 0348) before the real request-management
 * definition exists. Registered under the `request-management` key in tests so
 * the per-domain ImportRunPolicy mapping is exercised too; authorizeImport()
 * mirrors what the real definition does (`request-management.import`).
 */
final class FakeValueMappingImportDefinition extends AbstractImportDefinition
{
    /** @var array<int, array{id: int, name: string, group: ?string}> */
    public const array STATUS_OPTIONS = [
        ['id' => 1, 'name' => 'Open', 'group' => 'open'],
        ['id' => 2, 'name' => 'Attesa _ App. APL', 'group' => 'pending'],
    ];

    public function domain(): string
    {
        return 'request-management';
    }

    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    public function authorizeImport(User $actor): bool
    {
        return $actor->can('request-management.import');
    }

    public function columns(): array
    {
        return [['id' => 'first_name', 'required' => false]];
    }

    public function fields(): array
    {
        return array_map(static fn (string $id): array => [
            'id' => $id, 'label' => $id, 'required' => false, 'group' => null, 'type' => 'text',
        ], ['first_name', 'workflow_status', 'source_name']);
    }

    public function globalConfig(): array
    {
        return [[
            'id' => 'product_category_id', 'label' => 'category', 'required' => true,
            'for_select_resource' => 'product-categories', 'multiple' => false, 'depends_on' => null,
            'default' => null, 'required_unless_mapped' => null,
        ]];
    }

    public function dedupModes(): array
    {
        return [ImportDedupMode::CreateNew];
    }

    public function supportsOpportunityConversion(): bool
    {
        return false;
    }

    public function valueMappingFields(): array
    {
        return ['workflow_status', 'source_name'];
    }

    public function attributeTargets(): ?array
    {
        return ['context' => 'quote', 'depends_on' => 'product_category_id'];
    }

    public function valueMappingPreview(array $mappedColumns, array $globalConfig): array
    {
        if (isset($mappedColumns['workflow_status']) && empty($globalConfig['product_category_id'])) {
            throw ValidationException::withMessages(['global_config.product_category_id' => ['Required.']]);
        }

        $entries = [];

        foreach ($mappedColumns as $field => $column) {
            $entries[] = [
                'field' => $field,
                'column_key' => $column['column_key'],
                'allow_create' => $field === 'workflow_status',
                'options' => self::STATUS_OPTIONS,
                'values' => array_map(fn (array $value): array => [
                    ...$value,
                    'suggested_id' => $this->suggest($value['value']),
                ], $column['values']),
            ];
        }

        return $entries;
    }

    public function validateValueMappings(array $mappedColumns, array $valueMappings, array $globalConfig): array
    {
        $errors = [];
        $optionIds = array_column(self::STATUS_OPTIONS, 'id');
        $optionNames = array_map(ImportValueMappings::normalize(...), array_column(self::STATUS_OPTIONS, 'name'));

        foreach ($mappedColumns as $field => $column) {
            $decisions = array_combine(
                array_map(ImportValueMappings::normalize(...), array_keys($valueMappings[$field] ?? [])),
                array_values($valueMappings[$field] ?? []),
            );

            foreach ($column['values'] as $value) {
                $decision = $decisions[ImportValueMappings::normalize($value['value'])] ?? null;

                if ($decision === null) {
                    $errors["value_mappings.{$field}"][] = "No mapping for [{$value['value']}].";
                } elseif (isset($decision['id']) && ! in_array($decision['id'], $optionIds, true)) {
                    $errors["value_mappings.{$field}"][] = 'Unknown id.';
                } elseif (isset($decision['create']) && in_array(ImportValueMappings::normalize($decision['create']['name'] ?? ''), $optionNames, true)) {
                    $errors["value_mappings.{$field}"][] = 'Name already present.';
                }
            }
        }

        return $errors;
    }

    public function validateAttributeTargets(array $columnMapping, array $globalConfig): array
    {
        $errors = [];

        foreach ($columnMapping as $columnKey => $target) {
            if (str_starts_with($target, 'attribute.') && $target !== 'attribute.price') {
                $errors["column_mapping.{$columnKey}"][] = 'Attribute not applicable.';
            }
        }

        return $errors;
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        return [];
    }

    public function dedupKey(array $row): ?string
    {
        return null;
    }

    public function existsInDatabase(string $key): bool
    {
        return false;
    }

    public function createRow(User $actor, array $row): void {}

    private function suggest(string $value): ?int
    {
        foreach (self::STATUS_OPTIONS as $option) {
            if (ImportValueMappings::normalize($option['name']) === ImportValueMappings::normalize($value)) {
                return $option['id'];
            }
        }

        return null;
    }
}
