<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use App\Models\User;

/**
 * Request-scoped memo of CompetenceReach per actor (spec 0340, D-8): the
 * visibility scopes are static and the policies zero-argument constructible,
 * so they cannot receive OperatorCompetence by injection, and a table page
 * asks for the reach once per row. Bound `scoped` in AppServiceProvider: one
 * resolution per request, never a stale reach on a long-running worker.
 */
final class ActorCompetenceReach
{
    /** @var array<int, CompetenceReach> */
    private array $byUserId = [];

    public function __construct(private readonly OperatorCompetence $competence) {}

    public static function for(User $user): CompetenceReach
    {
        return app(self::class)->resolve($user);
    }

    public function resolve(User $user): CompetenceReach
    {
        return $this->byUserId[(int) $user->getKey()] ??= $this->competence->reachOf($user);
    }
}
