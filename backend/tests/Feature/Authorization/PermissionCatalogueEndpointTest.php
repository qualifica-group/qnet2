<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\CustomFieldDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

// spec 0076 — GET /api/authorization/permission-catalogue. Every test seeds
// the REAL production permission catalogue (`permissions:sync`, derived from
// config/navigation.php + every Policy) so AC-004 can compare the endpoint's
// output against the exact same source of truth the app ships with.
uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

if (! function_exists('actorWithRolesAbility')) {
    function actorWithRolesAbility(?string $ability): User
    {
        $user = User::factory()->create();

        if ($ability !== null) {
            $user->givePermissionTo("roles.{$ability}");
        }

        return $user;
    }
}

const ENDPOINT = '/api/authorization/permission-catalogue';

it('AC-001: 401 without authentication', function () {
    $this->getJson(ENDPOINT)->assertUnauthorized();
});

it('AC-002: 403 without roles.viewAny/create/update', function () {
    Sanctum::actingAs(actorWithRolesAbility(null));

    $this->getJson(ENDPOINT)->assertForbidden();
});

it('200 with roles.viewAny only', function () {
    Sanctum::actingAs(actorWithRolesAbility('viewAny'));

    $this->getJson(ENDPOINT)->assertOk();
});

it('200 with roles.create only', function () {
    Sanctum::actingAs(actorWithRolesAbility('create'));

    $this->getJson(ENDPOINT)->assertOk();
});

it('200 with roles.update only', function () {
    Sanctum::actingAs(actorWithRolesAbility('update'));

    $this->getJson(ENDPOINT)->assertOk();
});

it('AC-003: 200 with the {success, message, data.areas} envelope', function () {
    Sanctum::actingAs(actorWithRolesAbility('viewAny'));

    $response = $this->getJson(ENDPOINT)->assertOk();

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('message'))->toBe('OK')
        ->and($response->json('data.areas'))->toBeArray()
        ->and($response->json('data.areas'))->not->toBeEmpty();
});

it('AC-004: the union of permissions.name equals AssignablePermissionCatalogue::names(), no duplicates', function () {
    Sanctum::actingAs(actorWithRolesAbility('viewAny'));

    $areas = collect($this->getJson(ENDPOINT)->assertOk()->json('data.areas'));

    $names = $areas
        ->flatMap(fn (array $area): array => $area['resources'])
        ->flatMap(fn (array $resource): array => $resource['permissions'])
        ->pluck('name')
        ->all();

    expect($names)->toHaveCount(count(array_unique($names)));

    $expected = app(AssignablePermissionCatalogue::class)->names();
    expect(collect($names)->sort()->values()->all())->toEqual(collect($expected)->sort()->values()->all());
});

it('AC-005: no area exposes an addresses/contacts/personal_data module', function () {
    Sanctum::actingAs(actorWithRolesAbility('viewAny'));

    $areas = collect($this->getJson(ENDPOINT)->assertOk()->json('data.areas'));

    $resources = $areas->flatMap(fn (array $area): array => $area['resources'])->pluck('resource');

    expect($resources->all())->not->toContain('addresses', 'contacts', 'personal_data');
});

it('AC-006: the "shared" area exists with exactly notes and attachments, fields: []', function () {
    Sanctum::actingAs(actorWithRolesAbility('viewAny'));

    $areas = collect($this->getJson(ENDPOINT)->assertOk()->json('data.areas'));

    $shared = $areas->firstWhere('key', 'shared');

    expect($shared)->not->toBeNull()
        ->and($shared['label_key'])->toBe('permissions.areas.shared')
        ->and(collect($shared['resources'])->pluck('resource')->all())->toEqualCanonicalizing(['notes', 'attachments']);

    foreach ($shared['resources'] as $resource) {
        expect($resource['fields'])->toBe([]);
    }
});

it('AC-007: leads is in canonical ability order; contracts appends its extra actions alphabetically', function () {
    Sanctum::actingAs(actorWithRolesAbility('viewAny'));

    $areas = collect($this->getJson(ENDPOINT)->assertOk()->json('data.areas'));
    $resources = $areas->flatMap(fn (array $area): array => $area['resources'])->keyBy('resource');

    $leadsAbilities = collect($resources['leads']['permissions'])->pluck('ability')->all();
    expect($leadsAbilities)->toBe(['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity']);

    // ContractPolicy drops create/delete/import and adds validate/terminate/
    // schedule/changeStatus/reactivate (spec 0072): canonical first, then the
    // extras alphabetically.
    $contractsAbilities = collect($resources['contracts']['permissions'])->pluck('ability')->all();
    expect($contractsAbilities)->toBe([
        'viewAny', 'view', 'update', 'export', 'viewActivity',
        'changeStatus', 'reactivate', 'schedule', 'terminate', 'validate',
    ]);
});

it('AC-008: an active custom field on leads appears as custom.<key> with its admin label; native fields carry label: null; disabling it removes it', function () {
    $definition = CustomFieldDefinition::factory()->forEntity('leads')->ofType('text')
        ->create(['key' => 'budget', 'label' => 'Budget stimato']);

    $actor = actorWithRolesAbility('viewAny');
    $actor->givePermissionTo('custom-fields.update');
    Sanctum::actingAs($actor);

    $areas = collect($this->getJson(ENDPOINT)->assertOk()->json('data.areas'));
    $leads = $areas->flatMap(fn (array $area): array => $area['resources'])->firstWhere('resource', 'leads');

    $fields = collect($leads['fields'])->keyBy('key');
    expect($fields->has('custom.budget'))->toBeTrue()
        ->and($fields['custom.budget']['custom'])->toBeTrue()
        ->and($fields['custom.budget']['label'])->toBe('Budget stimato')
        ->and($fields['registry_id']['custom'])->toBeFalse()
        ->and($fields['registry_id']['label'])->toBeNull();

    // Deactivate through the real write path (PUT /custom-fields/{id}), not a
    // direct model mutation: CustomFieldService busts CustomFieldProvider's
    // request-scoped memo (App\CustomFields\CustomFieldProvider), the same
    // way production invalidates the read side after an admin edit.
    $this->putJson("/api/custom-fields/{$definition->id}", ['is_active' => false])->assertOk();

    $areas = collect($this->getJson(ENDPOINT)->assertOk()->json('data.areas'));
    $leads = $areas->flatMap(fn (array $area): array => $area['resources'])->firstWhere('resource', 'leads');

    expect(collect($leads['fields'])->pluck('key')->contains('custom.budget'))->toBeFalse();
});
