<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\Quotes\QuoteLineCommissionData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\ProductUsage;
use App\Enums\QuoteLineType;
use App\Migrations\Sources\ProductsSource;
use App\Migrations\Sources\QuotesSource;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use RuntimeException;

/**
 * Legacy offer lines (`quotationservices`) to REVENUE QuoteLineData (spec
 * 0189, G-8/G-9). The product is resolved on (old_source = services, old_id),
 * never on `old_id` alone: the cost products share the same id space. A line
 * whose service is missing or deleted carries `free_product` instead and is sold as the [OLD]
 * product of its title (LegacyFreeLineProduct, spec 0305). A line
 * that cannot be imported (no product, product not migrated or not sellable,
 * a commission with an unknown role, type or recipient) fails the whole offer
 * with the reason (user decision 2026-10-14: nothing is dropped silently).
 *
 * @phpstan-require-extends QuotesSource
 */
trait MapsLegacyQuoteLines
{
    /**
     * Legacy commission role -> qnet recipient role and the model its
     * `recipient_id` is remapped onto via `old_id` (G-9).
     *
     * @var array<string, array{0: CommissionRecipientRole, 1: class-string}>
     */
    private const array LEGACY_COMMISSION_ROLES = [
        'commercial' => [CommissionRecipientRole::Commercial, Referent::class],
        'reporter' => [CommissionRecipientRole::Reporter, Referent::class],
        'supervisor' => [CommissionRecipientRole::Supervisor, User::class],
        'supplier' => [CommissionRecipientRole::Supplier, Registry::class],
    ];

    private const int COMMISSION_VALUE_SCALE = 4;

    /**
     * The importable lines, plus the legacy line id of each one keyed by the
     * `sort_order` it is created with, so `quote_lines.old_id` can be set
     * once QuoteService has persisted them.
     *
     * @param  array<int, string>  $warnings
     * @return array{0: array<int, QuoteLineData>, 1: array<int, int>}
     */
    private function legacyOfferLines(mixed $externalLines, array &$warnings): array
    {
        $lines = [];
        $legacyLineIds = [];

        foreach ((array) ($externalLines ?? []) as $externalLine) {
            $externalLine = (array) $externalLine;
            $line = $this->legacyOfferLine($externalLine, count($lines), $warnings);

            if (isset($externalLine['id'])) {
                $legacyLineIds[$line->sortOrder] = (int) $externalLine['id'];
            }

            $lines[] = $line;
        }

        return [$lines, $legacyLineIds];
    }

    /**
     * Anchor each line QuoteService created to its legacy line (`old_id`),
     * matched on the sort_order legacyOfferLines() assigned.
     *
     * @param  array<int, int>  $legacyLineIds  legacy line id keyed by sort_order
     */
    private function tagLegacyLines(Quote $quote, array $legacyLineIds): void
    {
        if ($legacyLineIds === []) {
            return;
        }

        $lines = QuoteLine::query()
            ->where('quote_id', $quote->id)
            ->where('line_type', QuoteLineType::Revenue)
            ->get(['id', 'sort_order']);

        foreach ($lines as $line) {
            if (isset($legacyLineIds[$line->sort_order])) {
                $line->forceFill(['old_id' => $legacyLineIds[$line->sort_order]])->saveQuietly();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $externalLine
     * @param  array<int, string>  $warnings
     */
    private function legacyOfferLine(array $externalLine, int $sortOrder, array &$warnings): QuoteLineData
    {
        $lineRef = $externalLine['id'] ?? '?';
        $unitPrice = (float) ($externalLine['unit_price'] ?? 0);
        $vatRateId = $this->remapLegacyId(VatRate::class, $externalLine['vat_rate_id'] ?? null, "line {$lineRef} vat_rate_id", $warnings);
        $productId = isset($externalLine['free_product'])
            ? $this->freeLineProduct->id((array) $externalLine['free_product'], $unitPrice, $vatRateId, $lineRef, $warnings)
            : $this->sellableServiceProductId($externalLine['product_id'] ?? null, $lineRef);

        $description = trim((string) ($externalLine['description'] ?? ''));

        return new QuoteLineData(
            productId: $productId,
            quantity: (float) ($externalLine['quantity'] ?? 0),
            unitPrice: $unitPrice,
            vatRateId: $vatRateId,
            sortOrder: $sortOrder,
            commissions: $this->legacyCommissions($externalLine['commissions'] ?? [], $lineRef, $warnings),
            additionalDescription: $description !== '' ? $description : null,
            hasAdditionalDescription: true,
        );
    }

    private function sellableServiceProductId(mixed $externalRef, int|string $lineRef): int
    {
        if ($externalRef === null || $externalRef === '' || (int) $externalRef === 0) {
            throw new RuntimeException("Line {$lineRef} has no product; the quote is not imported.");
        }

        $product = Product::query()
            ->where('old_source', ProductsSource::OLD_SOURCE)
            ->where('old_id', $externalRef)
            ->first(['id', 'usages']);

        if ($product === null) {
            throw new RuntimeException("Line {$lineRef}: product (legacy service id {$externalRef}) not migrated; the quote is not imported.");
        }

        if (! $product->isUsableAs(ProductUsage::forLineType(QuoteLineType::Revenue))) {
            throw new RuntimeException("Line {$lineRef}: product (legacy service id {$externalRef}) is not sellable; the quote is not imported.");
        }

        return $product->id;
    }

    /**
     * A legacy commission (type and value already decided by the legacy)
     * becomes a manual override (G-9); one that cannot be imported fails the offer.
     *
     * @param  array<int, string>  $warnings
     * @return array<int, QuoteLineCommissionData>
     */
    private function legacyCommissions(mixed $externalCommissions, int|string $lineRef, array &$warnings): array
    {
        $commissions = [];

        foreach ((array) ($externalCommissions ?? []) as $externalCommission) {
            $commissions[] = $this->legacyCommission((array) $externalCommission, $lineRef, $warnings);
        }

        return $commissions;
    }

    /**
     * @param  array<string, mixed>  $externalCommission
     * @param  array<int, string>  $warnings
     */
    private function legacyCommission(array $externalCommission, int|string $lineRef, array &$warnings): QuoteLineCommissionData
    {
        $legacyRole = (string) ($externalCommission['role'] ?? '');

        if (! isset(self::LEGACY_COMMISSION_ROLES[$legacyRole])) {
            throw new RuntimeException("Line {$lineRef}: unknown commission role '{$legacyRole}'; the quote is not imported.");
        }

        $type = CommissionType::tryFrom((string) ($externalCommission['commission_type'] ?? ''));

        if ($type === null) {
            throw new RuntimeException("Line {$lineRef}: {$legacyRole} commission without a valid commission type; the quote is not imported.");
        }

        [$role, $recipientClass] = self::LEGACY_COMMISSION_ROLES[$legacyRole];
        $recipientId = $this->remapLegacyId($recipientClass, $externalCommission['recipient_id'] ?? null, "line {$lineRef} {$legacyRole} commission recipient", $warnings);

        if ($recipientId === null) {
            throw new RuntimeException("Line {$lineRef}: {$legacyRole} commission recipient (legacy id ".($externalCommission['recipient_id'] ?? '?').') not migrated; the quote is not imported.');
        }

        return new QuoteLineCommissionData(
            id: null,
            role: $role,
            recipientType: $role->recipientType(),
            recipientId: $recipientId,
            type: $type,
            value: number_format((float) ($externalCommission['value'] ?? 0), self::COMMISSION_VALUE_SCALE, '.', ''),
            internalNote: null,
            origin: CommissionOrigin::ManualOverride,
            configurationId: null,
        );
    }
}
