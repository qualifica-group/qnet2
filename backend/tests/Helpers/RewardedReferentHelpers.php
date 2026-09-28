<?php

declare(strict_types=1);

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('rewardedReferentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardedReferentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("rewarded-referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("rewarded-referents.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('rewardForOpportunity')) {
    /**
     * A Reward whose origin is $opportunity (bypasses the factory's own
     * default nested Opportunity, D-3's `nested_sync_precedent` shape).
     *
     * @param  array<string, mixed>  $attributes
     */
    function rewardForOpportunity(Referent $referent, Opportunity $opportunity, array $attributes = []): Reward
    {
        return Reward::factory()->for($opportunity, 'source')->create([
            'referent_id' => $referent->id,
            ...$attributes,
        ]);
    }
}
