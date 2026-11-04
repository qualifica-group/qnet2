<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ChartOfAccounts\ImportChartOfAccountsRequest;
use App\Imports\ChartOfAccounts\ChartOfAccountsImporter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * POST /api/chart-of-accounts/import (spec 0349, D-2, D-8): preview
 * (`dry_run=true`) or all-or-nothing application of an exported-layout file.
 */
class ChartOfAccountsImportController extends BaseApiController
{
    public function __construct(private readonly ChartOfAccountsImporter $importer) {}

    public function __invoke(ImportChartOfAccountsRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $path = $request->spreadsheetPath();

            try {
                return $this->ok($this->importer->handle($path, $request->isDryRun(), $actor));
            } finally {
                File::delete($path);
            }
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
