<?php

declare(strict_types=1);

use App\CustomFields\CustomFieldRequestBag;
use App\CustomFields\CustomFieldValidator;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * spec 0021 — T7: the custom-field WRITE pipeline (middleware -> bag ->
 * HasCustomFields -> writer + validator), zero per-module code. Pilot
 * domain: companies. AC-010/AC-011/AC-012/AC-013.
 *
 * Coverage split: the trait/writer/validator triad is ALSO exercised at the
 * MODEL level (bag populated manually, no HTTP) for every AC-011 branch and
 * the AC-012 permission-parity gate — against the REAL AuthorizationRegistry
 * / CustomFieldAwareAuthorization / role_field_permissions stack, just
 * without an HTTP transport — the fastest way to cover every validation rule
 * without a round-trip per case. HTTP-level tests close the loop end-to-end,
 * including BaseApiController::handleControllerException's 422 mapping of a
 * ValidationException raised INSIDE the `saving` observer (see the 422 test
 * below) and the show() read path returning `data.custom_fields`.
 */
uses(RefreshDatabase::class);

function customFieldsBag(): CustomFieldRequestBag
{
    return app(CustomFieldRequestBag::class);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function companyTextField(array $overrides = []): CustomFieldDefinition
{
    return CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create([...['key' => 'notes'], ...$overrides]);
}

if (! function_exists('writePipelineActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function writePipelineActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("companies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("companies.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-010 — persistence
// ---------------------------------------------------------------------------

it('model level: saving a company with a pending bag persists one custom_field_values row', function () {
    companyTextField();
    $company = Company::factory()->create();

    customFieldsBag()->set(['notes' => 'hello world']);
    $company->save();

    $this->assertDatabaseHas('custom_field_values', [
        'entity_type' => 'companies',
        'entity_id' => $company->id,
    ]);
    expect(CustomFieldValue::first()->values)->toBe(['notes' => 'hello world'])
        ->and($company->fresh()->custom_fields)->toBe(['notes' => 'hello world']);
});

it('HTTP: POST /companies with valid custom_fields returns 201 and persists the row', function () {
    companyTextField();
    $actor = writePipelineActor(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/companies', [
        'denomination' => 'Acme Srl',
        'custom_fields' => ['notes' => 'a note'],
    ])->assertCreated();

    $companyId = $response->json('data.id');

    $this->assertDatabaseHas('custom_field_values', [
        'entity_type' => 'companies',
        'entity_id' => $companyId,
    ]);
    expect(Company::find($companyId)->custom_fields)->toBe(['notes' => 'a note']);
});

it('HTTP: GET /companies/{id} returns data.custom_fields with the persisted values', function () {
    companyTextField();
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'headcount']);
    $actor = writePipelineActor(['view']);
    $target = Company::factory()->create();
    CustomFieldValue::create(['entity_type' => 'companies', 'entity_id' => $target->id, 'values' => ['notes' => 'from db', 'headcount' => 7]]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/companies/{$target->id}")->assertOk();

    expect($response->json('data.custom_fields'))->toBe(['notes' => 'from db', 'headcount' => 7]);
});

// ---------------------------------------------------------------------------
// AC-011 — validation
// ---------------------------------------------------------------------------

it('HTTP: POST /companies with an invalid custom field value returns 422 keyed custom_fields.<key>, not 500', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'headcount']);
    $actor = writePipelineActor(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/companies', [
        'denomination' => 'Bad Custom Field Srl',
        'custom_fields' => ['headcount' => 'not-a-number'],
    ]);

    $response->assertStatus(422);
    expect($response->json('success'))->toBeFalse()
        ->and($response->json('errors'))->toHaveKey('custom_fields.headcount');
    $this->assertDatabaseMissing('companies', ['denomination' => 'Bad Custom Field Srl']);
});

it('required missing on create throws a ValidationException keyed custom_fields.<key>', function () {
    companyTextField(['validation' => ['required' => true]]);
    $company = Company::factory()->make(['denomination' => 'Missing Required Srl']);

    customFieldsBag()->set(['unrelated' => 'x']);

    try {
        $company->save();
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('custom_fields.notes');
    }

    $this->assertDatabaseMissing('companies', ['denomination' => 'Missing Required Srl']);
});

it('enum value outside its options fails validation', function () {
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create([
        'key' => 'segment',
        'config' => ['display' => 'select'],
    ]);
    $definition->options()->create(['value' => 'retail', 'label' => 'Retail']);
    $company = Company::factory()->create();

    customFieldsBag()->set(['segment' => 'not-an-option']);

    expect(fn () => $company->save())->toThrow(ValidationException::class);
});

it('relation id that does not exist fails validation', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('relation')->create([
        'key' => 'parent_company',
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies'],
    ]);
    $company = Company::factory()->create();

    customFieldsBag()->set(['parent_company' => 999999]);

    expect(fn () => $company->save())->toThrow(ValidationException::class);
});

it('type mismatch (text submitted for an integer field) fails validation', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'headcount']);
    $company = Company::factory()->create();

    customFieldsBag()->set(['headcount' => 'not-a-number']);

    expect(fn () => $company->save())->toThrow(ValidationException::class);
});

it('text minLength violation fails validation', function () {
    companyTextField(['config' => ['minLength' => 5]]);
    $company = Company::factory()->create();

    customFieldsBag()->set(['notes' => 'ab']);

    expect(fn () => $company->save())->toThrow(ValidationException::class);
});

it('valid values save without throwing', function () {
    companyTextField();
    $company = Company::factory()->create();

    customFieldsBag()->set(['notes' => 'fine']);

    expect(fn () => $company->save())->not->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// New scalar-string types: date / datetime / time / email / url / color.
// One definition + company per dataset case (fresh DB each) so no bag state
// leaks between the interleaved saves.
// ---------------------------------------------------------------------------

it('rejects a malformed value for each scalar-string type', function (string $type, string $badValue) {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType($type)->create(['key' => 'field']);
    $company = Company::factory()->create();

    customFieldsBag()->set(['field' => $badValue]);

    expect(fn () => $company->save())->toThrow(ValidationException::class);
})->with([
    'date' => ['date', '09/07/2026'],
    'datetime' => ['datetime', '2026-07-09 14:30'],
    'time' => ['time', '25:00'],
    'email' => ['email', 'not-an-email'],
    'url' => ['url', 'not a url'],
    'color' => ['color', 'red'],
]);

it('accepts a well-formed value for each scalar-string type', function (string $type, string $goodValue) {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType($type)->create(['key' => 'field']);
    $company = Company::factory()->create();

    customFieldsBag()->set(['field' => $goodValue]);

    expect(fn () => $company->save())->not->toThrow(ValidationException::class);
})->with([
    'date' => ['date', '2026-07-09'],
    'datetime (no seconds)' => ['datetime', '2026-07-09T14:30'],
    'datetime (seconds)' => ['datetime', '2026-07-09T14:30:00'],
    'time' => ['time', '09:00'],
    'email' => ['email', 'ops@example.com'],
    'url' => ['url', 'https://example.com'],
    'color' => ['color', '#1A2B3C'],
]);

// ---------------------------------------------------------------------------
// AC-012 — permission parity + PATCH partial merge
// ---------------------------------------------------------------------------

it('a custom field made non-editable by the role matrix rejects a changed value (parity with EnforcesFieldPermissions)', function () {
    companyTextField();

    $role = Role::create(['name' => 'notes-readonly']);
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }
    $role->givePermissionTo(['companies.viewAny', 'companies.update']);
    $role->fieldPermissions()->create(['resource' => 'companies', 'field' => 'custom.notes', 'visible' => true, 'editable' => false, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    $company = Company::factory()->create();

    try {
        app(CustomFieldValidator::class)->validate($company, ['notes' => 'changed'], $actor);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('custom_fields.notes');
    }
});

it('a non-editable custom field re-submitted UNCHANGED passes (change-based gate, spec 0008 parity)', function () {
    companyTextField();

    $role = Role::create(['name' => 'notes-readonly-unchanged']);
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }
    $role->givePermissionTo(['companies.viewAny', 'companies.update']);
    $role->fieldPermissions()->create(['resource' => 'companies', 'field' => 'custom.notes', 'visible' => true, 'editable' => false, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    $company = Company::factory()->create();
    CustomFieldValue::create(['entity_type' => 'companies', 'entity_id' => $company->id, 'values' => ['notes' => 'already there']]);

    app(CustomFieldValidator::class)->validate($company, ['notes' => 'already there'], $actor);
})->throwsNoExceptions();

it('PATCH partial merge (model level): writing a subset of keys leaves the other previously-stored key intact', function () {
    companyTextField();
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'headcount']);
    $company = Company::factory()->create();

    customFieldsBag()->set(['notes' => 'first', 'headcount' => 10]);
    $company->save();

    customFieldsBag()->set(['headcount' => 20]);
    $company->save();

    expect(CustomFieldValue::first()->values)->toBe(['notes' => 'first', 'headcount' => 20]);
});

it('HTTP: PATCH partial merge leaves other custom field keys untouched', function () {
    companyTextField();
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'headcount']);
    $actor = writePipelineActor(['update']);
    $target = Company::factory()->create();
    CustomFieldValue::create(['entity_type' => 'companies', 'entity_id' => $target->id, 'values' => ['notes' => 'kept', 'headcount' => 1]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", [
        'vat_number' => 'IT99988877769',
        'custom_fields' => ['headcount' => 42],
    ])->assertOk();

    expect(CustomFieldValue::first()->values)->toBe(['notes' => 'kept', 'headcount' => 42]);
});

it('single-primary-entity assumption: pull() empties the bag so a later save in the same scope does not re-consume it', function () {
    companyTextField();
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    customFieldsBag()->set(['notes' => 'only for the first']);
    $first->save();
    $second->save();

    expect(CustomFieldValue::where('entity_id', $first->id)->first()->values)->toBe(['notes' => 'only for the first'])
        ->and(CustomFieldValue::where('entity_id', $second->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-013 — cleanup on entity delete
// ---------------------------------------------------------------------------

it('deleting a company purges its custom_field_values row (model level)', function () {
    companyTextField();
    $company = Company::factory()->create();
    customFieldsBag()->set(['notes' => 'to be purged']);
    $company->save();

    $company->delete();

    $this->assertDatabaseMissing('custom_field_values', ['entity_type' => 'companies', 'entity_id' => $company->id]);
});

it('HTTP: DELETE /companies/{company} removes its custom_field_values row', function () {
    companyTextField();
    $actor = writePipelineActor(['delete']);
    $target = Company::factory()->create();
    CustomFieldValue::create(['entity_type' => 'companies', 'entity_id' => $target->id, 'values' => ['notes' => 'x']]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/companies/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('custom_field_values', ['entity_type' => 'companies', 'entity_id' => $target->id]);
});
