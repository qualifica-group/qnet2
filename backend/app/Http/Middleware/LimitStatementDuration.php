<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Database\StatementTimeout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caps query duration for web/API requests: when PHP-FPM kills a slow request
 * the database would otherwise keep executing the query. Registered as HTTP
 * middleware (not in config) so CLI/queue and `config:cache` are unaffected.
 */
class LimitStatementDuration
{
    public function handle(Request $request, Closure $next): Response
    {
        $seconds = (int) config('database.statement_timeout.seconds');

        if ($seconds > 0 && ! $request->is(config('database.statement_timeout.excluded_paths', []))) {
            $statement = StatementTimeout::statementFor(DB::connection(), $seconds);

            if ($statement !== null) {
                DB::statement($statement);
            }
        }

        return $next($request);
    }
}
