<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Services\CallLogger;
use Integrations\Formalab\Support\Trigger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Authenticates Formalab's inbound calls with the shared X-Formalab-Token and
 * logs every one of them, rejected or not. The token itself is never logged.
 */
final class InboundWebhookGuard
{
    private const string TOKEN_HEADER = 'X-Formalab-Token';

    public function __construct(private readonly CallLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $expected = (string) config('formalab.inbound_token');

        // Step 1: a token that is not configured means the endpoint does not exist
        if ($expected === '') {
            abort(404);
        }

        // Step 2: constant-time comparison; a mismatch is logged and refused
        $provided = $request->header(self::TOKEN_HEADER);

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            $this->log($request, 401, null, $startedAt, __('formalab::messages.invalid_token'));

            return response()->json(['success' => false, 'message' => __('formalab::messages.invalid_token')], 401);
        }

        // Step 3: let the request through and log its outcome, also when it ends in an exception
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->log($request, $this->statusOf($exception), null, $startedAt, $exception->getMessage());

            throw $exception;
        }

        $this->log($request, $response->getStatusCode(), $response, $startedAt, null);

        return $response;
    }

    private function statusOf(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }

    private function log(Request $request, int $status, ?Response $response, float $startedAt, ?string $error): void
    {
        $ids = $request->input('edition_ids');

        $this->logger->record(
            FormalabCallLog::DIRECTION_INBOUND,
            $request->method(),
            '/'.$request->path(),
            $status,
            (int) round((microtime(true) - $startedAt) * 1000),
            Trigger::Webhook,
            is_array($ids) ? ['edition_ids' => array_slice(array_map(intval(...), array_filter($ids, is_scalar(...))), 0, 50)] : null,
            $error ?? ($status >= 400 ? 'HTTP '.$status : null),
            $this->responseSummary($response),
        );
    }

    private function responseSummary(?Response $response): ?string
    {
        if (! $response instanceof JsonResponse) {
            return null;
        }

        $data = $response->getData(true)['data'] ?? null;

        return is_array($data) && isset($data['refreshed'])
            ? sprintf('%d classes refreshed, %d work orders updated', $data['refreshed'], $data['work_orders_updated'] ?? 0)
            : null;
    }
}
