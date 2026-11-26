<?php

namespace App\Http\Controllers\Payslips;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Payslips\ImportPayslipsRequest;
use App\Imports\Payslips\PayslipImporter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * POST /api/purchase-invoices/payslips/import (spec 0431, D-8): preview
 * (`dry_run=true`) or all-or-nothing creation of the payslip documents of the
 * labour consultant's net file.
 */
class PayslipImportController extends BaseApiController
{
    public function __construct(private readonly PayslipImporter $importer) {}

    public function __invoke(ImportPayslipsRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $path = $request->spreadsheetPath();

            try {
                return $this->ok($this->importer->handle($path, $request->isDryRun(), $request->paymentDate(), $actor));
            } finally {
                File::delete($path);
            }
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
