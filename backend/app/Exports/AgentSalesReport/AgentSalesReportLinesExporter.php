<?php

declare(strict_types=1);

namespace App\Exports\AgentSalesReport;

use App\Enums\ExportFormat;
use App\Exports\ExportWriterFactory;
use Carbon\CarbonImmutable;

/**
 * "Righe dettaglio" export of the Report Vendite Agenti (spec 0365, D-9): a header row and one
 * flat row per REVENUE line of the selected offers (an offer without one yields no row).
 */
final class AgentSalesReportLinesExporter
{
    private const string DATE_FORMAT = 'd/m/Y';

    private const array HEADERS = [
        'code', 'date', 'registry', 'root_category', 'category', 'product', 'quantity', 'unit_price', 'amount', 'typology',
        'supervisor', 'supervisor_commission', 'commercial', 'commercial_commission', 'reporter', 'reporter_commission', 'net',
    ];

    public function __construct(private readonly ExportWriterFactory $writers) {}

    /**
     * @param  iterable<int, array<string, mixed>>  $blocks  AgentSalesReportReader::blocks()
     */
    public function write(string $path, iterable $blocks): void
    {
        // Step 1: header row
        $writer = $this->writers->make(ExportFormat::Xlsx);
        $writer->open($path);
        $writer->writeHeaders(array_map(static fn (string $key): string => __('agent_sales_report.export.'.$key), self::HEADERS));

        // Step 2: one flat row per REVENUE line, offers in grid order
        foreach ($blocks as $block) {
            $header = $block['header'];
            $date = $header['quote_date'] === null ? '' : CarbonImmutable::parse($header['quote_date'])->format(self::DATE_FORMAT);

            foreach ($block['lines'] as $line) {
                $writer->writeRow([
                    $header['code'], $date, $header['registry'],
                    $line['root_category'], $line['category'], $line['product'],
                    $line['quantity'], $line['unit_price'], $line['amount'], $line['typology'],
                    $block['supervisor'], $line['supervisor_commission'],
                    $header['commercial']['name'] ?? null, $line['commercial_commission'],
                    $header['reporter']['name'] ?? null, $line['reporter_commission'],
                    $line['net'],
                ]);
            }
        }

        $writer->close();
    }
}
