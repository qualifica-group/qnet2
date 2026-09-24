<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use App\Services\RoleAssignmentGuard;

/**
 * The user a sample seeder writes on behalf of, for the services that demand
 * one (QuoteService's audit and commissions, ContractActionService's
 * `validated_by`/`terminated_by`): the first privileged account, falling back
 * on the first account at all.
 *
 * `whereHas` rather than spatie's `role()` scope, which throws when the role
 * does not exist yet — exactly the partial-run case this has to survive.
 */
trait ResolvesSeedActor
{
    protected function resolveActor(): ?User
    {
        return User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', RoleAssignmentGuard::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();
    }
}
