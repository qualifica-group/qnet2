<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Enums\WorkflowStatusGroup;
use App\Policies\RequestManagementPolicy;
use Illuminate\Http\Request;

/**
 * THE single source of truth for what differs between "Gestione Richieste"
 * and "Gestione Iscritti" (spec 0130, goal): permission prefix, allowed
 * abilities, row-state filter and record path. Every caller reads these off
 * the enum — no `if ($module === ...)` may live outside it (constraints).
 *
 * `Requests` is the unrestricted module (no `create` gate, no status
 * filter): it is also the fail-safe default every parameterized call-site
 * falls back to, so the spec 0130 refactor is at parity for Gestione
 * Richieste (constraints, "refactor a parita'").
 */
enum RequestModule: string
{
    case Requests = 'request-management';
    case Enrollees = 'enrollee-management';

    /**
     * Route default key carried by every route belonging to one of these
     * two modules (spec 0130, data_contract): the module is resolved
     * server-side from the MATCHED route via fromRequest(), never from
     * client input.
     */
    public const string ROUTE_DEFAULT = 'requestModule';

    /**
     * "{value}.{ability}" — the same concatenation BasePolicy::permission()
     * performs, exposed here so every scope/authorization caller reads the
     * SAME prefix instead of hardcoding `request-management.`/
     * `enrollee-management.`.
     */
    public function permission(string $ability): string
    {
        return "{$this->value}.{$ability}";
    }

    /**
     * The abilities this module's policy exposes (spec 0130 D-4/D-8):
     * Enrollees is RequestManagementPolicy's own list MINUS `create` — no
     * creation surface at all, so `permissions:sync` never mints
     * `enrollee-management.create`.
     *
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Requests => RequestManagementPolicy::abilities(),
            self::Enrollees => array_values(array_diff(RequestManagementPolicy::abilities(), ['create'])),
        };
    }

    public function allowsCreate(): bool
    {
        return $this === self::Requests;
    }

    /**
     * The row-state filter (spec 0130 D-2): `null` means unrestricted —
     * Requests keeps showing every `quote_workflow_statuses.group` — while
     * Enrollees narrows to the two outcome groups that mark a request as
     * "iscritto". Declared ONCE here (constraints, "gruppi di stato Iscritti
     * definiti una sola volta"), never as inline strings at a call-site.
     *
     * @return array<int, WorkflowStatusGroup>|null
     */
    public function statusGroups(): ?array
    {
        return match ($this) {
            self::Requests => null,
            self::Enrollees => [WorkflowStatusGroup::Validated, WorkflowStatusGroup::ClosedWon],
        };
    }

    public function recordPath(): string
    {
        return "/{$this->value}";
    }

    /**
     * Resolves the module from the matched route's default (constraints:
     * "il modulo e' risolto server-side dalla rotta; mai da input client").
     * Falls back to Requests only when the matched route declares no
     * default at all, never from a request parameter/header/body.
     */
    public static function fromRequest(Request $request): self
    {
        $defaults = $request->route()?->defaults ?? [];

        return self::from($defaults[self::ROUTE_DEFAULT] ?? self::Requests->value);
    }
}
