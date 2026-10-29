<?php

namespace App\Authorization\Denials;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Auth\Access\Response;

/**
 * Per-request record of the Gate evaluations that were denied (spec 0327,
 * D-5), registered `scoped` so one request owns one log and nothing leaks
 * across queue workers/Octane.
 *
 * Spatie permissions are Gate abilities WITH a dot (`opportunities.delete`);
 * Policy abilities have none (`delete`). A Policy ability is evaluated AFTER
 * the permission checks it performs internally, so when it is denied the
 * dotted denials buffered so far are its causes. Any Policy evaluation, allowed
 * or not, closes the buffer: an earlier unrelated denial can never be blamed
 * for a later refusal.
 */
class AuthorizationDenialLog
{
    /** @var list<string> */
    private array $pendingPermissions = [];

    private ?string $lastDeniedPermission = null;

    public function record(GateEvaluated $event): void
    {
        $allowed = $event->result instanceof Response ? $event->result->allowed() : $event->result === true;

        $this->evaluated($event->ability, $allowed);
    }

    public function evaluated(string $ability, bool $allowed): void
    {
        if (str_contains($ability, '.')) {
            $this->recordPermission($ability, $allowed);

            return;
        }

        $this->lastDeniedPermission = $allowed ? null : $this->pickPermission($ability);
        $this->pendingPermissions = [];
    }

    /**
     * The permission to blame for the most recent denial, or null when the
     * last evaluation was allowed or no permission was involved.
     */
    public function deniedPermission(): ?string
    {
        return $this->lastDeniedPermission;
    }

    private function recordPermission(string $permission, bool $allowed): void
    {
        if ($allowed) {
            $this->lastDeniedPermission = null;

            return;
        }

        $this->pendingPermissions[] = $permission;
        $this->lastDeniedPermission = $permission;
    }

    /**
     * `{resource}.{ability}` when it is among the causes, otherwise the first.
     */
    private function pickPermission(string $ability): ?string
    {
        foreach ($this->pendingPermissions as $permission) {
            if (str_ends_with($permission, '.'.$ability)) {
                return $permission;
            }
        }

        return $this->pendingPermissions[0] ?? null;
    }
}
