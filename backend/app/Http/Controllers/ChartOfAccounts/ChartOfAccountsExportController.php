<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Exports\ChartOfAccounts\ChartOfAccountsExporter;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ChartOfAccounts\ExportChartOfAccountsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * GET /api/chart-of-accounts/export (spec 0349, D-1): synchronous download of
 * the whole chart in the import layout.
 */
class ChartOfAccountsExportController extends BaseApiController
{
    public function __construct(private readonly ChartOfAccountsExporter $exporter) {}

    public function __invoke(ExportChartOfAccountsRequest $request): BinaryFileResponse|JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            $format = $request->exportFormat();
            $path = tempnam(sys_get_temp_dir(), 'chart-of-accounts-');

            $this->exporter->write($format, $path, $actor);

            return response()
                ->download($path, 'chart-of-accounts-'.now()->toDateString().".{$format->extension()}", ['Content-Type' => $format->contentType()])
                ->deleteFileAfterSend();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
