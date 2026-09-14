<?php

declare(strict_types=1);

namespace App\Http\Controllers\TimeEntries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TimeEntries\ExportFilteredTimeEntriesRequest;
use App\Http\Requests\TimeEntries\ExportMonthlyTimeEntriesRequest;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntries\TimeEntryExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The two xlsx exports (spec 0122, data_contract, MT-B5, AC-024/AC-025):
 * synchronous downloads, no `ExportRun` (out of scope, D-12). Authorization
 * is a resource-level ability check (`time-entries.export`/`exportMonthly`,
 * D-8) done HERE; rule R for `filtered()` runs INSIDE
 * `TimeEntryExportService` (`TimeEntryDaySetBuilder`), the same split
 * `TimeEntryController::index()` uses. Errors (403/422) fall through
 * `handleControllerException()` into the standard JSON envelope, never a
 * file (data_contract).
 *
 * @see TimeEntryExportService
 */
class TimeEntryExportController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly TimeEntryExportService $service) {}

    /**
     * GET /api/time-entries/exports/filtered.
     */
    public function filtered(ExportFilteredTimeEntriesRequest $request): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('export', TimeEntry::class);

            /** @var User $actor */
            $actor = $request->user();
            $export = $this->service->filtered($request->filter(), $actor);

            return $this->streamXlsx($export['spreadsheet'], $export['filename']);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/time-entries/exports/monthly.
     */
    public function monthly(ExportMonthlyTimeEntriesRequest $request): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('exportMonthly', TimeEntry::class);

            $export = $this->service->monthly($request->month(), $request->year(), $request->userIds());

            return $this->streamXlsx($export['spreadsheet'], $export['filename']);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ],
        );
    }
}
