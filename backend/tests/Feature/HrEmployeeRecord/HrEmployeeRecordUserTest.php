<?php

use App\Enums\RelationshipTypeEnum;
use App\Models\CollectiveAgreement;
use App\Models\CollectiveAgreementLevel;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmploymentContract;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const HR_USER_IBAN = 'IT60X0542811101000000123456';
const HR_USER_IBAN_TYPED = 'it60 x054 2811 1010 0000 0123 456';

if (! function_exists('hrUserActor')) {
    /**
     * @param  array<int, string>  $abilities  `users.<ability>` names to grant
     */
    function hrUserActor(array $abilities, bool $compensation = false): User
    {
        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            Permission::findOrCreate("users.{$ability}");
            $actor->givePermissionTo("users.{$ability}");
        }

        if ($compensation) {
            Permission::findOrCreate('users.manageCompensation');
            $actor->givePermissionTo('users.manageCompensation');
        }

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// Permission catalogue
// ---------------------------------------------------------------------------

it('0272 D-5: permissions:sync creates users.manageCompensation and super-admin holds it', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'users.manageCompensation')->exists())->toBeTrue();

    Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $admin = User::factory()->create();
    $admin->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

    expect($admin->can('users.manageCompensation'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-007 (user part) — payment data hidden without users.manageCompensation
// ---------------------------------------------------------------------------

it('0272 AC-007: without users.manageCompensation the record has no iban keys and the meta hides them', function () {
    $actor = hrUserActor(['view', 'viewAny', 'update']);
    $target = User::factory()->withEmployment(fn ($f) => $f->state(['iban' => HR_USER_IBAN, 'iban_holder' => 'Mario Rossi']))->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/{$target->id}")->assertOk();

    expect($response->json('data.employment'))->not->toHaveKeys(['iban', 'iban_holder'])
        ->and($response->json('permissions.fields')['employment.iban']['hidden'])->toBeTrue()
        ->and($response->json('permissions.fields')['employment.iban_holder']['hidden'])->toBeTrue();
    expect($response->getContent())->not->toContain(HR_USER_IBAN);

    $meta = $this->getJson('/api/meta/users')->assertOk();
    expect($meta->json('permissions.fields')['employment.iban']['visible'])->toBeFalse();
});

it('0272 AC-007: writing iban or iban_holder without users.manageCompensation is 403 and changes nothing', function (array $payload) {
    $actor = hrUserActor(['view', 'update']);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => $payload])->assertForbidden();

    expect($target->employment->fresh()->iban)->toBeNull()
        ->and($target->employment->fresh()->iban_holder)->toBeNull();
})->with([
    'iban' => [['iban' => HR_USER_IBAN]],
    'holder' => [['iban_holder' => 'Mario Rossi']],
    'invalid iban still 403, never a 422 echoing it' => [['iban' => 'not-an-iban']],
]);

it('0272 AC-007: with users.manageCompensation the iban is visible and editable in the meta', function () {
    $actor = hrUserActor(['view', 'update'], compensation: true);
    $target = User::factory()->withEmployment(fn ($f) => $f->state(['iban' => HR_USER_IBAN, 'iban_holder' => 'Mario Rossi']))->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/{$target->id}")->assertOk()
        ->assertJsonPath('data.employment.iban', HR_USER_IBAN)
        ->assertJsonPath('data.employment.iban_holder', 'Mario Rossi');

    expect($response->json('permissions.fields')['employment.iban'])->toMatchArray(['visible' => true, 'editable' => true]);
});

// ---------------------------------------------------------------------------
// AC-008 — IBAN normalization, validation, encryption, activity log
// ---------------------------------------------------------------------------

it('0272 AC-008: a typed iban is stored uppercase without spaces, encrypted, and kept out of the activity log', function () {
    $actor = hrUserActor(['view', 'update'], compensation: true);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['covers_all_product_categories' => true, 'iban' => HR_USER_IBAN_TYPED, 'iban_holder' => 'Mario Rossi'],
    ])->assertOk()->assertJsonPath('data.employment.iban', HR_USER_IBAN);

    $raw = DB::table('employment_profiles')->where('user_id', $target->id)->value('iban');
    expect($raw)->not->toBe(HR_USER_IBAN)->and($raw)->not->toContain('IT60')
        ->and($target->employment->fresh()->iban)->toBe(HR_USER_IBAN);

    $logged = Activity::query()->get()->toJson();
    expect($logged)->not->toContain(HR_USER_IBAN)->and($logged)->not->toContain($raw);
});

it('0272 AC-008: an iban with a wrong check is 422 and the value is not echoed back', function () {
    $actor = hrUserActor(['view', 'update'], compensation: true);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['iban' => 'IT61X0542811101000000123456'],
    ])->assertStatus(422)->assertJsonValidationErrors(['employment.iban']);

    expect($response->getContent())->not->toContain('IT61X0542811101000000123456')
        ->and($target->employment->fresh()->iban)->toBeNull();
});

it('0272 AC-008: a null iban clears it, an absent one leaves it untouched', function () {
    $actor = hrUserActor(['view', 'update'], compensation: true);
    $target = User::factory()->withEmployment(fn ($f) => $f->state(['iban' => HR_USER_IBAN]))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['covers_all_product_categories' => true, 'iban_holder' => 'Mario Rossi']])->assertOk();
    expect($target->employment->fresh()->iban)->toBe(HR_USER_IBAN);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['covers_all_product_categories' => true, 'iban' => null]])->assertOk();
    expect($target->employment->fresh()->iban)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-009 — relationship_type / company_id / qualification_type are not writable
// ---------------------------------------------------------------------------

it('0272 AC-009: PUT users ignores relationship_type, company_id and qualification_type, and the response has no qualification_type', function () {
    $actor = hrUserActor(['view', 'update']);
    $company = Company::factory()->create(['denomination' => 'Acme Srl']);
    $other = Company::factory()->create();
    $target = User::factory()->withEmployment(fn ($f) => $f->state([
        'relationship_type' => RelationshipTypeEnum::Employee->value,
        'company_id' => $company->id,
    ]))->create();
    Sanctum::actingAs($actor);

    $response = $this->putJson("/api/users/{$target->id}", [
        'employment' => [
            'covers_all_product_categories' => true,
            'relationship_type' => 'other',
            'company_id' => $other->id,
            'qualification_type' => 'coordinator',
            'hired_at' => '2024-01-15',
        ],
    ])->assertOk()
        ->assertJsonPath('data.employment.relationship_type', 'employee')
        ->assertJsonPath('data.employment.company_id', $company->id)
        ->assertJsonPath('data.employment.company.label', 'Acme Srl');

    expect($response->json('data.employment'))->not->toHaveKey('qualification_type');
    $this->assertDatabaseHas('employment_profiles', [
        'user_id' => $target->id,
        'relationship_type' => 'employee',
        'company_id' => $company->id,
    ]);
});

it('0272 AC-009: a new employment block on create ignores relationship_type and company_id', function () {
    $actor = hrUserActor(['create']);
    $company = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'ignored@example.com',
        'locale' => 'it',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => ['type' => 'individual', 'first_name' => 'Ig', 'last_name' => 'Nored'],
        'employment' => ['covers_all_product_categories' => true, 'relationship_type' => 'employee', 'company_id' => $company->id, 'hired_at' => '2024-01-15'],
    ])->assertCreated()->assertJsonPath('data.employment.relationship_type', null);

    $this->assertDatabaseHas('employment_profiles', [
        'user_id' => User::where('email', 'ignored@example.com')->value('id'),
        'relationship_type' => null,
        'company_id' => null,
    ]);
});

it('0272 D-2: the record exposes the condition in force with its CCNL level label', function () {
    $actor = hrUserActor(['view']);
    $target = User::factory()->withEmployment()->create();
    $agreement = CollectiveAgreement::factory()->create(['name' => 'CCNL Commercio']);
    $level = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'Q2']);
    EmploymentContract::factory()->for($target->employment, 'profile')->create(['starts_on' => today()->subYear()->toDateString(), 'collective_agreement_level_id' => null]);
    EmploymentContract::factory()->for($target->employment, 'profile')->create(['starts_on' => today()->subMonth()->toDateString(), 'collective_agreement_level_id' => $level->id]);
    EmploymentContract::factory()->for($target->employment, 'profile')->create(['starts_on' => today()->addMonth()->toDateString(), 'collective_agreement_level_id' => null]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")->assertOk()
        ->assertJsonPath('data.employment.current_contract.starts_on', today()->subMonth()->toDateString())
        ->assertJsonPath('data.employment.current_contract.collective_agreement_level.id', $level->id)
        ->assertJsonPath('data.employment.current_contract.collective_agreement_level.label', 'CCNL Commercio – Q2');
});

it('0272 D-2: current_contract is null without any condition in force', function () {
    $actor = hrUserActor(['view']);
    $target = User::factory()->withEmployment()->create();
    EmploymentContract::factory()->for($target->employment, 'profile')->create(['starts_on' => today()->addMonth()->toDateString()]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")->assertOk()->assertJsonPath('data.employment.current_contract', null);
});

// ---------------------------------------------------------------------------
// AC-011 — contacts is_work
// ---------------------------------------------------------------------------

it('0272 AC-011: a user contact saved with is_work true returns it, absent means false', function () {
    $actor = hrUserActor(['view', 'update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'contacts' => [
                ['type' => 'email', 'value' => 'work@example.com', 'is_work' => true],
                ['type' => 'email', 'value' => 'home@example.com'],
            ],
        ],
    ])->assertOk();

    $byValue = collect($response->json('data.personal_data.contacts'))->keyBy('value');
    expect($byValue['work@example.com']['is_work'])->toBeTrue()
        ->and($byValue['home@example.com']['is_work'])->toBeFalse();
});

it('0272 AC-011: the generic contact endpoint accepts is_work for any owner and an update without it keeps it', function () {
    Permission::findOrCreate('contacts.create');
    Permission::findOrCreate('contacts.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['contacts.create', 'contacts.update']);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $id = $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => 'email',
        'value' => 'work@example.com',
        'is_work' => true,
    ])->assertCreated()->assertJsonPath('data.is_work', true)->json('data.id');

    $this->putJson("/api/contacts/{$id}", [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => 'email',
        'value' => 'work2@example.com',
    ])->assertOk();

    expect(Contact::find($id)->is_work)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-012 (saving part) — termination saves the user inactive
// ---------------------------------------------------------------------------

it('0272 AC-012: saving an active user with terminated_at today or past makes them inactive', function (string $terminatedAt) {
    $actor = hrUserActor(['view', 'update']);
    $target = User::factory()->withEmployment(fn ($f) => $f->state(['hired_at' => '2020-01-01']))->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'is_active' => true,
        'employment' => ['hired_at' => '2020-01-01', 'terminated_at' => $terminatedAt],
    ])->assertOk()->assertJsonPath('data.is_active', false);

    expect($target->fresh()->is_active)->toBeFalse();
})->with([
    'today' => fn () => today()->toDateString(),
    'past' => fn () => today()->subMonth()->toDateString(),
]);

it('0272 AC-012: a future termination date leaves the user active', function () {
    $actor = hrUserActor(['view', 'update']);
    $target = User::factory()->withEmployment(fn ($f) => $f->state(['hired_at' => '2020-01-01']))->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['covers_all_product_categories' => true, 'hired_at' => '2020-01-01', 'terminated_at' => today()->addMonth()->toDateString()],
    ])->assertOk()->assertJsonPath('data.is_active', true);
});

// ---------------------------------------------------------------------------
// AC-013 — grid
// ---------------------------------------------------------------------------

it('0272 AC-013: the users grid has the employment_status column and no qualification_type', function () {
    $actor = hrUserActor(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns)->toHaveKey('employment_status')->and($columns)->not->toHaveKey('qualification_type')
        ->and($columns['employment_status']['filterType'])->toBe('set')
        ->and($columns['employment_status']['options'])->toBe(['employed', 'terminated']);
});

it('0272 AC-013: employment_status is employed or terminated and filters on today', function () {
    $actor = hrUserActor(['viewAny']);
    User::factory()->withEmployment(fn ($f) => $f->state(['terminated_at' => null]))->create(['name' => 'Employed One']);
    User::factory()->withEmployment(fn ($f) => $f->state(['terminated_at' => today()->addMonth()->toDateString()]))->create(['name' => 'Future Leaver']);
    User::factory()->withEmployment(fn ($f) => $f->state(['terminated_at' => today()->toDateString()]))->create(['name' => 'Gone Today']);
    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/users/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items');
    $status = collect($rows)->pluck('employment_status', 'name');
    expect($status['Employed One'])->toBe('employed')
        ->and($status['Future Leaver'])->toBe('employed')
        ->and($status['Gone Today'])->toBe('terminated');

    $names = fn (string $value) => collect($this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 50,
        'filterModel' => ['employment_status' => ['values' => [$value]]],
    ])->assertOk()->json('items'))->pluck('name')->sort()->values()->all();

    expect($names('terminated'))->toBe(['Gone Today'])
        ->and($names('employed'))->toBe(['Employed One', 'Future Leaver']);

    $sorted = collect($this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 50,
        'sortModel' => [['colId' => 'employment_status', 'sort' => 'desc']],
    ])->assertOk()->json('items'))->pluck('employment_status')->filter()->values()->all();
    expect($sorted[0])->toBe('terminated');
});

it('0272 AC-013: UsersTableDefinition stays under 500 lines', function () {
    expect(count(file(app_path('Tables/UsersTableDefinition.php'))))->toBeLessThan(500);
});
