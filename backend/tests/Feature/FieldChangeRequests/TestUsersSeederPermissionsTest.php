<?php

use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0078, microtask F1: the seed's default permission matrix for the
 * `request-management.updateSource` protected field and the
 * `field-change-requests.*` permissions it introduces.
 */
uses(RefreshDatabase::class);

if (! function_exists('rolePermissionNames')) {
    /**
     * @return array<int, string>
     */
    function rolePermissionNames(string $role): array
    {
        return Role::findByName($role)->permissions()->pluck('name')->sort()->values()->all();
    }
}

// ---------------------------------------------------------------------------
// AC-051 — supervisor keeps every field-change-requests ability
// ---------------------------------------------------------------------------

it('AC-051: the supervisor holds updateSource plus the field-change-requests permissions', function () {
    $this->seed(TestUsersSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    expect($supervisor->can('request-management.updateSource'))->toBeTrue()
        ->and($supervisor->can('field-change-requests.view'))->toBeTrue()
        ->and($supervisor->can('field-change-requests.viewAny'))->toBeTrue()
        ->and($supervisor->can('field-change-requests.manage'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// The "Richieste di modifica" page is supervisor-only (user directive
// 2026-08-04): the navigation entry is gated on `field-change-requests.view`,
// the page behind it on `.viewAny`. Neither non-supervisory role holds them.
// ---------------------------------------------------------------------------

it('grants the field-change-requests page permissions to the supervisor only', function () {
    $this->seed(TestUsersSeeder::class);

    foreach (['campania@commerciale.com', 'umberto.santamaria@qualificagroup.com'] as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->can('field-change-requests.view'))->toBeFalse()
            ->and($user->can('field-change-requests.viewAny'))->toBeFalse()
            ->and($user->can('field-change-requests.manage'))->toBeFalse();
    }
});

it('still lets a commercial read the request they proposed, page permissions aside', function () {
    $this->seed(TestUsersSeeder::class);

    $commercial = User::query()->where('email', 'campania@commerciale.com')->firstOrFail();
    $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $opportunity->managers()->sync([$commercial->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);

    Sanctum::actingAs($commercial);

    $created = $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $opportunity->id,
        'field' => 'source_id',
        'requested_value' => Source::factory()->create()->id,
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/field-change-requests/{$created}")->assertOk();

    $this->getJson('/api/field-change-requests/for-record?resource=request-management&subject_id='.$opportunity->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $created);
});

// ---------------------------------------------------------------------------
// AC-052 — commercial loses updateSource, keeps create; enforced server-side
// ---------------------------------------------------------------------------

it('AC-052: the commercial lacks updateSource but holds field-change-requests.create', function () {
    $this->seed(TestUsersSeeder::class);

    $commercial = User::query()->where('email', 'campania@commerciale.com')->firstOrFail();

    expect($commercial->can('request-management.updateSource'))->toBeFalse()
        ->and($commercial->can('field-change-requests.create'))->toBeTrue();
});

it('AC-052: a commercial gets 422 writing the Fonte directly and 201 proposing a change request', function () {
    $this->seed(TestUsersSeeder::class);

    $commercial = User::query()->where('email', 'campania@commerciale.com')->firstOrFail();
    $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $opportunity->managers()->sync([$commercial->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);
    $otherSource = Source::factory()->create();

    Sanctum::actingAs($commercial);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['source_id' => $otherSource->id])
        ->assertStatus(422);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $opportunity->id,
        'field' => 'source_id',
        'requested_value' => $otherSource->id,
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-053 — idempotence: two runs produce the same permission set
// ---------------------------------------------------------------------------

it('AC-053: running the seeder twice produces the same permission set for every role', function () {
    $this->seed(TestUsersSeeder::class);
    $before = [
        'supervisor' => rolePermissionNames('supervisor'),
        'commercial' => rolePermissionNames('commercial'),
        'marketing' => rolePermissionNames('marketing'),
    ];

    $this->seed(TestUsersSeeder::class);
    $after = [
        'supervisor' => rolePermissionNames('supervisor'),
        'commercial' => rolePermissionNames('commercial'),
        'marketing' => rolePermissionNames('marketing'),
    ];

    expect($after)->toBe($before);
});
