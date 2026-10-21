<?php

use App\Models\EmploymentContract;
use App\Models\TableFilterView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Spec 0293: the CCNL module is removed, data included.

const DROP_COLLECTIVE_AGREEMENTS_MIGRATION = '2026_10_30_100000_drop_collective_agreements';

it('0293 AC-001: drops the tables and the contract column, and the state saved under the domain', function () {
    $migration = require database_path('migrations/'.DROP_COLLECTIVE_AGREEMENTS_MIGRATION.'.php');
    $migration->down();

    $user = User::factory()->create();
    $role = Role::findOrCreate('hr-test', 'web');
    $role->givePermissionTo(Permission::findOrCreate('collective-agreements.view', 'web'));
    $kept = Permission::findOrCreate('users.view', 'web');
    $agreementId = DB::table('collective_agreements')->insertGetId(['name' => 'Commercio']);
    $levelId = DB::table('collective_agreement_levels')->insertGetId(['collective_agreement_id' => $agreementId, 'code' => 'Q1']);
    $contract = EmploymentContract::factory()->create();
    DB::table('employment_contracts')->where('id', $contract->id)->update(['collective_agreement_level_id' => $levelId]);
    DB::table('role_field_permissions')->insert(['role_id' => $role->id, 'resource' => 'collective-agreements', 'field' => 'code']);
    DB::table('user_table_preferences')->insert(['user_id' => $user->id, 'domain' => 'collective-agreements', 'preferences' => '{}']);
    DB::table('user_table_filters')->insert(['user_id' => $user->id, 'domain' => 'collective-agreements', 'filters' => '{}']);
    $view = TableFilterView::factory()->create(['user_id' => $user->id, 'domain' => 'collective-agreements', 'visibility' => 'private']);
    $otherView = TableFilterView::factory()->create(['user_id' => $user->id, 'domain' => 'users', 'visibility' => 'private']);
    DB::table('activity_log')->insert(['log_name' => 'collective_agreements', 'description' => 'created', 'subject_type' => 'collective_agreement', 'subject_id' => $agreementId]);

    $migration->up();

    expect(Schema::hasTable('collective_agreements'))->toBeFalse()
        ->and(Schema::hasTable('collective_agreement_levels'))->toBeFalse()
        ->and(Schema::hasColumn('employment_contracts', 'collective_agreement_level_id'))->toBeFalse()
        ->and($contract->fresh())->not->toBeNull()
        ->and(DB::table('permissions')->where('name', 'like', 'collective-agreements.%')->exists())->toBeFalse()
        ->and(DB::table('permissions')->where('id', $kept->id)->exists())->toBeTrue()
        ->and(DB::table('role_field_permissions')->where('resource', 'collective-agreements')->exists())->toBeFalse()
        ->and(DB::table('user_table_preferences')->where('domain', 'collective-agreements')->exists())->toBeFalse()
        ->and(DB::table('user_table_filters')->where('domain', 'collective-agreements')->exists())->toBeFalse()
        ->and(TableFilterView::query()->whereKey($view->id)->exists())->toBeFalse()
        ->and(TableFilterView::query()->whereKey($otherView->id)->exists())->toBeTrue()
        ->and(DB::table('activity_log')->where('subject_type', 'collective_agreement')->exists())->toBeFalse()
        ->and($role->fresh()->permissions)->toHaveCount(0);
});

it('0293 AC-002: the module endpoints and table domain are gone', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/collective-agreements/1')->assertNotFound();
    $this->postJson('/api/collective-agreements', ['name' => 'Commercio'])->assertNotFound();
    $this->getJson('/api/collective-agreement-levels/for-select')->assertNotFound();

    expect(config('tables.definitions'))->not->toHaveKey('collective-agreements')
        ->and(config('authorization.definitions'))->not->toHaveKey('collective-agreements');
});
