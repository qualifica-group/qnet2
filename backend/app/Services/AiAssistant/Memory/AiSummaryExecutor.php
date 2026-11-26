<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Memory;

/**
 * Runs the summary request on the model. The turn implements it with its driver and its cost
 * reservation, so the call is costed like any other (spec 0433, D-7).
 */
interface AiSummaryExecutor
{
    /**
     * @param  list<AiMemoryEntry>  $request  system instructions then one user message
     * @return string the summary text
     */
    public function complete(array $request): string;
}
