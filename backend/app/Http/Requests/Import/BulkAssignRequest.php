<?php

namespace App\Http\Requests\Import;

use App\Enums\LeadAssignmentMode;
use App\Imports\Leads\LeadImportProductCoherence;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates PATCH /api/imports/{domain}/{importRun}/rows/assign (spec 0045
 * bulk increment, extended to a COMBINED operator+site assignment, then to a
 * `mode` — spec 0048): bulk-assign an operator and/or an operational site to
 * a batch of staged rows — distinct from the single-row PATCH .../rows/{row}.
 * AG Grid `getServerSideSelectionState()` semantics: `row_ids` are the rows to
 * TARGET when `select_all` is false (required, non-empty), the rows to
 * EXCLUDE when `select_all` is true (optional, empty = every row in the
 * run). Bulk-only ASSIGNS — `operator_id`/`operational_site_id` are never
 * nullable; clearing a single row's override stays PATCH .../rows/{row}.
 *
 * `mode` (spec 0048) is OPTIONAL and purely ADDITIVE: when absent, the
 * original rule stands unchanged — at least one of operator_id/
 * operational_site_id required (validateAtLeastOneAssignment), neither
 * individually mandatory (AC-020, full retro-compat with every pre-0048
 * caller). When the caller opts into `mode` explicitly, it tightens the
 * contract for the unified "Assegna operatori" popup: `single` requires
 * operator_id (assign every targeted row to that one operator); `balanced`
 * requires operational_site_id (needed to enumerate the Sede's operators —
 * LeadOperatorDistributor, same algorithm as POST /leads/assign-operators).
 *
 * `row_ids` existence is checked SCOPED to the bound {importRun} (a plain
 * `exists:import_run_rows,id` would let an id from ANOTHER run through —
 * an IDOR/cross-run leak), mirroring UpdateImportRowRequest's own allow-list
 * discipline. Ownership/authorization/the run's `reviewing` status guard are
 * NOT handled here — they stay in the controller, same convention as every
 * other Import* request.
 *
 * `product_ids` (spec 0094 increment): OPTIONAL, purely ADDITIVE bulk
 * "Prodotti di interesse" assignment, combinable freely with
 * `operator_id`/`operational_site_id`/`mode`. Bulk-only ASSIGNS, mirroring
 * `operator_id`/`operational_site_id`: never `null`, never `[]` (`min:1`) —
 * clearing/emptying a single row's override stays PATCH .../rows/{row}.
 * Every submitted id must sit inside the effective product categories of the
 * run's `global_config.campaign_id`, checked via the SAME
 * `LeadImportProductCoherence` service `UpdateImportRowRequest` already
 * applies to the per-row override — never re-implemented here.
 */
class BulkAssignRequest extends FormRequest
{
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
            'operator_id' => ['required_if:mode,single', 'integer', 'exists:users,id'],
            'operational_site_id' => ['required_if:mode,balanced', 'integer', 'exists:operational_sites,id'],
            'product_ids' => ['sometimes', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'mode' => ['sometimes', Rule::enum(LeadAssignmentMode::class)],
            'select_all' => ['nullable', 'boolean'],
            'row_ids' => ['array'],
            'row_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAtLeastOneAssignment($validator);
            $this->validateRowIdsRequiredWhenNotSelectAll($validator);
            $this->validateRowIdsBelongToRun($validator);
            $this->validateProductIdsCoverage($validator);
        });
    }

    private function validateAtLeastOneAssignment(Validator $validator): void
    {
        if ($this->has('operator_id') || $this->has('operational_site_id') || $this->has('product_ids')) {
            return;
        }

        $validator->errors()->add('operator_id', 'At least one of operator_id, operational_site_id or product_ids is required.');
    }

    /**
     * Bulk-only mirror of UpdateImportRowRequest::validateProductIdsCoverage()
     * (AC-054's per-row coherence rule): every submitted product must sit
     * inside the effective product categories of the run's own
     * `global_config.campaign_id` — same `LeadImportProductCoherence`
     * service, never re-implemented here. `[]` is already rejected by the
     * `min:1` rule, so only a non-empty array reaches this check.
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

    private function validateRowIdsRequiredWhenNotSelectAll(Validator $validator): void
    {
        if ($this->selectAll() || $this->rowIds() !== []) {
            return;
        }

        $validator->errors()->add('row_ids', 'row_ids is required when select_all is false.');
    }

    /**
     * Anti-IDOR: every submitted id must belong to THIS run — a plain
     * `exists:import_run_rows,id` rule would accept an id from another
     * user's/domain's run.
     */
    private function validateRowIdsBelongToRun(Validator $validator): void
    {
        $rowIds = $this->rowIds();

        if ($rowIds === []) {
            return;
        }

        $importRun = $this->route('importRun');

        if (! $importRun instanceof ImportRun) {
            return;
        }

        $matchedCount = ImportRunRow::query()
            ->where('import_run_id', $importRun->id)
            ->whereIn('id', $rowIds)
            ->count();

        if ($matchedCount !== count(array_unique($rowIds))) {
            $validator->errors()->add('row_ids', 'One or more row_ids do not belong to this import run.');
        }
    }

    public function selectAll(): bool
    {
        return $this->boolean('select_all', false);
    }

    /**
     * @return array<int, int>
     */
    public function rowIds(): array
    {
        $rowIds = $this->input('row_ids', []);

        return is_array($rowIds) ? array_map('intval', $rowIds) : [];
    }

    public function operatorId(): ?int
    {
        return $this->has('operator_id') ? (int) $this->input('operator_id') : null;
    }

    public function operationalSiteId(): ?int
    {
        return $this->has('operational_site_id') ? (int) $this->input('operational_site_id') : null;
    }

    /**
     * @return array<int, int>|null null when `product_ids` was not submitted
     */
    public function productIds(): ?array
    {
        if (! $this->has('product_ids')) {
            return null;
        }

        return array_map('intval', (array) $this->input('product_ids'));
    }

    /**
     * Defaults to `single` when the caller omits `mode` entirely — every
     * pre-0048 caller (AC-020 retro-compat).
     */
    public function mode(): LeadAssignmentMode
    {
        return $this->has('mode') ? LeadAssignmentMode::from((string) $this->input('mode')) : LeadAssignmentMode::Single;
    }
}
