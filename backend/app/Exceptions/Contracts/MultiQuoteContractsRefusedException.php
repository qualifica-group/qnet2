<?php

declare(strict_types=1);

namespace App\Exceptions\Contracts;

use RuntimeException;

/**
 * Some contracts selected for "Programma commessa unica" (spec 0267, D-3/D-14)
 * cannot take part in it. Carries the translated sentence (kept on
 * `errors.contract_ids`) plus the machine-readable reason and the refused
 * contracts, so the dialog can list them and drop them from the selection.
 */
final class MultiQuoteContractsRefusedException extends RuntimeException
{
    /** The contract is not in a status a work order is programmed from. */
    public const string REASON_NOT_WON = 'not_won';

    /** The contract's offer has no line of a branch with the multi-offer setting on. */
    public const string REASON_NOT_ALLOWED = 'not_allowed';

    /**
     * @param  array<int, array{id: int, quote_code: string|null, registry_name: string|null}>  $contracts
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $contracts,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Body of the 422 `errors`: the sentence on `contract_ids` as before, plus the structured refusal.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return [
            'contract_ids' => [$this->getMessage()],
            'reason' => $this->reason,
            'refused_contracts' => $this->contracts,
        ];
    }
}
