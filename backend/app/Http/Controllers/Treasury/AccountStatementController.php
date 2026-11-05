<?php

declare(strict_types=1);

namespace App\Http\Controllers\Treasury;

use App\Exports\Treasury\AccountStatementExporter;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Treasury\BalancesRequest;
use App\Http\Requests\Treasury\StatementExportRequest;
use App\Http\Requests\Treasury\StatementRequest;
use App\Http\Requests\Treasury\UnassignedRequest;
use App\Models\FinancialAccount;
use App\Services\Treasury\AccountStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Read-only treasury endpoints (spec 0355): account statement (+ xlsx export),
 * balances and unassigned collections/payments. Authorization is in the
 * FormRequests (`treasury-movements.view` / `.export`).
 */
class AccountStatementController extends BaseApiController
{
    public function __construct(private readonly AccountStatementService $service) {}

    /** GET /api/financial-accounts/{financialAccount}/statement */
    public function statement(StatementRequest $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            return $this->ok($this->service->statement($financialAccount, $request->filters()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /** GET /api/financial-accounts/{financialAccount}/statement/export */
    public function export(StatementExportRequest $request, FinancialAccount $financialAccount, AccountStatementExporter $exporter): BinaryFileResponse|JsonResponse
    {
        try {
            $statement = $this->service->statement($financialAccount, $request->filters());
            $path = File::isDirectory(storage_path('app/tmp')) ? storage_path('app/tmp') : sys_get_temp_dir();
            $file = tempnam($path, 'stmt');
            $exporter->write($statement, $file);

            return response()->download($file, "account-statement-{$financialAccount->id}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /** GET /api/account-statements/balances */
    public function balances(BalancesRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->service->balances($request->companyId(), $request->balanceDate()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /** GET /api/account-statements/unassigned */
    public function unassigned(UnassignedRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->service->unassigned($request->companyId()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
