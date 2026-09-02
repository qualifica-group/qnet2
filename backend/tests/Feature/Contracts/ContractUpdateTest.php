<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * PATCH /api/contracts/{contract} (spec 0072, MT-02): the partial write on
 * the 5 editable columns, 403, 422 on an inexistent/inactive status, and the
 * per-field permission ceiling (AC-039).
 */
uses(RefreshDatabase::class);

if (! function_exists('contractUpdateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractUpdateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('PATCH partially updates the 5 editable columns', function () {
    $contract = Contract::factory()->create(['payment_notes' => 'Before', 'comments' => 'Before']);
    $newStatus = ContractStatus::factory()->create(['is_active' => true]);
    $actor = contractUpdateUserWith(['update', 'view']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", [
        'contract_status_id' => $newStatus->id,
        'renewal_date' => '2027-01-15',
        'expiry_date' => '2027-06-30',
        'payment_notes' => 'After',
        'comments' => 'After',
    ])
        ->assertOk()
        ->assertJsonPath('data.contract_status_id', $newStatus->id)
        // `renewal_date`/`expiry_date` are `date`-cast (Contract::casts()):
        // Eloquent's default serializeDate() renders every date/datetime
        // cast as a full ISO-8601 instant (Carbon::toJSON()), never
        // truncated to Y-m-d — same behavior every other `date`-cast column
        // in this codebase gets (e.g. Campaign/Project/Opportunity's own
        // start_date/end_date), so this is not special-cased here either.
        ->assertJsonPath('data.renewal_date', '2027-01-15T00:00:00.000000Z')
        ->assertJsonPath('data.expiry_date', '2027-06-30T00:00:00.000000Z')
        ->assertJsonPath('data.payment_notes', 'After')
        ->assertJsonPath('data.comments', 'After');

    $this->assertDatabaseHas('contracts', [
        'id' => $contract->id,
        'contract_status_id' => $newStatus->id,
        'payment_notes' => 'After',
        'comments' => 'After',
    ]);
});

it('PATCH omitting a key leaves it untouched (partial)', function () {
    $contract = Contract::factory()->create(['payment_notes' => 'Kept', 'comments' => 'Kept']);
    $actor = contractUpdateUserWith(['update', 'view']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", ['comments' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.payment_notes', 'Kept')
        ->assertJsonPath('data.comments', 'Changed');
});

// ---------------------------------------------------------------------------
// 403
// ---------------------------------------------------------------------------

it('PATCH is 403 without contracts.update, no change persisted', function () {
    $contract = Contract::factory()->create(['comments' => 'Untouched']);
    $actor = contractUpdateUserWith([]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", ['comments' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'comments' => 'Untouched']);
});

// ---------------------------------------------------------------------------
// 422 — inexistent / inactive contract_status_id
// ---------------------------------------------------------------------------

it('PATCH with a nonexistent contract_status_id is 422', function () {
    $contract = Contract::factory()->create();
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", ['contract_status_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('contract_status_id');
});

// ---------------------------------------------------------------------------
// 422 — renewal_date after expiry_date (spec 0095: the rule the retired
// `schedule` endpoint used to enforce, now this PATCH's own responsibility)
// ---------------------------------------------------------------------------

it('PATCH with both dates and renewal_date after expiry_date is 422, nothing persisted', function () {
    $contract = Contract::factory()->create();
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", [
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'renewal_date' => now()->addYear()->toDateString(),
    ])->assertStatus(422)->assertJsonValidationErrors('renewal_date');

    expect($contract->fresh()->expiry_date)->toBeNull()
        ->and($contract->fresh()->renewal_date)->toBeNull();
});

it('PATCH sending only renewal_date is 422 against the CONTRACT\'s existing expiry_date', function () {
    $contract = Contract::factory()->create(['expiry_date' => now()->addMonths(6)->toDateString()]);
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", [
        'renewal_date' => now()->addYear()->toDateString(),
    ])->assertStatus(422)->assertJsonValidationErrors('renewal_date');

    expect($contract->fresh()->renewal_date)->toBeNull();
});

it('PATCH sending only expiry_date is 422 against the CONTRACT\'s existing renewal_date', function () {
    $contract = Contract::factory()->create(['renewal_date' => now()->addYear()->toDateString()]);
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", [
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ])->assertStatus(422)->assertJsonValidationErrors('renewal_date');

    expect($contract->fresh()->expiry_date)->toBeNull();
});

it('PATCH sending only renewal_date is accepted when the contract has no expiry_date yet (no invented obligation)', function () {
    $contract = Contract::factory()->create(['expiry_date' => null]);
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $renewalDate = now()->addYear()->toDateString();

    $this->patchJson("/api/contracts/{$contract->id}", ['renewal_date' => $renewalDate])->assertOk();

    expect($contract->fresh()->renewal_date->toDateString())->toBe($renewalDate)
        ->and($contract->fresh()->expiry_date)->toBeNull();
});

it('PATCH explicitly clearing renewal_date to null is accepted even with an expiry_date set', function () {
    $contract = Contract::factory()->create([
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'renewal_date' => now()->addMonths(3)->toDateString(),
    ]);
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", ['renewal_date' => null])->assertOk();

    expect($contract->fresh()->renewal_date)->toBeNull();
});

it('PATCH with renewal_date equal to expiry_date is accepted (before_or_equal)', function () {
    $contract = Contract::factory()->create();
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $sameDate = now()->addMonths(6)->toDateString();

    $this->patchJson("/api/contracts/{$contract->id}", [
        'expiry_date' => $sameDate, 'renewal_date' => $sameDate,
    ])->assertOk();

    expect($contract->fresh()->renewal_date->toDateString())->toBe($sameDate);
});

it('PATCH with an inactive contract_status_id is 422, nothing persisted', function () {
    $contract = Contract::factory()->create();
    $inactiveStatus = ContractStatus::factory()->create(['is_active' => false]);
    $actor = contractUpdateUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", ['contract_status_id' => $inactiveStatus->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('contract_status_id');

    expect($contract->fresh()->contract_status_id)->toBe($contract->contract_status_id);
});

// ---------------------------------------------------------------------------
// AC-039 — per-field permission ceiling: payment_notes readonly for the role
// ---------------------------------------------------------------------------

it('AC-039: payment_notes readonly for the actor\'s role -> 422 on a CHANGED value, no write', function () {
    foreach (['view', 'update'] as $ability) {
        Permission::findOrCreate("contracts.{$ability}");
    }

    $role = Role::create(['name' => 'contract-payment-notes-locked']);
    $role->givePermissionTo(['contracts.view', 'contracts.update']);
    $role->fieldPermissions()->create([
        'resource' => 'contracts',
        'field' => 'payment_notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $contract = Contract::factory()->create(['payment_notes' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", ['payment_notes' => 'Changed'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_notes');

    expect($contract->fresh()->payment_notes)->toBe('Original');
});

it('AC-039: PATCH resubmitting the SAME payment_notes for a locked field is a no-op that passes', function () {
    foreach (['view', 'update'] as $ability) {
        Permission::findOrCreate("contracts.{$ability}");
    }

    $role = Role::create(['name' => 'contract-payment-notes-locked-noop']);
    $role->givePermissionTo(['contracts.view', 'contracts.update']);
    $role->fieldPermissions()->create([
        'resource' => 'contracts',
        'field' => 'payment_notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $contract = Contract::factory()->create(['payment_notes' => 'Original', 'comments' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", ['payment_notes' => 'Original', 'comments' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.payment_notes', 'Original')
        ->assertJsonPath('data.comments', 'After');
});
