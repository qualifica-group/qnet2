<?php

declare(strict_types=1);

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Treasury\RegistryCompensationsRequest;
use App\Http\Requests\Treasury\StoreInstallmentCompensationRequest;
use App\Http\Resources\InstallmentCompensationResource;
use App\Models\InstallmentCompensation;
use App\Models\Registry;
use App\Services\Treasury\CompensationReader;
use App\Services\Treasury\InstallmentCompensationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Compensation of a receivable against a payable installment (spec 0364).
 * Registering and cancelling need `invoices.collect` and `purchase-invoices.pay`;
 * the two reads need `ledger.view` (checked in the FormRequest).
 */
class InstallmentCompensationController extends BaseApiController
{
    public function __construct(
        private readonly InstallmentCompensationService $service,
        private readonly CompensationReader $reader,
    ) {}

    /** GET /api/registries/{registry}/compensation-candidates */
    public function candidates(RegistryCompensationsRequest $request, Registry $registry): JsonResponse
    {
        try {
            return $this->ok($this->reader->candidates($registry, (int) $request->companyId()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['registry' => $registry->id]);
        }
    }

    /** GET /api/registries/{registry}/compensations */
    public function index(RegistryCompensationsRequest $request, Registry $registry): JsonResponse
    {
        try {
            return $this->ok(InstallmentCompensationResource::collection($this->reader->forRegistry($registry, $request->companyId())));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['registry' => $registry->id]);
        }
    }

    /** POST /api/installment-compensations */
    public function store(StoreInstallmentCompensationRequest $request): JsonResponse
    {
        try {
            $compensation = $this->service->register($request->user(), $request->safe()->only([
                'invoice_installment_id', 'purchase_invoice_installment_id', 'compensated_at', 'note', 'residual_mode', 'residual_due_date',
            ]));

            return $this->created(new InstallmentCompensationResource($compensation->load(InstallmentCompensation::RELATIONS)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /** DELETE /api/installment-compensations/{installmentCompensation} */
    public function destroy(Request $request, InstallmentCompensation $installmentCompensation): JsonResponse
    {
        try {
            $actor = $request->user();
            abort_unless($actor?->can('invoices.collect') === true && $actor->can('purchase-invoices.pay'), 403, __('treasury_compensations.registered_forbidden'));

            $this->service->cancel($installmentCompensation);

            return $this->ok(null, __('treasury_compensations.cancelled'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installmentCompensation' => $installmentCompensation->id]);
        }
    }
}
