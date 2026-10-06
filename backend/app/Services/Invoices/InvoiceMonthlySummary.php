<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;

/**
 * Per-month document count and total for the month strip of the list (spec 0194, D-15).
 */
final class InvoiceMonthlySummary
{
    private const int MONTHS = 12;

    /**
     * @param  string  $type  all|proforma|invoice
     * @return array{year: int, months: array<int, array{month: int, count: int, total_amount: string}>}
     */
    public function forYear(int $year, string $type): array
    {
        $months = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $months[$month] = ['month' => $month, 'count' => 0, 'total_amount' => '0.00'];
        }

        $rows = Invoice::query()->toBase()
            ->whereBetween('document_date', ["{$year}-01-01", "{$year}-12-31"])
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->get(['document_date', 'total_amount']);

        foreach ($rows as $row) {
            $month = (int) substr((string) $row->document_date, 5, 2);
            $months[$month]['count']++;
            $months[$month]['total_amount'] = bcadd($months[$month]['total_amount'], (string) $row->total_amount, 2);
        }

        return ['year' => $year, 'months' => array_values($months)];
    }
}
