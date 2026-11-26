<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

use LogicException;

/**
 * An open reservation, held by the turn between `reserve()` and exactly one of `settle()` or
 * `release()`.
 */
final class AiReservation
{
    private bool $closed = false;

    /**
     * @param  list<int>  $functionIds  business functions photographed with the message that started the turn
     */
    public function __construct(
        public readonly int $id,
        public readonly string $period,
        public readonly int $userId,
        public readonly ?int $conversationId,
        public readonly array $functionIds,
        public readonly string $amountUsd,
    ) {}

    /** @internal only the ledger closes a reservation */
    public function close(): void
    {
        if ($this->closed) {
            throw new LogicException('The reservation is already settled or released.');
        }
        $this->closed = true;
    }
}
