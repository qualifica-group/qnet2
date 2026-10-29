<?php

namespace App\Http\Controllers\Quotes;

use App\Enums\CommissionRecipientRole;
use App\Enums\SupplierCommissionDirection;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Quotes\QuoteCommissionDefaultsRequest;
use App\Models\Quote;
use App\Services\Commissions\QuoteCommissionInitializer;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use App\Services\Quotes\SupplierCommissionEnablement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

class QuoteCommissionDefaultsController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuoteCommissionInitializer $initializer,
        private readonly QuoteCommissionPayloadRedactor $redactor,
        private readonly SupplierCommissionEnablement $enablement,
    ) {}

    public function __invoke(QuoteCommissionDefaultsRequest $request): JsonResponse
    {
        try {
            $data = $request->toData();

            $quote = null;
            if ($data->quoteId === null) {
                $this->authorize('create', Quote::class);
            } else {
                $quote = Quote::findOrFail($data->quoteId);
                $this->authorize('update', $quote);
            }

            $permissions = $this->redactor->permissions($request->user(), $quote);

            abort_unless($permissions['commissions']->editable, 403, __('You cannot edit the commissions of this quote.'));

            // Spec 0306, D-6/AC-009: the preview direction is the resolved
            // Supplier rule's (PAID while the typology is on and no rule
            // applies, null when it is off); the form never recalculates the
            // rules client-side.
            $enabled = $this->enablement->enabledForProduct($data->productId);
            $drafts = $this->initializer->initialize($data, $enabled);
            $supplierDraft = collect($drafts)->first(fn ($draft): bool => $draft->role === CommissionRecipientRole::Supplier);
            $direction = $enabled ? ($supplierDraft?->supplierDirection ?? SupplierCommissionDirection::Paid) : null;

            return $this->ok([
                'commissions' => array_map(
                    fn ($draft): array => $this->redactor->redact($draft->toArray(), $permissions),
                    $drafts,
                ),
                'supplier_commission_direction' => $direction?->value,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
