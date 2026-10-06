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

/**
 * Legacy offer lines (`quotationservices`) to REVENUE QuoteLineData (spec
 * 0189, G-8/G-9). The product is resolved on (old_source = services, old_id),
 * never on `old_id` alone: the cost products share the same id space. A line
 * whose product is not migrated, or not sellable, is dropped with a warning
 * instead of failing the whole offer on QuoteLineWriter's usage guard.
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

    private const int DEFAULT_QUANTITY = 1;

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

            if ($line === null) {
                continue;
            }

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
    private function legacyOfferLine(array $externalLine, int $sortOrder, array &$warnings): ?QuoteLineData
    {
        $lineRef = $externalLine['id'] ?? '?';
        $productId = $this->sellableServiceProductId($externalLine['product_id'] ?? null, $lineRef, $warnings);

        if ($productId === null) {
            return null;
        }

        $quantity = $externalLine['quantity'] ?? null;

        if ($quantity === null || $quantity === '') {
            $warnings[] = "Line {$lineRef}: quantity missing, set to ".self::DEFAULT_QUANTITY.'.';
            $quantity = self::DEFAULT_QUANTITY;
        }

        $description = trim((string) ($externalLine['description'] ?? ''));

        return new QuoteLineData(
            productId: $productId,
            quantity: (float) $quantity,
            unitPrice: (float) ($externalLine['unit_price'] ?? 0),
            vatRateId: $this->remapLegacyId(VatRate::class, $externalLine['vat_rate_id'] ?? null, "line {$lineRef} vat_rate_id", $warnings),
            sortOrder: $sortOrder,
            commissions: $this->legacyCommissions($externalLine['commissions'] ?? [], $lineRef, $warnings),
            additionalDescription: $description !== '' ? $description : null,
            hasAdditionalDescription: true,
        );
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function sellableServiceProductId(mixed $externalRef, int|string $lineRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '' || (int) $externalRef === 0) {
            $warnings[] = "Line {$lineRef} skipped: it has no product.";

            return null;
        }

        $product = Product::query()
            ->where('old_source', ProductsSource::OLD_SOURCE)
            ->where('old_id', $externalRef)
            ->first(['id', 'usages']);

        if ($product === null) {
            $warnings[] = "Line {$lineRef} skipped: product (legacy service id {$externalRef}) not migrated.";

            return null;
        }

        if (! $product->isUsableAs(ProductUsage::forLineType(QuoteLineType::Revenue))) {
            $warnings[] = "Line {$lineRef} skipped: product (legacy service id {$externalRef}) is not sellable.";

            return null;
        }

        return $product->id;
    }

    /**
     * Only a legacy commission carrying a percentage or an amount becomes a
     * manual override (G-9); one whose recipient is not migrated is dropped
     * with a warning.
     *
     * @param  array<int, string>  $warnings
     * @return array<int, QuoteLineCommissionData>
     */
    private function legacyCommissions(mixed $externalCommissions, int|string $lineRef, array &$warnings): array
    {
        $commissions = [];

        foreach ((array) ($externalCommissions ?? []) as $externalCommission) {
            $commission = $this->legacyCommission((array) $externalCommission, $lineRef, $warnings);

            if ($commission !== null) {
                $commissions[] = $commission;
            }
        }

        return $commissions;
    }

    /**
     * @param  array<string, mixed>  $externalCommission
     * @param  array<int, string>  $warnings
     */
    private function legacyCommission(array $externalCommission, int|string $lineRef, array &$warnings): ?QuoteLineCommissionData
    {
        $legacyRole = (string) ($externalCommission['role'] ?? '');

        if (! isset(self::LEGACY_COMMISSION_ROLES[$legacyRole])) {
            $warnings[] = "Line {$lineRef}: unknown commission role '{$legacyRole}', commission skipped.";

            return null;
        }

        [$type, $value] = $this->legacyCommissionValue($externalCommission);

        if ($type === null) {
            return null;
        }

        [$role, $recipientClass] = self::LEGACY_COMMISSION_ROLES[$legacyRole];
        $recipientId = $this->remapLegacyId($recipientClass, $externalCommission['recipient_id'] ?? null, "line {$lineRef} {$legacyRole} commission recipient", $warnings);

        if ($recipientId === null) {
            return null;
        }

        return new QuoteLineCommissionData(
            id: null,
            role: $role,
            recipientType: $role->recipientType(),
            recipientId: $recipientId,
            type: $type,
            value: number_format($value, self::COMMISSION_VALUE_SCALE, '.', ''),
            internalNote: null,
            origin: CommissionOrigin::ManualOverride,
            configurationId: null,
        );
    }

    /**
     * A percentage wins over an amount; neither -> no commission.
     *
     * @param  array<string, mixed>  $externalCommission
     * @return array{0: CommissionType|null, 1: float}
     */
    private function legacyCommissionValue(array $externalCommission): array
    {
        $percentage = $externalCommission['percentage'] ?? null;

        if ($percentage !== null && $percentage !== '') {
            return [CommissionType::Percentage, (float) $percentage];
        }

        $amount = $externalCommission['amount'] ?? null;

        if ($amount !== null && $amount !== '') {
            return [CommissionType::FixedAmount, (float) $amount];
        }

        return [null, 0.0];
    }
}
