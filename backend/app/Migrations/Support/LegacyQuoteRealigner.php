<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Migrations\MigrationRowOutcome;
use App\Models\Quote;
use App\Services\Quotes\QuoteManagerWriter;
use App\Services\Quotes\RevenueProductTitleBuilder;
use App\Support\ManagerPositions;
use Carbon\CarbonImmutable;

/**
 * Realigns an already imported offer to the legacy record (spec 0372, D-4/D-5/D-8/D-9):
 * `title`, `quote_date`, `internal_notes`, `next_callback_at`, `supervisor_id`, managers,
 * flexible fields, status and contract. Never `code`, `opportunity_id`, the commercial
 * roles, site, company, payment method, source or lines (D-5). Quiet like the import:
 * the caller runs it without activity log; nothing notifies. An offer worked on in
 * qnet-2 is left alone, and an identical one costs no write.
 */
final class LegacyQuoteRealigner
{
    private const string TOUCHED_WARNING = 'Changed in qnet-2 after the import; not updated.';

    private const array CONFLICT_WARNINGS = [
        LegacyQuoteStatusApplier::CONTRACT_TOUCHED_WARNING,
        LegacyQuoteStatusApplier::CONTRACT_KEPT_WARNING,
    ];

    private const string CALLBACK_KEY_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        private readonly LegacyRecordTouchGuard $touchGuard,
        private readonly LegacyQuoteStatusApplier $statusApplier,
        private readonly LegacyAttributeValueApplier $attributeValueApplier,
        private readonly QuoteManagerWriter $managerWriter,
        private readonly RevenueProductTitleBuilder $titleBuilder,
    ) {}

    /**
     * @param  array{title?: string, quote_date?: string, internal_notes: ?string, next_callback_at: ?CarbonImmutable, supervisor_id?: ?int}  $fields  the legacy values to compare, a missing key = leave the current one
     * @param  array<int, int|null>  $managerSlots  empty = leave the managers as they are
     * @param  array<string, mixed>  $attributeValues
     * @param  array<int, string>  $warnings  mapping warnings, reported only when something was written (D-8)
     */
    public function realign(
        Quote $quote,
        array $fields,
        array $managerSlots,
        array $attributeValues,
        LegacyQuoteState $state,
        ?CarbonImmutable $updatedAt,
        array $warnings,
    ): MigrationRowOutcome {
        // Step 1: an offer (or its opportunity) worked on in qnet-2 is not overwritten.
        if ($this->touchGuard->isQuoteTouched($quote)) {
            return MigrationRowOutcome::skipped([self::TOUCHED_WARNING]);
        }

        // Step 2: the differing scalars and flexible fields, in memory.
        $quote->forceFill($this->scalarAttributes($quote, $fields));
        $this->attributeValueApplier->applyToQuote($quote, $attributeValues, $warnings);
        $changed = $quote->isDirty();

        // Step 3: managers, then status and contract (each writes only when different).
        $changed = $this->syncManagers($quote, $managerSlots) || $changed;
        $changed = $this->statusApplier->realign($quote, $state, $warnings) || $changed;

        // Step 4: one quiet save carrying the legacy `updated_at`; an identical offer is skipped untouched.
        if (! $changed) {
            return MigrationRowOutcome::skipped(array_values(array_intersect($warnings, self::CONFLICT_WARNINGS)));
        }

        $quote->forceFill($updatedAt !== null ? ['updated_at' => $updatedAt] : [])->saveQuietly();

        return MigrationRowOutcome::updated($warnings);
    }

    /**
     * A legacy title equal to the automatic one keeps the offer automatic, any other is
     * manual (QuoteTitleWriter::write). A changed callback re-arms its reminder
     * (RequestManagementService::applyNextCallbackAt).
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function scalarAttributes(Quote $quote, array $fields): array
    {
        $attributes = ['internal_notes' => $fields['internal_notes']];

        if (isset($fields['title'])) {
            $isManual = $fields['title'] !== $this->titleBuilder->forQuote($quote);
            $attributes['title'] = $isManual ? $fields['title'] : $this->titleBuilder->forQuote($quote);
            $attributes['title_is_manual'] = $isManual;
        }

        foreach (['quote_date', 'supervisor_id'] as $column) {
            if (array_key_exists($column, $fields)) {
                $attributes[$column] = $fields[$column];
            }
        }

        $callback = $fields['next_callback_at'];

        if ($callback?->format(self::CALLBACK_KEY_FORMAT) !== $quote->next_callback_at?->format(self::CALLBACK_KEY_FORMAT)) {
            $attributes['next_callback_at'] = $callback;
            $attributes['next_callback_reminded_at'] = null;
        }

        return $attributes;
    }

    /**
     * @param  array<int, int|null>  $slots
     */
    private function syncManagers(Quote $quote, array $slots): bool
    {
        if ($slots === []) {
            return false;
        }

        $target = [];

        foreach (ManagerPositions::syncMap($slots) as $userId => $pivot) {
            $target[(int) $userId] = $pivot['position'];
        }

        $current = ManagerPositions::positionsOf($quote->managers()->get());
        ksort($current);
        ksort($target);

        if ($current === $target) {
            return false;
        }

        $this->managerWriter->sync($quote, $slots, promoteToOpportunity: true);

        return true;
    }
}
