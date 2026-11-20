<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Illuminate\Support\Str;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Support\Trigger;

/**
 * Writes one formalab_call_logs row per HTTP call, in either direction.
 * Callers pass already-safe summaries: credentials and tokens never get here.
 */
final class CallLogger
{
    private const int ERROR_MESSAGE_LIMIT = 1000;

    /**
     * @param  array<string, mixed>|null  $requestSummary
     */
    public function record(
        string $direction,
        string $method,
        string $endpoint,
        ?int $statusCode,
        int $durationMs,
        Trigger $trigger,
        ?array $requestSummary = null,
        ?string $errorMessage = null,
        ?string $responseSummary = null,
    ): FormalabCallLog {
        $succeeded = $errorMessage === null && $statusCode !== null && $statusCode < 400;

        return FormalabCallLog::query()->create([
            'direction' => $direction,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'request_summary' => $requestSummary === [] ? null : $requestSummary,
            'status_code' => $statusCode,
            'outcome' => $succeeded ? FormalabCallLog::OUTCOME_SUCCESS : FormalabCallLog::OUTCOME_FAILURE,
            'duration_ms' => $durationMs,
            'trigger' => $trigger->value,
            'error_message' => $errorMessage === null ? null : Str::limit($errorMessage, self::ERROR_MESSAGE_LIMIT, ''),
            'response_summary' => $responseSummary === null ? null : Str::limit($responseSummary, 250, ''),
        ]);
    }
}
