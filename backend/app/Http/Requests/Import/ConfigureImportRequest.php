<?php

namespace App\Http\Requests\Import;

use App\Enums\ImportDedupMode;
use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\Leads\LeadImportProductCoherence;
use App\Imports\Staging\StagedRowBuilder;
use App\Imports\Support\ColumnAnalysis;
use App\Models\ImportRun;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates PUT /api/imports/{domain}/{importRun}/configure (spec 0033): the
 * wizard's configuration step (column mapping + global config + dedup
 * strategy), checked against the resolved ImportDefinition's OWN catalogue —
 * never a hardcoded list. `column_mapping` KEYS are an allow-list of the
 * bound run's OWN detected column keys (ColumnAnalysis::columnKeys() over
 * `detected_columns` — the deterministic "bare name, or name#index on a
 * later duplicate" key, never the raw column name: two identically-named
 * file columns must never collapse onto the same mapping entry); VALUES are
 * an allow-list of `fields()` ids plus `__ignore__` (always) and `__extra__`
 * (only when supportsExtraFields()). Required fields() and required
 * globalConfig() entries must be covered; `dedup_strategy` must be one of
 * dedupModes().
 *
 * The {domain} route segment resolves an UNKNOWN domain to a
 * ModelNotFoundException (-> 404, see bootstrap/app.php) BEFORE any rule
 * below runs, mirroring TableRowsRequest. Authorization and the run's
 * current-status guard (`configuring`) are NOT handled here — they stay in
 * the controller/Service, same convention as every other Import* request.
 */
class ConfigureImportRequest extends FormRequest
{
    private ?ImportDefinition $resolvedDefinition = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'column_mapping' => ['required', 'array'],
            'column_mapping.*' => ['required', 'string'],
            // `global_config` deliberately carries NO per-key dot-rule here:
            // its shape is domain-defined (campaign_id/source_id/product_ids/
            // ...), and Laravel's `excludeUnvalidatedArrayKeys` (default on)
            // drops the WHOLE bulk array from validated() the moment ANY
            // `global_config.<key>` rule exists but not every key has one —
            // `campaign_id`/`source_id` would silently vanish from
            // `$request->safe()->only(['global_config'])`. `product_ids`'s
            // own shape/existence/coherence checks therefore run manually in
            // assertGlobalProductIdsCoherent() below, never as a rule.
            'global_config' => ['sometimes', 'array'],
            'dedup_strategy' => ['required', 'string', Rule::in($this->allowedDedupValues())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $definition = $this->definition();

            $this->assertMappingKeysAndTargetsAllowed($validator, $definition);
            $this->assertRequiredFieldsMapped($validator, $definition);
            $this->assertRequiredGlobalConfigPresent($validator, $definition);
            $this->assertMappedModeExcludesGlobals($validator, $definition);
            $this->assertGlobalProductIdsCoherent($validator, $definition);
        });
    }

    private function assertMappingKeysAndTargetsAllowed(Validator $validator, ImportDefinition $definition): void
    {
        $mapping = $this->input('column_mapping');

        if (! is_array($mapping)) {
            return;
        }

        $allowedKeys = $this->allowedColumnKeys();
        $allowedTargets = $this->allowedMappingTargets($definition);

        foreach ($mapping as $columnKey => $target) {
            if (! in_array((string) $columnKey, $allowedKeys, true)) {
                $validator->errors()->add(
                    "column_mapping.{$columnKey}",
                    "The column [{$columnKey}] is not part of this run's detected columns.",
                );

                continue;
            }

            if (! is_string($target) || ! in_array($target, $allowedTargets, true)) {
                $validator->errors()->add(
                    "column_mapping.{$columnKey}",
                    "The mapping target for column [{$columnKey}] is not allowed.",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedColumnKeys(): array
    {
        $importRun = $this->route('importRun');
        $columns = $importRun instanceof ImportRun ? ($importRun->detected_columns ?? []) : [];

        return ColumnAnalysis::columnKeys($columns);
    }

    private function assertRequiredFieldsMapped(Validator $validator, ImportDefinition $definition): void
    {
        $mapping = $this->input('column_mapping');
        $mappedTargets = is_array($mapping) ? array_values($mapping) : [];

        foreach ($definition->fields() as $field) {
            if (($field['required'] ?? false) && ! in_array($field['id'], $mappedTargets, true)) {
                $validator->errors()->add('column_mapping', "Required field [{$field['id']}] is not mapped.");
            }
        }
    }

    private function assertRequiredGlobalConfigPresent(Validator $validator, ImportDefinition $definition): void
    {
        $globalConfig = $this->input('global_config', []);

        foreach ($definition->globalConfig() as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }

            // Spec 0108 (D-2): a global field whose `required_unless_mapped`
            // field IS mapped takes its value from that file column, per row —
            // the run-wide value is then not just optional but forbidden (see
            // assertMappedModeExcludesGlobals below).
            if ($this->isMapped($field['required_unless_mapped'] ?? null)) {
                continue;
            }

            $value = is_array($globalConfig) ? ($globalConfig[$field['id']] ?? null) : null;

            // AC-052: an empty array is as absent as null/'' for a required
            // field — a `multiple` field left unset submits `[]`, not a blank
            // scalar, and must be caught the same way.
            if ($value === null || $value === '' || $value === []) {
                $validator->errors()->add("global_config.{$field['id']}", "The [{$field['id']}] global field is required.");
            }
        }
    }

    /**
     * Spec 0108 (D-2/D-6), the mirror of the rule above: once a file column
     * feeds a global field per row, that field's run-wide value is rejected
     * instead of silently ignored — and so is any global field DEPENDING on
     * it (`depends_on`), which has nothing left to be validated against: on
     * `leads`, mapping `campaign_code` rules out both `global_config.
     * campaign_id` and `global_config.product_ids`. Both rules read the
     * catalogue's own descriptors, never a hardcoded field pair.
     */
    private function assertMappedModeExcludesGlobals(Validator $validator, ImportDefinition $definition): void
    {
        $globalFields = $definition->globalConfig();

        foreach ($globalFields as $field) {
            $mappedField = $field['required_unless_mapped'] ?? null;

            if (! $this->isMapped($mappedField)) {
                continue;
            }

            if ($this->hasGlobalValue($field['id'])) {
                $validator->errors()->add(
                    "global_config.{$field['id']}",
                    "The [{$field['id']}] global field cannot be set: [{$mappedField}] is mapped from a file column, so each row carries its own value.",
                );
            }

            foreach ($globalFields as $dependent) {
                if (($dependent['depends_on'] ?? null) === $field['id'] && $this->hasGlobalValue($dependent['id'])) {
                    $validator->errors()->add(
                        "global_config.{$dependent['id']}",
                        "The [{$dependent['id']}] global field cannot be set: it depends on [{$field['id']}], which varies per row when [{$mappedField}] is mapped from a file column.",
                    );
                }
            }
        }
    }

    /** Whether a file column is currently mapped onto the given field id (null = no such field). */
    private function isMapped(?string $fieldId): bool
    {
        if ($fieldId === null) {
            return false;
        }

        $mapping = $this->input('column_mapping');

        return is_array($mapping) && in_array($fieldId, array_values($mapping), true);
    }

    /** Whether `global_config.<id>` carries an actual value — `[]` counts as absent, as in AC-052. */
    private function hasGlobalValue(string $fieldId): bool
    {
        $globalConfig = $this->input('global_config', []);
        $value = is_array($globalConfig) ? ($globalConfig[$fieldId] ?? null) : null;

        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * AC-051: `global_config.product_ids` must be an array of EXISTING
     * product ids, each sitting inside the effective product categories of
     * the campaign chosen in the SAME payload (`global_config`'s field its
     * `depends_on` names) — resolved via LeadImportProductCoherence, never a
     * second copy of the coherence rule. Both checks run manually (never as
     * a `rules()` entry, see that method's docblock) and a definition with
     * no `product_ids` global field (every domain but `leads`, today) skips
     * silently.
     */
    private function assertGlobalProductIdsCoherent(Validator $validator, ImportDefinition $definition): void
    {
        $productField = collect($definition->globalConfig())->firstWhere('id', 'product_ids');

        if ($productField === null) {
            return;
        }

        $globalConfig = $this->input('global_config', []);
        $productIds = is_array($globalConfig) ? ($globalConfig['product_ids'] ?? null) : null;

        if ($productIds === null) {
            return;
        }

        if (! is_array($productIds)) {
            $validator->errors()->add('global_config.product_ids', 'The global_config.product_ids field must be an array.');

            return;
        }

        if ($productIds === []) {
            return;
        }

        $normalizedIds = array_map(static fn (mixed $id): int => (int) $id, $productIds);

        if (Product::query()->whereIn('id', $normalizedIds)->count() !== count(array_unique($normalizedIds))) {
            $validator->errors()->add('global_config.product_ids', 'One of the selected products does not exist.');

            return;
        }

        $campaignFieldId = $productField['depends_on'] ?? null;
        $campaignId = $campaignFieldId !== null ? ($globalConfig[$campaignFieldId] ?? null) : null;

        $coherence = app(LeadImportProductCoherence::class);
        $offending = $coherence->offendingProducts($campaignId === null ? null : (int) $campaignId, $normalizedIds);

        if ($offending !== []) {
            $validator->errors()->add('global_config.product_ids', $coherence->message($offending));
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedMappingTargets(ImportDefinition $definition): array
    {
        $targets = [
            StagedRowBuilder::IGNORE_TARGET,
            ...array_map(static fn (array $field): string => $field['id'], $definition->fields()),
        ];

        if ($definition->supportsExtraFields()) {
            $targets[] = StagedRowBuilder::EXTRA_TARGET;
        }

        return $targets;
    }

    /**
     * @return array<int, string>
     */
    private function allowedDedupValues(): array
    {
        return array_map(
            static fn (ImportDedupMode $mode): string => $mode->value,
            $this->definition()->dedupModes(),
        );
    }

    private function definition(): ImportDefinition
    {
        if ($this->resolvedDefinition === null) {
            $this->resolvedDefinition = app(ImportRegistry::class)->resolve((string) $this->route('domain'));
        }

        return $this->resolvedDefinition;
    }
}
