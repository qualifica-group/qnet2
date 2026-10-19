<?php

namespace Database\Seeders\ManagementControl;

use App\DataObjects\Quotes\QuoteLineData;
use App\Models\Product;

/**
 * The offer lines of a planned deal: one REVENUE line per edition or phase, priced on
 * the demo product, and the COST lines budgeted against each of them (external
 * teacher, room, material for a course; subcontracting and travel for a phase), which
 * feed the commessa's cost budget in the income statement.
 */
final class McQuoteLines
{
    private const int TRAVEL_TRAINS = 2;

    private const int TRAVEL_NIGHTS = 1;

    /**
     * @param  array<string, mixed>  $deal
     * @return list<QuoteLineData>
     */
    public function offerLines(array $deal, Product $product): array
    {
        return array_map(fn (array $line, int $index): QuoteLineData => new QuoteLineData(
            productId: $product->id,
            quantity: (float) $line['quantity'],
            unitPrice: self::unitPrice($product, $line),
            vatRateId: $product->vat_rate_id,
            sortOrder: $index + 1,
            additionalDescription: $line['description'],
            hasAdditionalDescription: true,
        ), $deal['lines'], array_keys($deal['lines']));
    }

    /**
     * @param  array<string, mixed>  $deal
     * @return list<QuoteLineData>
     */
    public function costLines(array $deal, McContext $context): array
    {
        $product = $context->products[$deal['product']];
        $costLines = [];

        foreach ($deal['lines'] as $index => $line) {
            foreach (self::budget($line, $product) as [$costName, $quantity]) {
                $cost = $context->products[$costName];
                $costLines[] = new QuoteLineData($cost->id, (float) $quantity, (float) $cost->cost, $cost->vat_rate_id, null, offerLineIndex: $index);
            }
        }

        return $costLines;
    }

    /** The unit price of a line: the product price times the line factor, to the cent. */
    public static function unitPrice(Product $product, array $line): float
    {
        return round((float) $product->price * $line['price_factor'], 2);
    }

    /** The hours a line takes: given for a course edition, sized on the value for a phase. */
    public static function hours(Product $product, array $line): int
    {
        if (isset($line['hours'])) {
            return $line['hours'];
        }

        return (int) round(self::unitPrice($product, $line) * $line['quantity'] * $line['labor_share'] / $line['sizing_hourly_cost']);
    }

    /** The external hours subcontracted on a phase, priced as external teacher hours. */
    public static function subcontractHours(Product $product, array $line, float $hourlyRate): int
    {
        $share = $line['subcontract_share'] ?? 0.0;

        return $share > 0 ? (int) round(self::unitPrice($product, $line) * $share / $hourlyRate) : 0;
    }

    /**
     * The budgeted cost items of a line: [cost product name, quantity].
     *
     * @param  array<string, mixed>  $line
     * @return list<array{0: string, 1: int}>
     */
    public static function budget(array $line, Product $product): array
    {
        if (isset($line['participants'])) {
            return array_values(array_filter([
                $line['teacher_hours'] > 0 ? [McCatalogue::COST_TEACHER, $line['teacher_hours']] : null,
                $line['room_days'] > 0 ? [McCatalogue::COST_ROOM, $line['room_days']] : null,
                [McCatalogue::COST_MATERIAL, $line['participants']],
            ]));
        }

        $subcontract = self::subcontractHours($product, $line, McDelivery::TEACHER_RATE);

        return array_values(array_filter([
            $subcontract > 0 ? [McCatalogue::COST_TEACHER, $subcontract] : null,
            $line['travel'] ? [McCatalogue::COST_TRAIN, self::TRAVEL_TRAINS] : null,
            $line['travel'] ? [McCatalogue::COST_HOTEL, self::TRAVEL_NIGHTS] : null,
        ]));
    }
}
