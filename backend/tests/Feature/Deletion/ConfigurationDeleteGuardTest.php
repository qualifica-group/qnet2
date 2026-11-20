<?php

use App\Models\Attribute;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\EmailTemplate;
use App\Models\JobRole;
use App\Models\OutboundEmail;
use App\Models\Product;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Skill;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(deletionSuperAdmin()));

it('0384 AC-001: a sector linked to a registry is refused and the link stays', function () {
    $sector = Sector::factory()->create();
    $registry = Registry::factory()->create();
    $registry->sectors()->attach($sector->id);

    $this->deleteJson("/api/sectors/{$sector->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('deletion.in_use', ['usages' => '1 registry']));

    $this->assertDatabaseHas('sectors', ['id' => $sector->id]);
    $this->assertDatabaseHas('sector_registry', ['sector_id' => $sector->id, 'registry_id' => $registry->id]);
});

it('0384 AC-002: a role assigned to a user is refused and the user keeps it', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->deleteJson("/api/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('deletion.in_use', ['usages' => '1 user']));

    expect($user->fresh()->hasRole($role->name))->toBeTrue();
});

it('0384 AC-003: a VAT rate used only by a work order cost is refused and the cost keeps it', function () {
    $rate = VatRate::factory()->create();
    $cost = WorkOrderCost::factory()->create(['vat_rate_id' => $rate->id]);

    $this->deleteJson("/api/vat-rates/{$rate->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('deletion.in_use', ['usages' => '1 work order cost']));

    expect($cost->fresh()->vat_rate_id)->toBe($rate->id);
});

it('0384 AC-004: an email template referenced by an outbound email is refused', function () {
    $template = EmailTemplate::factory()->create();
    OutboundEmail::factory()->create(['email_template_id' => $template->id]);

    $this->deleteJson("/api/email-templates/{$template->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('deletion.in_use', ['usages' => '1 sent email']));

    $this->assertDatabaseHas('email_templates', ['id' => $template->id]);
});

it('0384 AC-005: a custom field with a recorded value is refused and the value stays; without values it is deleted', function () {
    $used = CustomFieldDefinition::factory()->create(['entity_type' => 'companies', 'key' => 'vat_note']);
    $free = CustomFieldDefinition::factory()->create(['entity_type' => 'companies', 'key' => 'unused_note']);
    CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => ['vat_note' => 'x']]);

    $this->deleteJson("/api/custom-fields/{$used->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('deletion.in_use', ['usages' => '1 record with a value']));
    $this->assertDatabaseHas('custom_field_definitions', ['id' => $used->id]);
    expect(CustomFieldValue::query()->first()->values)->toBe(['vat_note' => 'x']);

    $this->deleteJson("/api/custom-fields/{$free->id}")->assertNoContent();
    $this->assertDatabaseMissing('custom_field_definitions', ['id' => $free->id]);
});

it('0384 AC-006: an attribute with a value on a product is refused', function () {
    $attribute = Attribute::factory()->create(['code' => 'color']);
    Product::factory()->create(['attribute_values' => ['color' => 'red']]);

    $this->deleteJson("/api/attributes/{$attribute->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('deletion.in_use', ['usages' => '1 product']));

    $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
});

it('0384 AC-006: an unused attribute is deleted', function () {
    $attribute = Attribute::factory()->create(['code' => 'size']);
    Product::factory()->create(['attribute_values' => ['color' => 'red']]);

    $this->deleteJson("/api/attributes/{$attribute->id}")->assertNoContent();
    $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
});

it('0384 AC-007: an unused role is deleted together with its permissions', function () {
    $role = Role::factory()->create();
    $role->givePermissionTo(Permission::findOrCreate('sectors.view', 'web'));

    $this->deleteJson("/api/roles/{$role->id}")->assertNoContent();

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
});

it('0384 AC-007: an unused job role is deleted together with its skill links', function () {
    $jobRole = JobRole::factory()->create();
    $skill = Skill::factory()->create();
    $skill->jobRoles()->attach($jobRole->id, ['min_level' => 2]);

    $this->deleteJson("/api/job-roles/{$jobRole->id}")->assertNoContent();

    $this->assertDatabaseMissing('job_roles', ['id' => $jobRole->id]);
    $this->assertDatabaseMissing('job_role_skill', ['job_role_id' => $jobRole->id]);
    $this->assertDatabaseHas('skills', ['id' => $skill->id]);
});

it('0384 AC-008: the bulk delete of an in-use row does not delete it nor count it', function () {
    $inUse = Sector::factory()->create();
    Registry::factory()->create()->sectors()->attach($inUse->id);
    $free = Sector::factory()->create();

    $response = $this->postJson('/api/tables/sectors/bulk-delete', ['ids' => [$inUse->id, $free->id]])->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and($response->json('data.failed'))->toBe([['id' => $inUse->id, 'reason' => 'guarded']]);
    $this->assertDatabaseHas('sectors', ['id' => $inUse->id]);
    $this->assertDatabaseMissing('sectors', ['id' => $free->id]);
});
