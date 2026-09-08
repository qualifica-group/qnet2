<?php

namespace App\Http\Requests\Import;

use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\Leads\LeadImportProductCoherence;
use App\Imports\Leads\LeadRowCampaign;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Support\Import\GeoPinValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates PATCH /api/imports/{domain}/{importRun}/rows/{row} (spec 0033,
 * AC-017; extended by spec 0038 with the optional `geo` block): the inline
 * edit of ONE staged row's values, keyed by an allow-list built from the
 * bound run — the definition's `fields()` ids PLUS the file column keys
 * currently mapped to `__extra__` on THIS run's `column_mapping` (mirroring
 * how ImportRunRowResource/StagedRowBuilder key extra values by their
 * original column name, never a synthetic "extra field id"). Any other key
 * is rejected — the same allow-list discipline as ConfigureImportRequest.
 *
 * `geo` (spec 0038): at least one of `values`/`geo`/`operator_id` is
 * required. `geo` pins the 4 geo levels to authoritative ids (country_id/
 * state_id/province_id/city_id, all nullable) instead of letting the
 * reviser re-run the fuzzy GeoRecognizer — validated here for existence
 * (`exists:`) and hierarchical coherence (a child id must actually belong to
 * its declared parent, the same rule GeoSelect enforces client-side), so an
 * incoherent pin never reaches StagedRowReviser — those two checks live in
 * App\Support\Import\GeoPinValidator, keeping this class under the 300-line
 * soft limit (engineering.md §6).
 *
 * `operator_id`/`operational_site_id` (spec 0045, the latter mirrored): the
 * per-row Operator/Operational Site overrides — both nullable (a present,
 * explicitly-null value CLEARS the override back to the run's global value).
 * Presence is checked manually in withValidator() (not via
 * `required_without_all`), because the built-in rule cannot tell "submitted
 * as null to clear" apart from "not submitted at all".
 *
 * `campaign_id` (spec 0108, D-5): the campaign an operator pins on a row
 * whose file code did not match — accepted ONLY on a run that reads campaigns
 * from a file column (on a global-campaign run the campaign belongs to the
 * run, not to the row), nullable to unpin. Unlike the overrides above it is
 * not a plain column write: StagedRowReviser replays the staging pipeline, so
 * it can change the row's status/messages/duplicate.
 *
 * `product_ids` (spec 0094, D-4/AC-054): the per-row "Prodotti di interesse"
 * override, mirroring operator_id/operational_site_id's three-state
 * semantics but for an ARRAY — not submitted = row untouched, `null` =
 * explicitly revert to inheriting the run's global `product_ids`, `[]` =
 * this row carries none. Every submitted id must exist AND sit inside the
 * effective product categories of the ROW's campaign (LeadImportProductCoherence
 * — the same coherence rule ConfigureImportRequest applies to the global
 * value, never duplicated).
 *
 * The {domain}/{importRun} route segments resolve BEFORE any rule below runs
 * (unknown domain -> 404 via bootstrap/app.php; unknown/unbound importRun ->
 * 404 via route model binding), mirroring ConfigureImportRequest.
 * Authorization, run ownership and the `reviewing` status guard are NOT
 * handled here — they stay in the controller/Service.
 */
class UpdateImportRowRequest extends FormRequest
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
            'values' => ['sometimes', 'array'],
            'values.*' => ['nullable', 'string'],
            'geo' => ['sometimes', 'array'],
            'geo.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'geo.state_id' => ['nullable', 'integer', 'exists:states,id'],
            'geo.province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'geo.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'operator_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', 'exists:operational_sites,id'],
            'product_ids' => ['sometimes', 'nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'campaign_id' => ['sometimes', 'nullable', 'integer', 'exists:campaigns,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAtLeastOneSubmitted($validator);
            $this->validateValuesAllowList($validator);
            $this->geoPinValidator()->validate($validator, $this->input('geo'));
            $this->validateProductIdsCoverage($validator);
            $this->validateCampaignIsPerRow($validator);
        });
    }

    /**
     * `values`/`geo`/`operator_id`/`operational_site_id`/`product_ids`
     * replace the old `required_without` pair: an explicit `operator_id:
     * null` (clearing the override) must count as "submitted", which the
     * built-in required-family rules cannot express for a nullable field —
     * so presence is checked directly here instead.
     */
    private function validateAtLeastOneSubmitted(Validator $validator): void
    {
        if ($this->has('values') || $this->has('geo') || $this->has('operator_id') || $this->has('operational_site_id') || $this->has('product_ids') || $this->has('campaign_id')) {
            return;
        }

        $validator->errors()->add('values', 'At least one of values, geo, operator_id, operational_site_id, product_ids or campaign_id is required.');
    }

    /**
     * AC-054: a submitted, non-empty `product_ids` must sit inside the
     * effective product categories of THIS ROW's campaign — its own resolved
     * one on a run reading campaigns from a file column (spec 0108, D-6,
     * including a campaign pinned in the very same payload), the run's global
     * one otherwise. `null` (revert to the global default) and `[]`
     * (explicitly none) both need no coverage check.
     */
    private function validateProductIdsCoverage(Validator $validator): void
    {
        $productIds = $this->input('product_ids');

        if (! is_array($productIds) || $productIds === []) {
            return;
        }

        $coherence = app(LeadImportProductCoherence::class);
        $offending = $coherence->offendingProducts(
            $this->rowCampaignId(),
            array_map(static fn (mixed $id): int => (int) $id, $productIds),
        );

        if ($offending !== []) {
            $validator->errors()->add('product_ids', $coherence->message($offending));
        }
    }

    /**
     * Spec 0108 (D-5): a row-level campaign only exists on a run whose
     * `column_mapping` feeds `campaign_code` from the file. On a global run
     * the campaign is the run's, and pinning one row would silently split a
     * run the rest of the pipeline still treats as single-campaign.
     */
    private function validateCampaignIsPerRow(Validator $validator): void
    {
        $importRun = $this->route('importRun');

        if (! $this->has('campaign_id') || ! $importRun instanceof ImportRun) {
            return;
        }

        if (! LeadRowCampaign::isPerRow($importRun->column_mapping ?? [])) {
            $validator->errors()->add('campaign_id', 'This import takes its campaign from the run configuration, not from a file column: a per-row campaign cannot be set.');
        }
    }

    private function geoPinValidator(): GeoPinValidator
    {
        return app(GeoPinValidator::class);
    }

    /** The campaign this row will end up on: a campaign pinned in this payload wins, then the row's own, then the run's global one. */
    private function rowCampaignId(): ?int
    {
        $pinned = $this->input('campaign_id');

        if ($pinned !== null) {
            return (int) $pinned;
        }

        $importRun = $this->route('importRun');
        $row = $this->route('row');

        return LeadRowCampaign::resolve(
            $row instanceof ImportRunRow ? ($row->mapped_values ?? []) : [],
            $importRun instanceof ImportRun ? ($importRun->global_config ?? []) : [],
        );
    }

    private function validateValuesAllowList(Validator $validator): void
    {
        $values = $this->input('values');

        if (! is_array($values)) {
            return;
        }

        $allowedKeys = $this->allowedValueKeys();

        foreach (array_keys($values) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                $validator->errors()->add(
                    "values.{$key}",
                    "The field [{$key}] is not mapped nor an extra column for this import.",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedValueKeys(): array
    {
        $fieldIds = array_map(
            static fn (array $field): string => $field['id'],
            $this->definition()->fields(),
        );

        $importRun = $this->route('importRun');
        $mapping = $importRun instanceof ImportRun ? ($importRun->column_mapping ?? []) : [];

        $extraKeys = array_keys(array_filter(
            $mapping,
            static fn (string $target): bool => $target === StagedRowBuilder::EXTRA_TARGET,
        ));

        return [...$fieldIds, ...$extraKeys];
    }

    private function definition(): ImportDefinition
    {
        if ($this->resolvedDefinition === null) {
            $this->resolvedDefinition = app(ImportRegistry::class)->resolve((string) $this->route('domain'));
        }

        return $this->resolvedDefinition;
    }
}
