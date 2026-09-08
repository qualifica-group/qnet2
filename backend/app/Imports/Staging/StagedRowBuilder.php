<?php

namespace App\Imports\Staging;

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Imports\ImportDefinition;
use App\Imports\ImportRowContext;
use App\Imports\Recognition\RowRecognizer;
use App\Models\User;

/**
 * Turns ONE raw file row into a StageOutcome (spec 0033): applies the run's
 * `column_mapping` (column key -> field id | __ignore__ | __extra__), runs
 * the definition's recognizers(), validates the mapped values, then resolves
 * the row's status from resolveDuplicate() + the run's dedup strategy. A
 * fresh instance is constructed per job run (mirrors ImportRowProcessor), so
 * it carries no cross-row state.
 */
final class StagedRowBuilder
{
    /** Column mapping target meaning "do not import this file column". */
    public const string IGNORE_TARGET = '__ignore__';

    /** Column mapping target meaning "store this file column verbatim as an extra field". */
    public const string EXTRA_TARGET = '__extra__';

    /**
     * @param  array<string, string>  $columnMapping  column key => field id | IGNORE_TARGET | EXTRA_TARGET
     * @param  array<string, mixed>  $globalConfig  the run's configuration-step values (e.g. leads' campaign_id), spec 0036
     */
    public function __construct(
        private readonly ImportDefinition $definition,
        private readonly User $actor,
        private readonly array $columnMapping,
        private readonly ImportDedupMode $dedupMode,
        private readonly array $globalConfig = [],
    ) {}

    /**
     * @param  array<string, string>  $rawValues  column key => raw file value
     */
    public function build(int $rowNumber, array $rawValues): StageOutcome
    {
        // Step 1: split the raw row into mapped field values and extra values.
        [$mappedValues, $extraValues] = $this->applyMapping($rawValues);

        return $this->resolve($rowNumber, $mappedValues, $extraValues === [] ? null : $extraValues);
    }

    /**
     * Runs recognizers -> placeholder -> validateRow -> resolveDuplicate on
     * an ALREADY mapped value set (spec 0033 delta
     * D-2026-07-15-placeholder-review-fields): build() calls this right
     * after applying a fresh row's column_mapping; StagedRowReviser calls it
     * directly on an edited row's merged mapped values, with no raw file
     * columns to reconstruct — a recognizer-derived field (e.g.
     * first_name/last_name) has no raw column of its own.
     *
     * @param  array<string, mixed>  $mappedValues
     * @param  array<string, string>|null  $extraValues
     * @param  array<int, class-string>  $skipRecognizers  recognizers the caller has already
     *                                                     superseded with an authoritative pin: GeoRecognizer when
     *                                                     StagedRowReviser pinned country/region/province/city via
     *                                                     GeoPinResolver (spec 0038), CampaignRecognizer when it
     *                                                     pinned the row's campaign via CampaignPinResolver (spec
     *                                                     0108). Re-running them would redo — and could contradict —
     *                                                     a choice the operator already made explicit.
     */
    public function resolve(int $rowNumber, array $mappedValues, ?array $extraValues, array $skipRecognizers = []): StageOutcome
    {
        // Step 2: run the definition's recognizers, merging resolved values
        // into BOTH the mapped values (so validateRow/persistRow see them
        // directly) and the row's own `resolved` record (for the review UI).
        $context = new ImportRowContext($rowNumber, $this->actor);
        [$resolved, $messages, $needsReview] = $this->runRecognizers($context, $mappedValues, $skipRecognizers);
        $mappedValues = [...$mappedValues, ...$resolved];

        // Step 2.5: any field still blank but required for creation defaults
        // to the configured placeholder, flagging the row for review instead
        // of rejecting it outright — the placeholder lands in mappedValues,
        // so it is persisted and editable in the review grid.
        [$mappedValues, $placeholderMessages, $placeholderApplied] = $this->applyPlaceholders($mappedValues);
        $messages = [...$messages, ...$placeholderMessages];
        $needsReview = $needsReview || $placeholderApplied;

        // Step 3: field-level validation rejects the row regardless of dedup.
        $errors = $this->definition->validateRow($mappedValues, $context);

        if ($errors !== []) {
            return new StageOutcome(
                mappedValues: $mappedValues,
                extraValues: $extraValues,
                resolved: $resolved === [] ? null : $resolved,
                status: ImportRowStatus::Error,
                messages: [...$messages, ...$errors],
                duplicateOfId: null,
                duplicateMeta: null,
            );
        }

        // Step 4: resolve the row's duplicate id + review-facing meta (spec
        // 0036), then derive its status from the id.
        $duplicateMatch = $this->definition->resolveDuplicateMatch($mappedValues, $this->globalConfig);

        return new StageOutcome(
            mappedValues: $mappedValues,
            extraValues: $extraValues,
            resolved: $resolved === [] ? null : $resolved,
            status: $this->resolveStatus($duplicateMatch['id'], $needsReview),
            messages: $messages === [] ? null : $messages,
            duplicateOfId: $duplicateMatch['id'],
            duplicateMeta: $duplicateMatch['meta'],
        );
    }

    /**
     * @param  array<string, string>  $rawValues
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function applyMapping(array $rawValues): array
    {
        $mapped = [];
        $extra = [];

        foreach ($this->columnMapping as $columnKey => $target) {
            if (! array_key_exists($columnKey, $rawValues)) {
                continue;
            }

            $value = $rawValues[$columnKey];

            match ($target) {
                self::IGNORE_TARGET => null,
                self::EXTRA_TARGET => $extra[$columnKey] = $value,
                default => $mapped[$target] = $value,
            };
        }

        return [$mapped, $extra];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, class-string>  $skipRecognizers
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: bool}
     */
    private function runRecognizers(ImportRowContext $context, array $mapped, array $skipRecognizers = []): array
    {
        $resolved = [];
        $messages = [];
        $needsReview = false;

        foreach ($this->definition->recognizers() as $recognizerClass) {
            if (in_array($recognizerClass, $skipRecognizers, true)) {
                continue;
            }

            /** @var RowRecognizer $recognizer */
            $recognizer = app($recognizerClass);
            $result = $recognizer->recognize($context, [...$mapped, ...$resolved]);

            $resolved = [...$resolved, ...$result->resolved];
            $messages = [...$messages, ...$result->messages];
            $needsReview = $needsReview || $result->needsReview;
        }

        return [$resolved, $messages, $needsReview];
    }

    /**
     * Defaults every still-blank `requiredForCreation()` field to
     * `config('imports.placeholder')` (spec 0033 delta
     * D-2026-07-15-placeholder-review-fields), so a row is never silently
     * rejected for a missing identity field — it surfaces as an editable
     * warning instead.
     *
     * @param  array<string, mixed>  $mapped
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: bool}
     */
    private function applyPlaceholders(array $mapped): array
    {
        $messages = [];
        $applied = false;
        $placeholder = (string) config('imports.placeholder');

        foreach ($this->definition->requiredForCreation() as $fieldId) {
            if (trim((string) ($mapped[$fieldId] ?? '')) !== '') {
                continue;
            }

            $mapped[$fieldId] = $placeholder;
            $messages[] = "{$fieldId} was empty and defaulted to {$placeholder}; review it.";
            $applied = true;
        }

        return [$mapped, $messages, $applied];
    }

    private function resolveStatus(?int $duplicateOfId, bool $needsReview): ImportRowStatus
    {
        if ($duplicateOfId !== null) {
            return match ($this->dedupMode) {
                ImportDedupMode::Ignore => ImportRowStatus::Skipped,
                ImportDedupMode::Manual => ImportRowStatus::Duplicate,
                ImportDedupMode::CreateNew, ImportDedupMode::UpdateExisting, ImportDedupMode::CreateOnly => ImportRowStatus::Valid,
            };
        }

        return $needsReview ? ImportRowStatus::Warning : ImportRowStatus::Valid;
    }
}
