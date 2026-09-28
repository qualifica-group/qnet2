<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\QualificaOperatorSeeder;
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

/**
 * `QualificaOperatorSeeder` is the expensive part of these tests (~1s per
 * run): the 3 scenarios below only read `can()` on already-seeded roles, so
 * they share a single seed run instead of one each.
 */
it('grants the field-change-requests permission matrix by role', function () {
    $this->seed(QualificaOperatorSeeder::class);

    // was: 'AC-051: the supervisor holds updateSource plus the field-change-requests permissions'
    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    expect($supervisor->can('request-management.updateSource'))->toBeTrue()
        ->and($supervisor->can('field-change-requests.view'))->toBeTrue()
        ->and($supervisor->can('field-change-requests.viewAny'))->toBeTrue()
        ->and($supervisor->can('field-change-requests.manage'))->toBeTrue();

    // The "Richieste di modifica" page is supervisor-only (user directive
    // 2026-08-04): the navigation entry is gated on `field-change-requests.view`,
    // the page behind it on `.viewAny`. Neither non-supervisory role holds them.
    // was: 'grants the field-change-requests page permissions to the supervisor only'
    foreach (['marco.baldi@qualificagroup.com', 'sabino.figurelli@qualificagroup.com'] as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->can('field-change-requests.view'))->toBeFalse()
            ->and($user->can('field-change-requests.viewAny'))->toBeFalse()
            ->and($user->can('field-change-requests.manage'))->toBeFalse();
    }

    // was: 'AC-052: the commercial lacks updateSource but holds field-change-requests.create'
    $commercial = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();

    expect($commercial->can('request-management.updateSource'))->toBeFalse()
        ->and($commercial->can('field-change-requests.create'))->toBeTrue();
});

/**
 * Both scenarios below create their own Opportunity/Quote and assert only on
 * the ids they mint, so merging them behind a single seed run cannot leak
 * into one another's assertions.
 */
it('lets a commercial create and read field-change-requests on their own offers', function () {
    $this->seed(QualificaOperatorSeeder::class);

    // was: 'still lets a commercial read the request they proposed, page permissions aside'
    $reader = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
    $readOpportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $readOpportunity->managers()->sync([$reader->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);
    $readQuote = Quote::factory()->for($readOpportunity)->create(['operator_id' => $reader->id]);

    Sanctum::actingAs($reader);

    $created = $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $readQuote->id,
        'field' => 'source_id',
        'requested_value' => Source::factory()->create()->id,
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/field-change-requests/{$created}")->assertOk();

    $this->getJson('/api/field-change-requests/for-record?resource=request-management&subject_id='.$readQuote->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $created);

    // was: 'AC-052: a commercial gets 422 writing the Fonte directly and 201 proposing a change request'
    $writer = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
    $writeOpportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $writeOpportunity->managers()->sync([$writer->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);
    $writeQuote = Quote::factory()->for($writeOpportunity)->create(['operator_id' => $writer->id]);
    $otherSource = Source::factory()->create();

    Sanctum::actingAs($writer);

    $this->patchJson("/api/request-management/{$writeQuote->id}", ['source_id' => $otherSource->id])
        ->assertStatus(422);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $writeQuote->id,
        'field' => 'source_id',
        'requested_value' => $otherSource->id,
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-053 — idempotence: two runs produce the same permission set
// ---------------------------------------------------------------------------

it('AC-053: running the seeder twice produces the same permission set for every role', function () {
    $this->seed(QualificaOperatorSeeder::class);
    $before = [
        'supervisor' => rolePermissionNames('supervisore-commerciale'),
        'commercial' => rolePermissionNames('commerciale'),
        'marketing' => rolePermissionNames('marketing'),
    ];

    $this->seed(QualificaOperatorSeeder::class);
    $after = [
        'supervisor' => rolePermissionNames('supervisore-commerciale'),
        'commercial' => rolePermissionNames('commerciale'),
        'marketing' => rolePermissionNames('marketing'),
    ];

    expect($after)->toBe($before);
});
