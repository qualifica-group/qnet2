<?php

declare(strict_types=1);

namespace App\Services\ChartOfAccounts;

use App\Enums\ProformaRequestKind;
use App\Models\Invoice;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Automatic classification of the invoice lines that have no revenue
 * sub-account yet (spec 0350, D-9b/c): applies the proposal of the
 * RevenueTypeResolver to the NULL lines and NEVER touches a line that already
 * has a value, not even one classified by hand.
 */
final class RevenueLineClassifier
{
    private const int CHUNK = 500;

    public function __construct(private readonly RevenueTypeResolver $resolver) {}

    /**
     * @param  array<int, int>  $productIds
     * @return int lines classified
     */
    public function classifyProducts(array $productIds): int
    {
        return $productIds === [] ? 0 : $this->classify($this->unclassified()->whereIn('invoice_lines.product_id', $productIds));
    }

    /**
     * @return int lines classified
     */
    public function classifyAll(): int
    {
        return $this->classify($this->unclassified());
    }

    /**
     * @return int lines classified
     */
    public function classifyInvoice(Invoice $invoice): int
    {
        return $this->classify($this->unclassified()->where('invoice_lines.invoice_id', $invoice->id));
    }

    private function unclassified(): Builder
    {
        return DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->leftJoin('proforma_requests', 'proforma_requests.id', '=', 'invoices.proforma_request_id')
            ->whereNull('invoice_lines.revenue_type_id')
            ->select('invoice_lines.id', 'invoice_lines.product_id', 'proforma_requests.kind');
    }

    private function classify(Builder $lines): int
    {
        $classified = 0;

        $lines->chunkById(self::CHUNK, function ($chunk) use (&$classified): void {
            $items = [];

            foreach ($chunk as $line) {
                $items[$line->id] = ['product_id' => $line->product_id, 'institution' => $line->kind === ProformaRequestKind::Institution->value];
            }

            foreach (collect($this->resolver->resolve($items))->filter()->groupBy(fn (int $revenueTypeId): int => $revenueTypeId, preserveKeys: true) as $revenueTypeId => $proposals) {
                $classified += DB::table('invoice_lines')
                    ->whereIn('id', $proposals->keys())
                    ->whereNull('revenue_type_id')
                    ->update(['revenue_type_id' => $revenueTypeId]);
            }
        }, 'invoice_lines.id', 'id');

        return $classified;
    }
}
