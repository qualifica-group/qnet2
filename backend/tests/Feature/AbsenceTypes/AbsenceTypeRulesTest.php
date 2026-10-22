<?php

use App\Models\AbsenceType;
use App\Models\User;
use Database\Seeders\QualificaAbsenceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const ABSENCE_RULES_PAYLOAD = ['name' => 'Ferie', 'deducts_balance' => true, 'requires_approval' => true];

it('0304 AC-001: the new fields are saved and read back', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['create', 'view', 'update']));

    $id = $this->postJson('/api/absence-types', [
        ...ABSENCE_RULES_PAYLOAD,
        'allows_full_day' => true, 'allows_half_day' => true, 'allows_hourly' => false,
        'min_notice_days' => 7, 'color' => 'blue', 'default_annual_days' => 26.5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.allows_hourly', false)
        ->assertJsonPath('data.min_notice_days', 7)
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.default_annual_days', 26.5)
        ->json('data.id');

    $this->patchJson("/api/absence-types/{$id}", ['min_notice_days' => null, 'color' => 'red', 'default_annual_days' => null])
        ->assertOk()
        ->assertJsonPath('data.min_notice_days', null)
        ->assertJsonPath('data.color', 'red')
        ->assertJsonPath('data.default_annual_days', null);
});

it('0304 AC-001: a new type takes every mode allowed and slate by default', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['create']));

    $this->postJson('/api/absence-types', ABSENCE_RULES_PAYLOAD)
        ->assertCreated()
        ->assertJsonPath('data.allows_full_day', true)
        ->assertJsonPath('data.allows_half_day', true)
        ->assertJsonPath('data.allows_hourly', true)
        ->assertJsonPath('data.color', 'slate')
        ->assertJsonPath('data.min_notice_days', null);
});

it('0304 AC-001: no allowed mode is 422, also on a partial update', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['create', 'update']));
    $type = AbsenceType::factory()->create(['allows_full_day' => true, 'allows_half_day' => false, 'allows_hourly' => false]);

    $this->postJson('/api/absence-types', [...ABSENCE_RULES_PAYLOAD, 'allows_full_day' => false, 'allows_half_day' => false, 'allows_hourly' => false])
        ->assertStatus(422)->assertJsonValidationErrors('allows_full_day');
    $this->patchJson("/api/absence-types/{$type->id}", ['allows_full_day' => false])
        ->assertStatus(422)->assertJsonValidationErrors('allows_full_day');
    $this->patchJson("/api/absence-types/{$type->id}", ['allows_full_day' => false, 'allows_hourly' => true])->assertOk();
});

it('0304 AC-001: a default allowance on a type that does not deduct the balance is 422', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['create', 'update']));
    $sick = AbsenceType::factory()->notDeducting()->create();

    $this->postJson('/api/absence-types', [...ABSENCE_RULES_PAYLOAD, 'deducts_balance' => false, 'default_annual_days' => 5])
        ->assertStatus(422)->assertJsonValidationErrors('default_annual_days');
    $this->patchJson("/api/absence-types/{$sick->id}", ['default_annual_days' => 5])
        ->assertStatus(422)->assertJsonValidationErrors('default_annual_days');
    $this->patchJson("/api/absence-types/{$sick->id}", ['deducts_balance' => true, 'default_annual_days' => 5])->assertOk();
});

it('0304 AC-001: color outside the palette, notice and days out of range are 422', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['create']));

    foreach ([['color' => 'pink'], ['min_notice_days' => 366], ['min_notice_days' => -1], ['default_annual_days' => 367], ['default_annual_days' => 1.234]] as $invalid) {
        $this->postJson('/api/absence-types', [...ABSENCE_RULES_PAYLOAD, ...$invalid])
            ->assertStatus(422)->assertJsonValidationErrors(array_key_first($invalid));
    }
});

it('0304 AC-001: for-select exposes modes, notice and color', function () {
    AbsenceType::factory()->create(['name' => 'Permesso', 'allows_full_day' => false, 'min_notice_days' => 3, 'color' => 'violet']);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/absence-types/for-select')->assertOk()
        ->assertJsonPath('data.0.allows_full_day', false)
        ->assertJsonPath('data.0.allows_half_day', true)
        ->assertJsonPath('data.0.allows_hourly', true)
        ->assertJsonPath('data.0.min_notice_days', 3)
        ->assertJsonPath('data.0.color', 'violet');
});

it('0304 AC-001: the grid carries the new columns and rows', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['viewAny']));
    AbsenceType::factory()->create(['color' => 'green', 'default_annual_days' => 26, 'min_notice_days' => 2]);

    $columns = collect($this->getJson('/api/tables/absence-types/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/absence-types/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($columns)->toContain('allows_full_day', 'allows_half_day', 'allows_hourly', 'min_notice_days', 'color', 'default_annual_days')
        ->and($row)->toMatchArray(['color' => 'green', 'min_notice_days' => 2, 'default_annual_days' => 26.0, 'allows_hourly' => true]);
});

it('0304 AC-001: existing types keep every mode and slate after the migration, and the seed sets modes and colors', function () {
    $migration = require database_path('migrations/2026_10_31_100700_add_rules_to_absence_types_table.php');
    $migration->down();
    AbsenceType::query()->insert(['name' => 'Old', 'deducts_balance' => true, 'requires_approval' => true, 'created_at' => now(), 'updated_at' => now()]);
    $migration->up();

    $old = AbsenceType::query()->where('name', 'Old')->firstOrFail();
    expect($old->allows_full_day && $old->allows_half_day && $old->allows_hourly)->toBeTrue()
        ->and($old->color)->toBe('slate')
        ->and($old->min_notice_days)->toBeNull()
        ->and($old->default_annual_days)->toBeNull();

    $this->seed(QualificaAbsenceTypeSeeder::class);
    $permission = AbsenceType::query()->where('name', 'Permesso')->firstOrFail();

    expect($permission->allows_full_day)->toBeFalse()
        ->and($permission->allows_hourly)->toBeTrue()
        ->and($permission->color)->toBe('violet')
        ->and(AbsenceType::query()->where('name', 'Ferie')->value('color'))->toBe('blue');
});

it('0304 B1: the closure, request and allowance migrations roll back and re-apply', function () {
    $closures = require database_path('migrations/2026_10_31_100600_create_company_closures_tables.php');
    $requests = require database_path('migrations/2026_10_31_100800_add_half_day_notice_and_closure_to_absence_requests_table.php');
    $allowances = require database_path('migrations/2026_10_31_100900_make_annual_minutes_nullable_on_absence_allowances_table.php');

    $allowances->down();
    $requests->down();
    expect(Schema::hasColumn('absence_requests', 'day_part'))->toBeFalse()
        ->and(Schema::hasColumn('absence_requests', 'company_closure_id'))->toBeFalse();
    $closures->down();
    expect(Schema::hasTable('company_closures'))->toBeFalse()
        ->and(Schema::hasTable('company_closure_operational_site'))->toBeFalse();

    $closures->up();
    $requests->up();
    $allowances->up();
    expect(Schema::hasColumns('absence_requests', ['day_part', 'is_short_notice', 'company_closure_id']))->toBeTrue()
        ->and(Artisan::call('migrate:status'))->toBe(0);
});
