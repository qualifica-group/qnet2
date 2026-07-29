<?php

namespace App\Http\Controllers\Quotes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Quotes\QuoteCommissionDefaultsRequest;
use App\Models\Quote;
use App\Services\Commissions\QuoteCommissionInitializer;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

class QuoteCommissionDefaultsController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuoteCommissionInitializer $initializer,
        private readonly QuoteCommissionPayloadRedactor $redactor,
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

            abort_unless($permissions['commissions']->editable, 403);

            return $this->ok(array_map(
                fn ($draft): array => $this->redactor->redact($draft->toArray(), $permissions),
                $this->initializer->initialize($data),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
