<?php

namespace App\Support\Import;

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Imports\ImportDefinition;
use App\Imports\Recognition\CampaignRecognizer;
use App\Imports\Recognition\GeoRecognizer;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;

/**
 * Re-validates ONE staged row after an inline edit (PATCH .../rows/{row},
 * spec 0033 AC-017; extended by delta D-2026-07-15-placeholder-review-fields
 * and by spec 0038's optional `geo` pin) by re-running it through the SAME
 * StagedRowBuilder pipeline (recognizers -> placeholder -> validateRow ->
 * resolveDuplicate) StageImportJob used to stage it originally — never a
 * parallel/duplicated validation path.
 *
 * Unlike the original B5 implementation, this does NOT rebuild raw file
 * values by inverting column_mapping: the edited `values` are field-id-keyed
 * (the review grid edits the FINAL persisted fields, e.g. `first_name`/
 * `last_name`, not the raw `full_name` column they were split from — a
 * recognizer-derived field has no raw column of its own to reconstruct). It
 * merges the edited values directly onto the row's existing `mapped_values`/
 * `extra_values` and replays StagedRowBuilder::resolve() from there, so
 * recognizers skip fields the edit (or the original staging) already
 * populated and the placeholder only re-applies to a field still blank.
 *
 * `geo` (spec 0038): when present, GeoPinResolver turns the operator's 4
 * authoritative ids into canonical name + id mapped_values BEFORE the
 * pipeline runs, and the pipeline is told to skip GeoRecognizer — the pin IS
 * the resolution, not a hint to re-fuzzy-match.
 *
 * `operator_id`/`operational_site_id` (spec 0045, the latter mirrored): the
 * per-row Operator/Operational Site overrides never re-run the staging
 * pipeline on their own — they do not affect recognizers/validation/dedup,
 * only which operator/site owns the row at commit time — so a request
 * carrying ONLY these overrides short-circuits before StagedRowBuilder even
 * runs.
 *
 * `campaign_id` (spec 0108, D-5): NOT an override — the campaign drives
 * validation and duplicate matching, so pinning it goes through the full
 * replay like `geo` does, with CampaignRecognizer skipped (CampaignPinResolver
 * IS the resolution). An explicit `null` unpins instead: the file's own raw
 * code comes back into `mapped_values` and the recognizer re-decides, which
 * puts the row back in `error` when that code is still unusable.
 *
 * `product_ids` (spec 0094, D-4/AC-054): the per-row "Prodotti di interesse"
 * override mirrors the same short-circuit — it never touches recognizers/
 * validation/dedup either, only which products this row carries at commit
 * time (LeadRowPersister). Coverage against the campaign's categories is
 * already enforced by UpdateImportRowRequest before this class ever runs.
 */
final class StagedRowReviser
{
    public function __construct(
        private readonly GeoPinResolver $geoPinResolver,
        private readonly CampaignPinResolver $campaignPinResolver,
    ) {}

    /**
     * @param  array<string, string>|null  $editedValues  field id (or extra column key) => new value
     * @param  array{country_id: ?int, state_id: ?int, province_id: ?int, city_id: ?int}|null  $geo
     * @param  array<int, int>|null  $productIds  three-state: not submitted (see $productIdsSubmitted) / null (inherit global) / explicit set
     */
    public function revise(
        ImportDefinition $definition,
        User $actor,
        ImportRun $run,
        ImportRunRow $row,
        ?array $editedValues,
        ?array $geo = null,
        bool $operatorIdSubmitted = false,
        ?int $operatorId = null,
        bool $siteIdSubmitted = false,
        ?int $siteId = null,
        bool $productIdsSubmitted = false,
        ?array $productIds = null,
        bool $campaignIdSubmitted = false,
        ?int $campaignId = null,
    ): ImportRunRow {
        // Step 1: an operator/site/product_ids-only override never touches
        // staging/validation status — plain column write, no
        // StagedRowBuilder replay.
        if ($editedValues === null && $geo === null && ! $campaignIdSubmitted) {
            if ($operatorIdSubmitted || $siteIdSubmitted || $productIdsSubmitted) {
                $row->update([
                    ...($operatorIdSubmitted ? ['operator_id' => $operatorId] : []),
                    ...($siteIdSubmitted ? ['operational_site_id' => $siteId] : []),
                    ...($productIdsSubmitted ? ['product_ids' => $productIds] : []),
                    'is_edited' => true,
                ]);
            }

            return $row->fresh();
        }

        $columnMapping = $run->column_mapping ?? [];
        $dedupMode = ImportDedupMode::from($run->dedup_strategy ?? ImportDedupMode::CreateOnly->value);

        [$mappedValues, $extraValues] = $editedValues === null
            ? [$row->mapped_values ?? [], $row->extra_values]
            : $this->mergeEditedValues($row, $columnMapping, $editedValues);

        if ($geo !== null) {
            $mappedValues = [...$mappedValues, ...$this->geoPinResolver->pin($geo)];
        }

        $skipRecognizers = $geo !== null ? [GeoRecognizer::class] : [];

        if ($campaignIdSubmitted) {
            [$mappedValues, $campaignPinned] = $this->applyCampaignPin($row, $columnMapping, $mappedValues, $campaignId);

            if ($campaignPinned) {
                $skipRecognizers[] = CampaignRecognizer::class;
            }
        }

        $builder = new StagedRowBuilder($definition, $actor, $columnMapping, $dedupMode, $run->global_config ?? []);
        $outcome = $builder->resolve($row->row_number, $mappedValues, $extraValues, skipRecognizers: $skipRecognizers);

        $row->update([
            'mapped_values' => $outcome->mappedValues,
            'extra_values' => $outcome->extraValues,
            'resolved' => $outcome->resolved,
            'status' => $outcome->status,
            'messages' => $outcome->messages,
            'duplicate_of_id' => $outcome->duplicateOfId,
            'duplicate_meta' => $outcome->duplicateMeta,
            // spec 0036 AC-006: a match that disappears on edit (status no
            // longer `duplicate`) clears the operator's prior resolution too
            // — it no longer refers to a real match.
            'resolution' => $outcome->status === ImportRowStatus::Duplicate ? $row->resolution : null,
            'is_edited' => true,
            ...($operatorIdSubmitted ? ['operator_id' => $operatorId] : []),
            ...($siteIdSubmitted ? ['operational_site_id' => $siteId] : []),
            ...($productIdsSubmitted ? ['product_ids' => $productIds] : []),
        ]);

        return $row->fresh();
    }

    /**
     * Pins the operator's campaign choice onto the row's mapped values, or
     * unpins it (`$campaignId === null`): unpinning drops the resolved id and
     * restores the code the FILE carried for this row — `raw_values` keyed by
     * the column currently mapped to `campaign_code` — so CampaignRecognizer
     * decides again from the original input, exactly as at staging time.
     *
     * @param  array<string, string>  $columnMapping
     * @param  array<string, mixed>  $mappedValues
     * @return array{0: array<string, mixed>, 1: bool} the values, and whether the recognizer must be skipped
     */
    private function applyCampaignPin(ImportRunRow $row, array $columnMapping, array $mappedValues, ?int $campaignId): array
    {
        if ($campaignId !== null) {
            return [[...$mappedValues, ...$this->campaignPinResolver->pin($campaignId)], true];
        }

        unset($mappedValues[CampaignRecognizer::CAMPAIGN_ID_FIELD]);
        $mappedValues[CampaignRecognizer::CAMPAIGN_CODE_FIELD] = $this->rawCampaignCode($row, $columnMapping);

        return [$mappedValues, false];
    }

    /**
     * @param  array<string, string>  $columnMapping
     */
    private function rawCampaignCode(ImportRunRow $row, array $columnMapping): string
    {
        $rawValues = $row->raw_values ?? [];

        foreach ($columnMapping as $columnKey => $target) {
            if ($target === CampaignRecognizer::CAMPAIGN_CODE_FIELD) {
                return (string) ($rawValues[$columnKey] ?? '');
            }
        }

        return '';
    }

    /**
     * Overlays `editedValues` onto the row's current mapped/extra values: a
     * key naming a file column mapped to `__extra__` on this run goes to
     * extraValues (keyed by that same original column name, mirroring
     * StagedRowBuilder::applyMapping()); every other key is a field id and
     * goes to mappedValues.
     *
     * @param  array<string, string>  $columnMapping
     * @param  array<string, string>  $editedValues
     * @return array{0: array<string, mixed>, 1: array<string, string>|null}
     */
    private function mergeEditedValues(ImportRunRow $row, array $columnMapping, array $editedValues): array
    {
        $mappedValues = $row->mapped_values ?? [];
        $extraValues = $row->extra_values ?? [];

        $extraColumnKeys = array_keys(array_filter(
            $columnMapping,
            static fn (string $target): bool => $target === StagedRowBuilder::EXTRA_TARGET,
        ));

        foreach ($editedValues as $key => $value) {
            if (in_array($key, $extraColumnKeys, true)) {
                $extraValues[$key] = $value;

                continue;
            }

            $mappedValues[$key] = $value;
        }

        return [$mappedValues, $extraValues === [] ? null : $extraValues];
    }
}
