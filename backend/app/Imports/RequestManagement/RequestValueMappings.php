<?php

namespace App\Imports\RequestManagement;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Support\Import\ImportValueMappings;
use Illuminate\Validation\ValidationException;

/**
 * The wizard's "Valori" step for the request import (spec 0348): which status
 * of the Offerta's workflow and which Fonte each distinct file value stands for.
 * Builds the options and the suggestions, and judges the decisions the user
 * submitted. What the decisions DO at commit time is RequestRowPersister's job.
 */
final class RequestValueMappings
{
    public const string STATUS_FIELD = 'workflow_status';

    public const string SOURCE_FIELD = 'source_name';

    private const int STATUS_NAME_MAX = 100;

    public function __construct(private readonly RequestWorkflowStatuses $workflows) {}

    /**
     * @param  array<string, array{column_key: string, values: array<int, array{value: string, count: int}>}>  $mappedColumns
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, array{field: string, column_key: string, allow_create: bool, options: array<int, array{id: int, name: string, group: ?string}>, values: array<int, array{value: string, count: int, suggested_id: ?int}>}>
     */
    public function preview(array $mappedColumns, array $globalConfig): array
    {
        $entries = [];

        foreach ([self::STATUS_FIELD, self::SOURCE_FIELD] as $field) {
            if (! isset($mappedColumns[$field])) {
                continue;
            }

            $options = $this->options($field, $globalConfig);
            $suggest = $this->suggester($field, $options, $globalConfig);

            $entries[] = [
                'field' => $field,
                'column_key' => $mappedColumns[$field]['column_key'],
                'allow_create' => $field === self::STATUS_FIELD,
                'options' => array_map(static fn (array $option): array => ['id' => $option['id'], 'name' => $option['name'], 'group' => $option['group']], $options),
                'values' => array_map(
                    static fn (array $value): array => [...$value, 'suggested_id' => $suggest($value['value'])],
                    $mappedColumns[$field]['values'],
                ),
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, array{column_key: string, values: array<int, array{value: string, count: int}>}>  $mappedColumns
     * @param  array<string, mixed>  $valueMappings
     * @param  array<string, mixed>  $globalConfig
     * @return array<string, array<int, string>>
     */
    public function errors(array $mappedColumns, array $valueMappings, array $globalConfig): array
    {
        $errors = [];

        foreach ([self::STATUS_FIELD, self::SOURCE_FIELD] as $field) {
            if (! isset($mappedColumns[$field])) {
                continue;
            }

            $options = $this->options($field, $globalConfig);
            $decisions = $this->decisionsByValue((array) ($valueMappings[$field] ?? []));

            foreach ($mappedColumns[$field]['values'] as $value) {
                $message = $this->decisionError($field, $decisions[ImportValueMappings::normalize($value['value'])] ?? null, $options, $value['value']);

                if ($message !== null) {
                    $errors["value_mappings.{$field}"][] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * The category the run is configured on, or a 422 keyed on the global
     * field: the status options are meaningless without it.
     *
     * @param  array<string, mixed>  $globalConfig
     */
    public function selectableCategoryId(array $globalConfig): int
    {
        $id = (int) ($globalConfig['product_category_id'] ?? 0);

        $selectable = $id > 0 && ProductCategory::query()
            ->whereKey($id)->where('is_selectable', true)->where('is_active', true)->exists();

        if (! $selectable) {
            throw ValidationException::withMessages([
                'global_config.product_category_id' => ['Choose the product line before mapping the statuses.'],
            ]);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, array{id: int, name: string, group: ?string, system_key: ?string}>
     */
    private function options(string $field, array $globalConfig): array
    {
        if ($field === self::SOURCE_FIELD) {
            return Source::query()->orderBy('name')->get(['id', 'name'])
                ->map(static fn (Source $source): array => ['id' => $source->id, 'name' => $source->name, 'group' => null, 'system_key' => null])
                ->all();
        }

        return $this->workflows
            ->statuses($this->selectableCategoryId($globalConfig), $this->sourceId($globalConfig))
            ->map(static fn (QuoteWorkflowStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'group' => $status->group->value,
                'system_key' => $status->system_key,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id: int, name: string, group: ?string, system_key: ?string}>  $options
     * @param  array<string, mixed>  $globalConfig
     * @return callable(string): ?int
     */
    private function suggester(string $field, array $options, array $globalConfig): callable
    {
        $aliases = $this->aliases($field === self::STATUS_FIELD ? 'status_aliases' : 'source_aliases');
        $idByName = [];

        foreach ($options as $option) {
            $idByName[ImportValueMappings::normalize($option['name'])] ??= $option['id'];
        }

        $openId = collect($options)->firstWhere('system_key', WorkflowStatusSystemKey::Open->value)['id'] ?? null;
        $fallbackId = $field === self::SOURCE_FIELD && ! empty($globalConfig['source_id']) ? (int) $globalConfig['source_id'] : null;

        return static function (string $value) use ($field, $idByName, $aliases, $openId, $fallbackId): ?int {
            $key = ImportValueMappings::normalize($value);

            if ($key === '') {
                return $field === self::STATUS_FIELD ? $openId : $fallbackId;
            }

            $target = $aliases[$key] ?? $key;

            return $idByName[$target] ?? $idByName[$key] ?? $fallbackId;
        };
    }

    /**
     * @return array<string, string> normalized file value => normalized target name
     */
    private function aliases(string $configKey): array
    {
        $aliases = [];

        foreach ((array) config("imports.request_management.{$configKey}") as $from => $to) {
            $aliases[ImportValueMappings::normalize((string) $from)] = ImportValueMappings::normalize((string) $to);
        }

        return $aliases;
    }

    /**
     * @param  array<string, mixed>  $decisions
     * @return array<string, mixed>
     */
    private function decisionsByValue(array $decisions): array
    {
        $byValue = [];

        foreach ($decisions as $value => $decision) {
            $byValue[ImportValueMappings::normalize((string) $value)] = $decision;
        }

        return $byValue;
    }

    /**
     * @param  array<int, array{id: int, name: string, group: ?string, system_key: ?string}>  $options
     */
    private function decisionError(string $field, mixed $decision, array $options, string $value): ?string
    {
        if (! is_array($decision)) {
            return "No mapping for the value [{$value}].";
        }

        if (isset($decision['id'])) {
            return in_array((int) $decision['id'], array_column($options, 'id'), true)
                ? null
                : "The mapping of [{$value}] points to a record that is not selectable here.";
        }

        if (! isset($decision['create']) || $field !== self::STATUS_FIELD) {
            return "The mapping of [{$value}] must pick an existing record".($field === self::STATUS_FIELD ? ' or create a status.' : '.');
        }

        return $this->createError((array) $decision['create'], $options, $value);
    }

    /**
     * @param  array<string, mixed>  $create
     * @param  array<int, array{id: int, name: string, group: ?string, system_key: ?string}>  $options
     */
    private function createError(array $create, array $options, string $value): ?string
    {
        $name = trim((string) ($create['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > self::STATUS_NAME_MAX) {
            return "The new status for [{$value}] needs a name of 1 to ".self::STATUS_NAME_MAX.' characters.';
        }

        if (WorkflowStatusGroup::tryFrom((string) ($create['group'] ?? '')) === null) {
            return "The new status [{$name}] needs a valid group.";
        }

        $taken = in_array(ImportValueMappings::normalize($name), array_map(
            static fn (array $option): string => ImportValueMappings::normalize($option['name']),
            $options,
        ), true);

        return $taken ? "A status named [{$name}] already exists in the workflow." : null;
    }

    /**
     * @param  array<string, mixed>  $globalConfig
     */
    private function sourceId(array $globalConfig): ?int
    {
        return empty($globalConfig['source_id']) ? null : (int) $globalConfig['source_id'];
    }
}
