<?php

declare(strict_types=1);

namespace App\Tables\PurchaseRequests;

use App\Http\Requests\PurchaseRequests\PurchaseRequestWriteRequest;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestService;
use App\Services\Table\FormRequestCellValidator;

/**
 * The write behind the RDA grid's inline cell edit (spec 0256, D-3): the
 * cell's one field goes through PurchaseRequestWriteRequest and then
 * PurchaseRequestService::update() — the same pair PATCH
 * /api/purchase-requests/{purchaseRequest} runs, so the approver rule, the
 * company/site check and the field permissions are the form's own. The lines
 * are never sent, so they never change from a cell.
 */
final class PurchaseRequestCellWriter
{
    private const string ROUTE_PARAMETER = 'purchaseRequest';

    public function __construct(
        private readonly FormRequestCellValidator $formRequest,
        private readonly PurchaseRequestService $service,
    ) {}

    public function write(PurchaseRequest $purchaseRequest, string $fieldKey, mixed $value, User $actor): PurchaseRequest
    {
        $request = $this->formRequest->validate(PurchaseRequestWriteRequest::class, self::ROUTE_PARAMETER, $purchaseRequest, $actor, [$fieldKey => $value]);

        return $this->service->update($purchaseRequest, $request->toData(), $actor);
    }
}
