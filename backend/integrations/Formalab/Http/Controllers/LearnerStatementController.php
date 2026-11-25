<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Integrations\Formalab\Exports\LearnerStatementExporter;
use Integrations\Formalab\Http\Requests\LearnerStatementRequest;
use Integrations\Formalab\Http\Requests\UpdateLearnerCommissionRequest;
use Integrations\Formalab\Http\Requests\UpdatePartnerSupplierRequest;
use Integrations\Formalab\Services\LearnerStatement\CommissionEditor;
use Integrations\Formalab\Services\LearnerStatement\LearnerStatementLoader;
use Integrations\Formalab\Services\LearnerStatement\StatementPresenter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * "Prospetto contabile discenti" of a work order (spec 0427): reading and
 * export need the work order's `viewCosts`, the edits its `manageCosts`.
 */
class LearnerStatementController extends BaseApiController
{
    private const string XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private readonly LearnerStatementLoader $loader,
        private readonly StatementPresenter $presenter,
        private readonly CommissionEditor $editor,
        private readonly LearnerStatementExporter $exporter,
    ) {}

    /**
     * GET /api/integrations/formalab/work-orders/{workOrder}/learner-statement.
     */
    public function show(LearnerStatementRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            Gate::authorize('viewCosts', $workOrder);

            return $this->respond($workOrder, $request->scenario(), $request->boolean('refresh'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT .../learner-statement/learners/{taxCode}/commission.
     */
    public function updateCommission(UpdateLearnerCommissionRequest $request, WorkOrder $workOrder, string $taxCode): JsonResponse
    {
        try {
            Gate::authorize('manageCosts', $workOrder);

            $statement = $this->loader->load($workOrder, $request->scenario());
            $percentage = $request->validated('percentage');

            if ($percentage !== null) {
                $this->editor->setPercentage($statement, $taxCode, (float) $percentage);
            } else {
                $this->editor->setAmount($statement, $taxCode, (float) $request->validated('amount'));
            }

            return $this->respond($workOrder, $request->scenario());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT .../learner-statement/partners/supplier.
     */
    public function updatePartnerSupplier(UpdatePartnerSupplierRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            Gate::authorize('manageCosts', $workOrder);

            $statement = $this->loader->load($workOrder, $request->scenario());
            $this->editor->setPartnerSupplier($statement, (string) $request->validated('partner'), $request->supplierId());

            return $this->respond($workOrder, $request->scenario());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET .../learner-statement/export: the legacy Excel, whole or one sheet per partner.
     */
    public function export(LearnerStatementRequest $request, WorkOrder $workOrder): BinaryFileResponse|JsonResponse
    {
        try {
            Gate::authorize('viewCosts', $workOrder);

            $statement = $this->loader->load($workOrder, $request->scenario());
            $path = (string) tempnam(sys_get_temp_dir(), 'learner-statement');
            $filename = $this->exporter->write($statement, $request->variant(), $path);

            if ($filename === null) {
                @unlink($path);

                return $this->fail(__('formalab::messages.no_partners'), 422);
            }

            return response()->download($path, $filename, ['Content-Type' => self::XLSX_CONTENT_TYPE])->deleteFileAfterSend();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function respond(WorkOrder $workOrder, ?string $scenario, bool $refresh = false): JsonResponse
    {
        $statement = $this->loader->load($workOrder, $scenario, $refresh);

        return $this->ok($this->presenter->toArray($statement, Gate::allows('manageCosts', $workOrder)));
    }
}
