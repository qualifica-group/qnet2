<?php

namespace App\Http\Requests\Import;

use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\Leads\LeadImportProductCoherence;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\City;
use App\Models\ImportRun;
use App\Models\Province;
use App\Models\State;
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
 * incoherent pin never reaches StagedRowReviser.
 *
 * `operator_id`/`operational_site_id` (spec 0045, the latter mirrored): the
 * per-row Operator/Operational Site overrides — both nullable (a present,
 * explicitly-null value CLEARS the override back to the run's global value).
 * Presence is checked manually in withValidator() (not via
 * `required_without_all`), because the built-in rule cannot tell "submitted
 * as null to clear" apart from "not submitted at all".
 *
 * `product_ids` (spec 0094, D-4/AC-054): the per-row "Prodotti di interesse"
 * override, mirroring operator_id/operational_site_id's three-state
 * semantics but for an ARRAY — not submitted = row untouched, `null` =
 * explicitly revert to inheriting the run's global `product_ids`, `[]` =
 * this row carries none. Every submitted id must exist AND sit inside the
 * effective product categories of the RUN's `global_config.campaign_id`
 * (LeadImportProductCoherence — the same coherence rule
 * ConfigureImportRequest applies to the global value, never duplicated).
 *
 * The {domain}/{importRun} route segments resolve BEFORE any rule below runs
 * (unknown domain -> 404 via bootstrap/app.php; unknown/unbound importRun ->
 * 404 via route model binding), mirroring ConfigureImportRequest.
 * Authorization, run ownership and the `reviewing` status guard are NOT
 * handled here — they stay in the controller/Service.
 */
class UpdateImportRowRequest extends FormRequest
{
    /** The only keys `geo` accepts — any other one is rejected (AC-004). */
    private const array GEO_KEYS = ['country_id', 'state_id', 'province_id', 'city_id'];

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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAtLeastOneSubmitted($validator);
            $this->validateValuesAllowList($validator);
            $this->validateGeoKeys($validator);
            $this->validateGeoHierarchy($validator);
            $this->validateProductIdsCoverage($validator);
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
        if ($this->has('values') || $this->has('geo') || $this->has('operator_id') || $this->has('operational_site_id') || $this->has('product_ids')) {
            return;
        }

        $validator->errors()->add('values', 'At least one of values, geo, operator_id, operational_site_id or product_ids is required.');
    }

    /**
     * AC-054: a submitted, non-empty `product_ids` must sit inside the
     * effective product categories of the RUN's own `global_config.
     * campaign_id` — `null` (revert to the global default) and `[]`
     * (explicitly none) both need no coverage check.
     */
    private function validateProductIdsCoverage(Validator $validator): void
    {
        $productIds = $this->input('product_ids');

        if (! is_array($productIds) || $productIds === []) {
            return;
        }

        $importRun = $this->route('importRun');
        $campaignId = $importRun instanceof ImportRun ? ($importRun->global_config['campaign_id'] ?? null) : null;

        $coherence = app(LeadImportProductCoherence::class);
        $offending = $coherence->offendingProducts(
            $campaignId === null ? null : (int) $campaignId,
            array_map(static fn (mixed $id): int => (int) $id, $productIds),
        );

        if ($offending !== []) {
            $validator->errors()->add('product_ids', $coherence->message($offending));
        }
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

    private function validateGeoKeys(Validator $validator): void
    {
        $geo = $this->input('geo');

        if (! is_array($geo)) {
            return;
        }

        foreach (array_keys($geo) as $key) {
            if (! in_array($key, self::GEO_KEYS, true)) {
                $validator->errors()->add("geo.{$key}", "The field [geo.{$key}] is not a recognized geo level.");
            }
        }
    }

    /**
     * A child id is only coherent when its declared parent id resolves to
     * the SAME ancestor the child actually belongs to (spec 0038 AC-003) —
     * the province is an optional level: a city with no province instead
     * agrees directly with its state (mirroring GeoResolver's own
     * province-optional scoping).
     */
    private function validateGeoHierarchy(Validator $validator): void
    {
        $geo = $this->input('geo');

        if (! is_array($geo)) {
            return;
        }

        $countryId = $geo['country_id'] ?? null;
        $stateId = $geo['state_id'] ?? null;
        $provinceId = $geo['province_id'] ?? null;
        $cityId = $geo['city_id'] ?? null;

        if ($stateId !== null) {
            if ($countryId === null) {
                $validator->errors()->add('geo.state_id', 'A state requires a country.');
            } elseif (($state = State::find($stateId)) !== null && $state->country_id !== (int) $countryId) {
                $validator->errors()->add('geo.state_id', 'The selected state does not belong to the given country.');
            }
        }

        if ($provinceId !== null) {
            if ($stateId === null) {
                $validator->errors()->add('geo.province_id', 'A province requires a state.');
            } elseif (($province = Province::find($provinceId)) !== null && $province->state_id !== (int) $stateId) {
                $validator->errors()->add('geo.province_id', 'The selected province does not belong to the given state.');
            }
        }

        if ($cityId === null) {
            return;
        }

        if ($stateId === null) {
            $validator->errors()->add('geo.city_id', 'A city requires a state.');

            return;
        }

        $city = City::find($cityId);

        if ($city === null) {
            return;
        }

        $belongs = $provinceId !== null ? $city->province_id === (int) $provinceId : $city->state_id === (int) $stateId;

        if (! $belongs) {
            $validator->errors()->add('geo.city_id', 'The selected city does not belong to the given province/state.');
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
