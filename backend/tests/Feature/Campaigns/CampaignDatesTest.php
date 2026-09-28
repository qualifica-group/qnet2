<?php

use App\Models\Campaign;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('campaignUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// start_date is required on every campaign (linked or standalone); end_date is
// optional (nullable) — only its ordering vs start_date is enforced.
// ---------------------------------------------------------------------------

it('create: 422 when start_date is missing, even for a linked campaign', function () {
    Sanctum::actingAs(campaignUserWith(['create']));
    $project = Project::factory()->create();

    $payload = ['name' => 'Linked No Date', 'project_id' => $project->id, ...campaignStoreDates()];
    unset($payload['start_date']);

    $this->postJson('/api/campaigns', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('start_date');

    expect(Campaign::count())->toBe(0);
});

it('create: succeeds when end_date is omitted (optional)', function () {
    Sanctum::actingAs(campaignUserWith(['create']));
    $project = Project::factory()->create();

    $payload = ['name' => 'Linked No End Date', 'project_id' => $project->id, ...campaignStoreDates()];
    unset($payload['end_date']);

    $this->postJson('/api/campaigns', $payload)->assertCreated();

    expect(Campaign::query()->where('name', 'Linked No End Date')->sole()->end_date)->toBeNull();
});
