<?php

declare(strict_types=1);

namespace App\Exports\AgentSalesReport;

use App\Enums\ExportFormat;
use App\Exports\ExportWriterFactory;
use Carbon\CarbonImmutable;

/**
 * "Dettagli" export of the Report Vendite Agenti (spec 0365, D-9): one sheet, after the header
 * row of the offer fields, a block per offer: (a) the offer row, (b) the lines header,
 * (c) one row per REVENUE line, (d) the commission and net totals.
 */
final class AgentSalesReportDetailsExporter
{
    private const string DATE_FORMAT = 'd/m/Y';

    private const int DECIMALS = 2;

    /** Offer header fields in the legacy order, keyed by the `lang` label. */
    private const array OFFER_LABELS = [
        'code', 'date', 'accepted', 'title', 'registry', 'address', 'zip_city', 'phone', 'email', 'company_site',
        'status', 'revenue_net', 'consultancy', 'institution', 'managers', 'supervisor', 'commercial', 'reporter',
    ];

    private const array LINE_LABELS = [
        'root_category', 'category', 'product', 'quantity', 'unit_price', 'amount', 'typology',
        'supervisor_commission', 'commercial_commission', 'reporter_commission', 'net',
    ];

    public function __construct(private readonly ExportWriterFactory $writers) {}

    /**
     * @param  iterable<int, array<string, mixed>>  $blocks  AgentSalesReportReader::blocks()
     */
    public function write(string $path, iterable $blocks): void
    {
        // Step 1: header row of the offer fields
        $writer = $this->writers->make(ExportFormat::Xlsx);
        $writer->open($path);
        $writer->writeHeaders($this->labels(self::OFFER_LABELS));

        // Step 2: one block per offer, in grid order
        foreach ($blocks as $block) {
            $writer->writeRow($this->offerRow($block));
            $writer->writeRow($this->labels(self::LINE_LABELS));

            foreach ($block['lines'] as $line) {
                $writer->writeRow(array_map(fn (string $key): string|float => $line[$key], self::LINE_LABELS));
            }

            $writer->writeRow($this->totalsRow($block));
        }

        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<string|float|null>
     */
    private function offerRow(array $block): array
    {
        $header = $block['header'];

        return [
            $header['code'],
            $this->date($header['quote_date']),
            $this->date($header['accepted_at']),
            $header['title'],
            $header['registry'],
            $header['address'],
            trim(($header['postal_code'] ?? '').' - '.($header['city'] ?? ''), ' -'),
            $header['phone'],
            $header['email'],
            $header['company_site']['name'] ?? null,
            $header['quote_workflow_status']['name'] ?? null,
            (float) $header['revenue_net'],
            $block['consultancy'],
            $block['institution'],
            implode('; ', array_column($header['managers'], 'name')),
            $block['supervisor'],
            $header['commercial']['name'] ?? null,
            $header['reporter']['name'] ?? null,
        ];
    }

    /**
     * Totals under the three commission columns; Netto is the offer's `revenue_net` less them.
     *
     * @param  array<string, mixed>  $block
     * @return list<string|float>
     */
    private function totalsRow(array $block): array
    {
        $lines = collect($block['lines']);
        $supervisor = round($lines->sum('supervisor_commission'), self::DECIMALS);
        $commercial = round($lines->sum('commercial_commission'), self::DECIMALS);
        $reporter = round($lines->sum('reporter_commission'), self::DECIMALS);
        $net = round((float) $block['header']['revenue_net'] - $supervisor - $commercial - $reporter, self::DECIMALS);

        return [__('agent_sales_report.export.total_commissions'), '', '', '', '', '', '', $supervisor, $commercial, $reporter, $net];
    }

    private function date(?string $isoDate): string
    {
        return $isoDate === null ? '' : CarbonImmutable::parse($isoDate)->format(self::DATE_FORMAT);
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function labels(array $keys): array
    {
        return array_map(static fn (string $key): string => __('agent_sales_report.export.'.$key), $keys);
    }
}
