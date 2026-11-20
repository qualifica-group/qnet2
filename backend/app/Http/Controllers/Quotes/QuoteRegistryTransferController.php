<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quotes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Quotes\TransferQuoteRegistryRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\RequestManagement\RequestModule;
use App\Services\Quotes\QuoteRegistryTransferService;
use App\Services\QuoteService;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * POST /api/quotes/{quote}/transfer-registry and
 * POST /api/{request-management|enrollee-management}/{quote}/transfer-registry
 * (spec 0404, part B): moves the offer to another anagrafica. Each route
 * authorizes with its own module's permission set, `transferRegistry` on top
 * of `update`, like `transferContact`.
 */
class QuoteRegistryTransferController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuoteRegistryTransferService $transferService,
        private readonly QuoteService $quoteService,
        private readonly RequestManagementScope $scope,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /** Offerte: answers with the same detail as GET /api/quotes/{quote}. */
    public function forQuotes(TransferQuoteRegistryRequest $request, Quote $quote): JsonResponse
    {
        try {
            $actor = $request->user();
            $this->authorize('update', $quote);
            Gate::forUser($actor)->authorize('quotes.'.QuoteRegistryTransferService::ABILITY);

            $quote = $this->transferService->transfer(
                $quote,
                $request->registryId(),
                $request->attributeValues(),
                $request->titleSubmitted(),
                $request->title(),
                $actor,
            );

            return $this->okWithPermissions(
                new QuoteResource($this->quoteService->loadDetail($quote)),
                $this->permissionsBuilder->build($this->authorization->resolve('quotes'), $actor, $quote),
                __('Offer transferred to :registry', ['registry' => $quote->opportunity->registry?->name]),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    /** Gestione Richieste / Iscritti: the work panel reloads itself, so the answer is the new link only. */
    public function forRequestModule(TransferQuoteRegistryRequest $request, Quote $quote): JsonResponse
    {
        try {
            $actor = $request->user();
            $module = RequestModule::fromRequest($request);
            Gate::forUser($actor)->authorize($module->permission('update'));
            Gate::forUser($actor)->authorize($module->permission(QuoteRegistryTransferService::ABILITY));
            $this->scope->assertInScope($actor, $quote, $module);

            $quote = $this->transferService->transfer(
                $quote,
                $request->registryId(),
                $request->attributeValues(),
                $request->titleSubmitted(),
                $request->title(),
                $actor,
            );

            return $this->ok(
                ['id' => $quote->id, 'opportunity_id' => $quote->opportunity_id, 'registry_id' => $quote->opportunity->registry_id],
                __('Offer transferred to :registry', ['registry' => $quote->opportunity->registry?->name]),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }
}
