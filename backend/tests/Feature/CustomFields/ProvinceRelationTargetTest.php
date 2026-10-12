<?php

use App\CustomFields\CustomFieldRequestBag;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0225 — AC-003/AC-004/AC-005: provinces as a relation-only target of a
// RELATION custom field or attribute, never a custom-fieldable host.
uses(RefreshDatabase::class);

const PROVINCE_RELATION_TARGET = ['entity_type' => 'provinces', 'cardinality' => 'one', 'for_select_resource' => 'provinces'];

function provinceTargetActor(string $permission): User
{
    Permission::findOrCreate($permission);
    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    return $user;
}

it('relation-targets: lists the custom-fieldable modules followed by provinces', function () {
    Sanctum::actingAs(provinceTargetActor('custom-fields.viewAny'));

    $entityTypes = collect($this->getJson('/api/custom-fields/relation-targets')->assertOk()->json('data'))->pluck('entity_type');

    expect($entityTypes)->toContain('companies')
        ->and($entityTypes->last())->toBe('provinces');
    $this->getJson('/api/custom-fields/relation-targets')
        ->assertJsonFragment(['entity_type' => 'provinces', 'label' => 'customFields.entities.provinces']);
});

it('relation-targets: 403 without custom-fields.viewAny', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/custom-fields/relation-targets')->assertForbidden();
});

it('entities: provinces is not a custom-fieldable host', function () {
    Sanctum::actingAs(provinceTargetActor('custom-fields.viewAny'));

    $entityTypes = collect($this->getJson('/api/custom-fields/entities')->assertOk()->json('data'))->pluck('entity_type');

    expect($entityTypes)->not->toContain('provinces');
});

it('custom field: a relation to provinces is accepted, a field ON provinces is rejected', function () {
    Sanctum::actingAs(provinceTargetActor('custom-fields.create'));

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'province', 'type' => 'relation', 'label' => 'Province',
        'relation_target' => PROVINCE_RELATION_TARGET,
    ])->assertCreated()->assertJsonPath('data.relation_target.entity_type', 'provinces');

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'provinces', 'key' => 'notes', 'type' => 'text', 'label' => 'Notes',
    ])->assertStatus(422)->assertJsonValidationErrors('entity_type');
});

it('attribute: a relation to provinces is accepted', function () {
    Sanctum::actingAs(provinceTargetActor('attributes.create'));

    $this->postJson('/api/attributes', [
        'code' => 'province_id', 'name' => 'Provincia', 'type' => 'relation',
        'relation_target' => PROVINCE_RELATION_TARGET,
    ])->assertCreated()->assertJsonPath('data.relation_target.entity_type', 'provinces');
});

it('value: an existing province id is stored, a missing one fails validation', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('relation')->create([
        'key' => 'province', 'relation_target' => PROVINCE_RELATION_TARGET,
    ]);
    $province = Province::factory()->create();
    $company = Company::factory()->create();
    $bag = app(CustomFieldRequestBag::class);

    $bag->set(['province' => 999999]);
    expect(fn () => $company->save())->toThrow(ValidationException::class);

    $bag->set(['province' => $province->id]);
    $company->save();

    $stored = CustomFieldValue::query()->where('entity_type', 'companies')->where('entity_id', $company->id)->first();
    expect($stored->values['province'])->toBe($province->id);
});
