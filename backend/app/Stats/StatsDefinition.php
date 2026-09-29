<?php

declare(strict_types=1);

namespace App\Stats;

use App\Models\User;
use App\Stats\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * The contract of a module statistics panel (spec 0026).
 *
 * One implementation per module, registered in config/stats.php. GET
 * /api/stats/{domain} resolves it through the StatsRegistry and returns
 * `widgets()`; adding statistics to a module is one class + one config line,
 * with zero frontend work (the panel is generic).
 */
interface StatsDefinition
{
    /**
     * The route key, identical to the module's table domain
     * (config/tables.php), e.g. `leads`, `operational-sites`.
     */
    public function domain(): string;

    /**
     * The model the panel aggregates — also the subject of the `viewAny`
     * gate (see AbstractStatsDefinition).
     *
     * @return class-string<Model>
     */
    public function modelClass(): string;

    /**
     * Server-side authorization for the whole panel (spec 0026, D-3: the
     * module's existing `{domain}.viewAny`, no new permission).
     */
    public function authorizeViewAny(User $actor): bool;

    /**
     * The cache partition of this panel's aggregates (spec 0178, D-3):
     * `global` when every viewer sees the same numbers, `user:{id}` when the
     * widgets are narrowed to the actor.
     */
    public function cacheScope(User $actor): string;

    /**
     * The widgets, in display order. An empty list is legal (the frontend
     * renders its empty state).
     *
     * @return array<int, Widget>
     */
    public function widgets(): array;
}
