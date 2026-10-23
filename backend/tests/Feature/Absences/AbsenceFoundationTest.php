<?php

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\User;
use App\Policies\AbsenceRequestPolicy;
use App\Policies\AbsenceTypePolicy;
use Database\Seeders\QualificaAbsenceTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('0278 BE-1: the four absence tables exist and roll back cleanly', function () {
    $tables = ['absence_types', 'absence_requests', 'absence_request_approver', 'absence_allowances'];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }

    // The 4 absence migrations plus every later one, so the count survives new migrations.
    $steps = collect(glob(database_path('migrations/*.php')))
        ->filter(fn (string $path): bool => basename($path) >= '2026_10_23_100000_create_absence_types_table.php')
        ->count();

    expect(Artisan::call('migrate:rollback', ['--step' => $steps]))->toBe(0);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse($table);
    }

    expect(Artisan::call('migrate'))->toBe(0)
        ->and(Schema::hasTable('absence_allowances'))->toBeTrue();
});

it('0278 BE-1: the request casts, relations and approvers snapshot work', function () {
    $approver = User::factory()->create();
    $request = AbsenceRequest::factory()->submitted()->create(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-03']);
    $request->approvers()->attach($approver->id);

    $request = $request->fresh();

    expect($request->status)->toBe(AbsenceRequestStatus::Submitted)
        ->and($request->is_full_day)->toBeTrue()
        ->and($request->starts_on->format('Y-m-d'))->toBe('2026-11-02')
        ->and($request->user)->toBeInstanceOf(User::class)
        ->and($request->absenceType)->toBeInstanceOf(AbsenceType::class)
        ->and($request->approvers->pluck('id')->all())->toBe([$approver->id])
        ->and($request->absenceType->absenceRequests()->count())->toBe(1)
        ->and($request->attachments()->count())->toBe(0);

    expect(fn () => $request->approvers()->attach($approver->id))->toThrow(QueryException::class);
});

it('0278 BE-1: an absence type used by a request cannot be hard-deleted at DB level', function () {
    $request = AbsenceRequest::factory()->create();

    expect(fn () => $request->absenceType->delete())->toThrow(QueryException::class);
});

it('0278 BE-1: one allowance per user, type and year', function () {
    $allowance = AbsenceAllowance::factory()->create(['carried_over_minutes' => -120]);

    expect($allowance->fresh()->carried_over_minutes)->toBe(-120)
        ->and(fn () => AbsenceAllowance::factory()->create([
            'user_id' => $allowance->user_id,
            'absence_type_id' => $allowance->absence_type_id,
            'year' => $allowance->year,
        ]))->toThrow(QueryException::class);
});

it('0278 BE-1: the policies declare the permissions and permissions:sync creates them', function () {
    // REQUIREMENT CHANGED (spec 0279 D-1): the calendar levels joined the module; spec 0309 D-1: calendarTeam dropped.
    expect(AbsenceRequestPolicy::abilities())->toBe(['viewAny', 'create', 'manageAll', 'calendarOwn', 'calendarAll'])
        ->and((new AbsenceTypePolicy)->permissions())->toContain(
            'absence-types.view', 'absence-types.create', 'absence-types.update', 'absence-types.delete',
        );

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'like', 'absence-requests.%')->pluck('name')->sort()->values()->all())
        ->toBe([
            'absence-requests.calendarAll', 'absence-requests.calendarOwn',
            'absence-requests.create', 'absence-requests.manageAll', 'absence-requests.viewAny',
        ]);

    $admin = User::factory()->create();
    $this->artisan('roles:create-super-admin')->assertSuccessful();
    $admin->assignRole('super-admin');

    expect($admin->can('absence-requests.manageAll'))->toBeTrue()
        ->and($admin->can('absence-types.view'))->toBeTrue();
});

it('0278 BE-1: the menu shows Assenze after the Segnatempo and the catalogue in Configurazione', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $this->artisan('roles:create-super-admin')->assertSuccessful();
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $routes = visibleRoutes($admin);

    expect($routes)->toContain('/absences', '/absence-types')
        ->and(array_search('/absences', $routes, true))->toBe(array_search('/time-entries', $routes, true) + 1);
});

it('0278 BE-1: the production seeder step is idempotent and sets the three base types', function () {
    $this->seed(QualificaAbsenceTypeSeeder::class);
    AbsenceType::query()->where('name', 'Ferie')->update(['requires_approval' => false]);
    $this->seed(QualificaAbsenceTypeSeeder::class);

    $types = AbsenceType::query()->orderBy('sort_order')->get();

    expect($types)->toHaveCount(3)
        ->and($types->pluck('name')->all())->toBe(['Ferie', 'Permesso', 'Malattia'])
        ->and($types->pluck('deducts_balance')->all())->toBe([true, true, false])
        ->and($types->pluck('requires_approval')->all())->toBe([true, true, false])
        ->and($types->pluck('is_active')->unique()->all())->toBe([true]);
});
