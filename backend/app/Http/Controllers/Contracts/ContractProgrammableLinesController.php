<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\ProgrammableQuoteLineResource;
use App\Models\Contract;
use App\Services\Contracts\ContractActionAvailability;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/contracts/{contract}/programmable-lines (spec 0095, D-6):
 * the offer's REVENUE lines feeding the "Programma" dialog, each carrying
 * the work order that already occupies it (or null). Gate: `contracts.program`
 * AND the contract's ClosedWon-group lifecycle
 * (ContractActionAvailability::mayProgram) — 403 on either, mirroring
 * ContractActionAvailability's own doubled-gate convention (each caller ANDs
 * the availability rule with the actor's ability).
 */
class ContractProgrammableLinesController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ContractActionAvailability $availability) {}

    public function __invoke(Contract $contract): JsonResponse
    {
        try {
            $this->authorize('program', $contract);
            abort_unless($this->availability->mayProgram($contract), 403);

            $contract->loadMissing('quote');

            $lines = $contract->quote->offerLines()
                ->with(['product.category', 'product.unitOfMeasure', 'unitOfMeasure', 'workOrders'])
                ->get();

            return $this->ok(ProgrammableQuoteLineResource::collection($lines));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }
}
