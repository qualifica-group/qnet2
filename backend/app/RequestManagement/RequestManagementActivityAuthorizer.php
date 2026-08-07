<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\ActivityLog\Contracts\ActivityLogAuthorizer;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-gate of the `request-management` activity resource (spec 0049 D-7,
 * amended): the operational history stays anchored on the Opportunity (spec
 * 0086, D-9) even though the grid row migrated onto the Quote — this module
 * authorizes through its OWN permission set, never `opportunities.*` — the
 * default PolicyActivityLogAuthorizer would have resolved OpportunityPolicy,
 * which is exactly why the resource key stayed unregistered until now.
 *
 * The rule mirrors the work panel's own D-3 scope: `request-management.
 * viewActivity` for the surface, plus — since RequestManagementScope's
 * predicate is keyed on the Quote (D-3) — "the actor supervises at least one
 * of this Opportunity's Offerte" (the same rule RequestManagementNotable
 * applies for read access, so the two can never drift). An actor never reads
 * the history of a request they cannot open in ANY of its offers.
 *
 * Lives in this module's OWN namespace alongside RequestManagementNotable
 * (the notes equivalent), referenced as a pure class-string in
 * config/activity-log.php: app/ActivityLog/ stays agnostic.
 */
final class RequestManagementActivityAuthorizer implements ActivityLogAuthorizer
{
    public function authorize(User $user, Model $record): void
    {
        abort_unless($record instanceof Opportunity, 403);
        abort_unless($user->can('request-management.viewActivity'), 403);

        $query = Quote::query()->where('opportunity_id', $record->getKey());

        abort_unless(RequestManagementScope::scopeToActor($query, $user)->exists(), 403);
    }
}
