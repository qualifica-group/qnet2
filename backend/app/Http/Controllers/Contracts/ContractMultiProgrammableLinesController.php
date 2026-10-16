<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\MultiProgrammableLinesRequest;
use App\Models\Contract;
use App\Services\Contracts\MultiProgrammableLinesBuilder;
use App\Services\Contracts\MultiQuoteContractGate;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/contracts/multi-programmable-lines (spec 0267, D-3): revenue lines
 * of the selected contracts for "Programma commessa unica". The doubled gate
 * (`contracts.program` AND ClosedWon) is applied to EVERY contract: one
 * failure is a 403 for the whole request.
 */
class ContractMultiProgrammableLinesController extends BaseApiController
{
    public function __construct(
        private readonly MultiQuoteContractGate $gate,
        private readonly MultiProgrammableLinesBuilder $builder,
    ) {}

    public function __invoke(MultiProgrammableLinesRequest $request): JsonResponse
    {
        try {
            $contracts = $this->gate->load($request->user(), (array) $request->validated('contract_ids'));

            return $this->ok([
                'contracts' => $contracts->map(fn (Contract $contract): array => $this->summarize($contract))->all(),
                'lines' => $this->builder->build($contracts),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Contract $contract): array
    {
        $quote = $contract->quote;
        $registry = $quote->opportunity?->registry;

        return [
            'id' => $contract->id,
            'quote' => ['id' => $quote->id, 'code' => $quote->code, 'title' => $quote->title],
            'registry' => $registry === null ? null : ['id' => $registry->id, 'name' => $registry->name],
        ];
    }
}
