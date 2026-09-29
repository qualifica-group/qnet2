<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use App\Stats\StatsRegistry;
use App\Stats\Widgets\Widget;
use App\Support\Cache\AggregateCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/stats/{domain} — the generic, registry-driven module statistics
 * panel (spec 0026). One controller serves every module registered in
 * config/stats.php, mirroring TableController/MetaController's fail-closed
 * pattern: unknown {domain} → 404 (StatsRegistry::resolve), missing
 * `{domain}.viewAny` → 403 (the definition's own gate, D-3).
 *
 * Thin by construction: no business logic, no queries — the definition owns
 * both, and the widget builders own the JSON shape.
 */
class StatsController extends BaseApiController
{
    public function __construct(
        private readonly StatsRegistry $registry,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(Request $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            // The locale is part of the key: widget and enum labels are translated.
            $widgets = $this->cache->remember(
                "stats:{$domain}:{$definition->cacheScope($actor)}:".app()->getLocale(),
                fn () => array_map(static fn (Widget $widget) => $widget->toArray(), $definition->widgets()),
                $actor,
            );

            return $this->ok(['widgets' => $widgets]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['domain' => $domain]);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403.
     *
     * @throws AuthorizationException
     */
    private function authorizeViewAny(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
