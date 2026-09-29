<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Cache\AggregateCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec 0178, D-4: after every successful API write, remembers that the actor
 * wrote, so AggregateCache recomputes for them instead of serving a number
 * that predates their own change. Keyed on the HTTP method (not model events)
 * because bulk writes emit none; a false positive only costs one recompute.
 */
final class RecordActorWrite
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** POSTs that only read (table framework and form previews). */
    private const READ_ONLY_POSTS = [
        'api/tables/*/rows',
        'api/tables/*/values',
        'api/request-management/form-context',
        'api/enrollee-management/form-context',
    ];

    public function __construct(private readonly AggregateCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();

        if ($user === null
            || ! in_array($request->method(), self::WRITE_METHODS, true)
            || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
            || $request->is(...self::READ_ONLY_POSTS)) {
            return;
        }

        $this->cache->recordWrite($user);
    }
}
