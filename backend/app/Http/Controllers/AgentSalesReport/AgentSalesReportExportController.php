<?php

declare(strict_types=1);

namespace App\Http\Controllers\AgentSalesReport;

use App\Enums\ExportFormat;
use App\Exports\AgentSalesReport\AgentSalesReportDetailsExporter;
use App\Exports\AgentSalesReport\AgentSalesReportLinesExporter;
use App\Exports\AgentSalesReport\AgentSalesReportReader;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AgentSalesReport\ExportAgentSalesReportRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * The "Dettagli" and "Righe dettaglio" downloads of the Report Vendite Agenti (spec 0365, D-9):
 * synchronous xlsx files over the offers the grid state selects. The "Sommario" is the generic
 * `POST /api/exports/agent-sales-report`.
 */
class AgentSalesReportExportController extends BaseApiController
{
    private const string FILENAME_DATE_FORMAT = 'Ymd';

    public function __construct(
        private readonly AgentSalesReportReader $reader,
        private readonly AgentSalesReportDetailsExporter $detailsExporter,
        private readonly AgentSalesReportLinesExporter $linesExporter,
    ) {}

    public function details(ExportAgentSalesReportRequest $request): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->download($request, 'report-vendite-dettagli', $this->detailsExporter->write(...));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function lines(ExportAgentSalesReportRequest $request): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->download($request, 'report-vendite-righe', $this->linesExporter->write(...));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @param  callable(string, iterable<int, array<string, mixed>>): void  $write
     */
    private function download(ExportAgentSalesReportRequest $request, string $filenamePrefix, callable $write): BinaryFileResponse
    {
        // Step 1: the offers of the grid state (422 above the export cap, before any file exists)
        $ids = $this->reader->selectedIds($request->gridState());

        // Step 2: write the file from the offer blocks
        $path = tempnam(sys_get_temp_dir(), 'agent-sales-report-');
        $write($path, $this->reader->blocks($request->user(), $ids));

        // Step 3: download, then delete the temporary file
        $format = ExportFormat::Xlsx;

        return response()
            ->download($path, $filenamePrefix.'-'.now()->format(self::FILENAME_DATE_FORMAT).'.'.$format->extension(), ['Content-Type' => $format->contentType()])
            ->deleteFileAfterSend();
    }
}
