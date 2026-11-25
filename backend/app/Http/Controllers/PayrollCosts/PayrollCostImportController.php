<?php

namespace App\Http\Controllers\PayrollCosts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PayrollCosts\ImportPayrollCostsRequest;
use App\Imports\PayrollCosts\PayrollCostImporter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * POST /api/payroll-costs/import (spec 0430, D-1): preview (`dry_run=true`) or
 * all-or-nothing replacement of the month in the labour consultant's file.
 */
class PayrollCostImportController extends BaseApiController
{
    public function __construct(private readonly PayrollCostImporter $importer) {}

    public function __invoke(ImportPayrollCostsRequest $request): JsonResponse
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
