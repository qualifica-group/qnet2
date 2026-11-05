<?php

declare(strict_types=1);

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Treasury\ReconciliationMarksRequest;
use App\Http\Requests\Treasury\ReconciliationPreviewRequest;
use App\Http\Requests\Treasury\StoreBankReconciliationRequest;
use App\Http\Resources\BankReconciliationResource;
use App\Models\BankReconciliation;
use App\Models\FinancialAccount;
use App\Services\Treasury\BankReconciliationService;
use App\Services\Treasury\ReconciliationMarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Statement ticks and bank reconciliations (spec 0363). Reading the history is
 * `treasury-movements.view`; everything else `treasury-movements.reconcile`
 * (checked in the FormRequests, or inline for the delete).
 */
class BankReconciliationController extends BaseApiController
{
    public function __construct(
        private readonly BankReconciliationService $reconciliations,
        private readonly ReconciliationMarkService $marks,
    ) {}

    /** POST /api/financial-accounts/{financialAccount}/reconciliation-marks */
    public function mark(ReconciliationMarksRequest $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            $updated = $this->marks->handle($financialAccount, $request->validated('keys'), $request->boolean('reconciled'), $request->user());

            return $this->ok(['updated' => $updated]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /** GET /api/financial-accounts/{financialAccount}/bank-reconciliations */
    public function index(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            abort_unless($request->user()?->can('treasury-movements.view') === true, 403, __('treasury_reconciliations.view_forbidden'));

            return $this->ok(BankReconciliationResource::collection($this->reconciliations->list($financialAccount)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /** GET /api/financial-accounts/{financialAccount}/bank-reconciliations/preview */
    public function preview(ReconciliationPreviewRequest $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            return $this->ok($this->reconciliations->preview($financialAccount, (string) $request->validated('statement_date')));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /** POST /api/financial-accounts/{financialAccount}/bank-reconciliations */
    public function store(StoreBankReconciliationRequest $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            $reconciliation = $this->reconciliations->create($financialAccount, $request->validated(), $request->user());

            return $this->created(new BankReconciliationResource($reconciliation));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /** DELETE /api/bank-reconciliations/{bankReconciliation} */
    public function destroy(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        try {
            abort_unless($request->user()?->can('treasury-movements.reconcile') === true, 403, __('treasury_reconciliations.manage_forbidden'));

            $this->reconciliations->delete($bankReconciliation);

            return $this->ok(null, __('treasury_reconciliations.deleted'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['bankReconciliation' => $bankReconciliation->id]);
        }
    }
}
