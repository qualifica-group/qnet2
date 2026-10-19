<?php

use App\Enums\SalaryPeriodEnum;
use App\Models\EmploymentContract;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ManagementControl\LaborCostSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0277 — the hourly cost derived from the salary of the contract condition.
uses(RefreshDatabase::class);

if (! function_exists('salaryCostViewer')) {
    function salaryCostViewer(bool $compensation): User
    {
        $abilities = $compensation ? ['users.view', 'users.manageCompensation'] : ['users.view'];

        foreach ($abilities as $ability) {
            Permission::findOrCreate($ability);
        }

        return User::factory()->create()->givePermissionTo($abilities);
    }
}

it('AC-001: the hourly cost of a condition follows its salary period, rounded to the cent', function (?string $amount, ?SalaryPeriodEnum $period, ?string $expected) {
    $contract = new EmploymentContract(['salary_amount' => $amount, 'salary_period' => $period]);

    expect($contract->hourlyCost())->toBe($expected);
})->with([
    'hourly as is' => ['30.00', SalaryPeriodEnum::Hourly, '30.00'],
    'monthly / 173' => ['2595.00', SalaryPeriodEnum::Monthly, '15.00'],
    'yearly / (173 x 12)' => ['31140.00', SalaryPeriodEnum::Yearly, '15.00'],
    'monthly rounded' => ['2500.00', SalaryPeriodEnum::Monthly, '14.45'],
    'no salary' => [null, null, null],
]);

it('AC-001: the monthly divisor comes from the config', function () {
    config(['employment.monthly_salary_hours' => 160]);

    expect((new EmploymentContract(['salary_amount' => '3200.00', 'salary_period' => SalaryPeriodEnum::Monthly]))->hourlyCost())->toBe('20.00');
});

it('AC-002: each hour is valued with the condition in force on its date, uncovered hours stay unpriced', function () {
    $user = User::factory()->withEmployment()->create();
    EmploymentContract::factory()->for($user->employment, 'profile')->withSalary('20.00', SalaryPeriodEnum::Hourly)->create(['starts_on' => '2026-02-01']);
    EmploymentContract::factory()->for($user->employment, 'profile')->withSalary('5190.00', SalaryPeriodEnum::Monthly)->create(['starts_on' => '2026-03-01']);
    EmploymentContract::factory()->for($user->employment, 'profile')->create(['starts_on' => '2026-04-01']);

    foreach (['2026-01-31' => 60, '2026-02-28' => 60, '2026-03-01' => 30, '2026-04-02' => 45] as $date => $minutes) {
        TimeEntry::factory()->create(['user_id' => $user->id, 'work_order_id' => null, 'minutes' => $minutes, 'date' => $date]);
    }

    $months = collect(app(LaborCostSource::class)->byMonth('2026-01-01', '2026-05-01'))->keyBy('month');

    // January: before the first condition; February: 20 €/h; March: 5190 / 173 = 30 €/h; April: no salary.
    expect($months['2026-01'])->toMatchArray(['cents' => 0, 'minutes' => 60, 'unpriced_minutes' => 60])
        ->and($months['2026-02'])->toMatchArray(['cents' => 2000, 'minutes' => 60, 'unpriced_minutes' => 0])
        ->and($months['2026-03'])->toMatchArray(['cents' => 1500, 'minutes' => 30, 'unpriced_minutes' => 0])
        ->and($months['2026-04'])->toMatchArray(['cents' => 0, 'minutes' => 45, 'unpriced_minutes' => 45]);
});

it('AC-004: the manual column is gone and its field permission rows are deleted, both reversibly', function () {
    expect(Schema::hasColumn('employment_profiles', 'hourly_cost'))->toBeFalse();

    $drop = require database_path('migrations/2026_10_22_100000_drop_hourly_cost_from_employment_profiles_table.php');
    $drop->down();
    expect(Schema::hasColumn('employment_profiles', 'hourly_cost'))->toBeTrue();
    $drop->up();
    expect(Schema::hasColumn('employment_profiles', 'hourly_cost'))->toBeFalse();

    DB::table('role_field_permissions')->insert(['role_id' => DB::table('roles')->insertGetId(['name' => 'hr', 'guard_name' => 'web']), 'resource' => 'users', 'field' => 'employment.hourly_cost']);
    (require database_path('migrations/2026_10_22_100100_remove_hourly_cost_field_permission.php'))->up();

    expect(DB::table('role_field_permissions')->where('field', 'employment.hourly_cost')->exists())->toBeFalse();
});

it('AC-004: employment.hourly_cost is no longer written', function () {
    Permission::findOrCreate('users.update');
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs(User::factory()->create()->givePermissionTo('users.update'));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['hourly_cost' => 'abc']])->assertJsonMissingValidationErrors(['employment.hourly_cost']);
});

it('AC-005: the record shows the hourly cost in force today only with users.manageCompensation', function () {
    $target = User::factory()->withEmployment()->create();
    EmploymentContract::factory()->for($target->employment, 'profile')->withSalary('3460.00', SalaryPeriodEnum::Monthly)->create(['starts_on' => today()->subYear()->toDateString()]);
    EmploymentContract::factory()->for($target->employment, 'profile')->withSalary('99.00', SalaryPeriodEnum::Hourly)->create(['starts_on' => today()->addMonth()->toDateString()]);

    Sanctum::actingAs(salaryCostViewer(compensation: true));
    $this->getJson("/api/users/{$target->id}")->assertOk()->assertJsonPath('data.employment.hourly_cost', 20);

    Sanctum::actingAs(salaryCostViewer(compensation: false));
    expect($this->getJson("/api/users/{$target->id}")->assertOk()->json('data.employment'))->not->toHaveKey('hourly_cost');
});

it('AC-005: without a salary in force the hourly cost is null', function () {
    $target = User::factory()->withEmployment()->create();
    EmploymentContract::factory()->for($target->employment, 'profile')->create(['starts_on' => today()->subYear()->toDateString()]);
    Sanctum::actingAs(salaryCostViewer(compensation: true));

    $this->getJson("/api/users/{$target->id}")->assertOk()->assertJsonPath('data.employment.hourly_cost', null);
});
