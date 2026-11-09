<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Maintenance\MaintenanceMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 503 to every API request during maintenance, except for the excluded
 * users (spec 0376). Runs before authentication, so a guest gets the 503 too.
 */
class EnsureNotInMaintenance
{
    /** What an excluded user without a session needs to reach the login form and sign in. */
    private const array OPEN_PATHS = ['api/config', 'api/auth/login'];

    public function __construct(private readonly MaintenanceMode $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenance->isEnabled() || $request->is(self::OPEN_PATHS) || $this->isExcludedCaller()) {
            return $next($request);
        }

        return $this->maintenance->unavailableResponse();
    }

    /** With no exclusions the token is never read, so the database stays untouched. */
    private function isExcludedCaller(): bool
    {
        if (! $this->maintenance->hasExclusions()) {
            return false;
        }

        $user = auth('sanctum')->user();

        return $user !== null && $this->maintenance->isExcluded($user);
    }
}
