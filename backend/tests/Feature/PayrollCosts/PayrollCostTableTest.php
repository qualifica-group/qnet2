<?php

use App\Models\Company;
use App\Models\PayrollCost;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

// Spec 0430, AC-001 (grid part) and AC-010 — the read-only "Costi del personale" grid.
uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

const PAYROLL_ROWS = ['startRow' => 0, 'endRow' => 25];

function payrollViewer(): User
{
    return User::factory()->create()->givePermissionTo('payroll-costs.view');
}

/**
 * AC-007 data: one user with 1,600.00 on company X and 1,000.00 on company Y in 2026-10, 160 h in October
 * (100 on work order A, 40 on B, 20 without), plus an unrelated person without hours.
 *
 * @return array{x: PayrollCost, y: PayrollCost, idle: PayrollCost, user: User}
 */
function payrollGridData(): array
{
    $user = User::factory()->create(['name' => 'ignored']);
    PersonalData::factory()->individual()->for($user, 'personable')->create(['last_name' => 'Rossi', 'first_name' => 'Mario']);
    $companyX = Company::factory()->create(['denomination' => 'Alfa Srl']);
    $companyY = Company::factory()->create(['denomination' => 'Beta Srl']);

    $x = PayrollCost::factory()->create(['user_id' => $user->id, 'company_id' => $companyX->id, 'total_cost' => '1600.00']);
    $y = PayrollCost::factory()->create(['user_id' => $user->id, 'company_id' => $companyY->id, 'total_cost' => '1000.00']);
    mcTimeEntry(mcWorkOrder(), 6000, null, '2026-10-05', $user);
    mcTimeEntry(mcWorkOrder(), 2400, null, '2026-10-06', $user);
    mcTimeEntry(null, 1200, null, '2026-10-07', $user);
    mcTimeEntry(mcWorkOrder(), 600, null, '2026-09-05', $user);

    $idle = PayrollCost::factory()->create(['company_id' => $companyX->id, 'period' => '2026-09', 'total_cost' => '500.00']);

    return ['x' => $x, 'y' => $y, 'idle' => $idle, 'user' => $user];
}

it('AC-001: the grid endpoints answer 401 to anonymous users', function () {
    $this->getJson('/api/tables/payroll-costs/columns')->assertUnauthorized();
    $this->postJson('/api/tables/payroll-costs/rows', PAYROLL_ROWS)->assertUnauthorized();
});

it('AC-001: the grid endpoints answer 403 without payroll-costs.view and 200 with it', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/tables/payroll-costs/columns')->assertForbidden();
    $this->postJson('/api/tables/payroll-costs/rows', PAYROLL_ROWS)->assertForbidden();

    Sanctum::actingAs(payrollViewer());
    $this->getJson('/api/tables/payroll-costs/columns')->assertOk();
    $this->postJson('/api/tables/payroll-costs/rows', PAYROLL_ROWS)->assertOk();
});

it('AC-010: the computed columns are declared neither sortable nor filterable, the data columns are', function () {
    Sanctum::actingAs(payrollViewer());

    $columns = collect($this->getJson('/api/tables/payroll-costs/columns')->assertOk()->json('data.columns'))->keyBy('id');

    foreach (['timesheet_hours', 'work_order_hours', 'allocated_cost', 'unallocated_cost'] as $id) {
        expect($columns[$id]['sortable'])->toBeFalse()->and($columns[$id]['filterable'])->toBeFalse();
    }
    foreach (['period', 'company', 'user', 'tax_code', 'salary_cost', 'contribution_cost', 'insurance_cost', 'total_cost', 'ordinary_hours', 'hired_on', 'terminated_on'] as $id) {
        expect($columns[$id]['sortable'])->toBeTrue()->and($columns[$id]['filterable'])->toBeTrue();
    }
});

it('AC-010: the row of company X shows the hours and the shares of the person-month', function () {
    $data = payrollGridData();
    Sanctum::actingAs(payrollViewer());

    $items = collect($this->postJson('/api/tables/payroll-costs/rows', PAYROLL_ROWS)->assertOk()->json('items'))->keyBy('id');

    expect($items[$data['x']->id])->toMatchArray([
        'period' => '2026-10', 'company' => 'Alfa Srl', 'user' => 'Rossi Mario', 'total_cost' => '1600.00',
        'timesheet_hours' => '160.00', 'work_order_hours' => '140.00', 'allocated_cost' => '1400.00', 'unallocated_cost' => '200.00',
    ])->and($items[$data['y']->id])->toMatchArray(['allocated_cost' => '875.00', 'unallocated_cost' => '125.00'])
        ->and($items[$data['idle']->id])->toMatchArray([
            'timesheet_hours' => '0.00', 'work_order_hours' => '0.00', 'allocated_cost' => '0.00', 'unallocated_cost' => '500.00',
        ]);
});

it('AC-010: the minutes of a whole page come from one aggregate query on the timesheet', function () {
    payrollGridData();
    PayrollCost::factory()->count(3)->create();
    Sanctum::actingAs(payrollViewer());

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->postJson('/api/tables/payroll-costs/rows', PAYROLL_ROWS)->assertOk();

    $timesheetQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'time_entries'));
    expect($timesheetQueries)->toHaveCount(1);
});

it('AC-010: filters by month and by company', function () {
    $data = payrollGridData();
    Sanctum::actingAs(payrollViewer());

    $byPeriod = $this->postJson('/api/tables/payroll-costs/rows', [...PAYROLL_ROWS, 'filterModel' => [
        'period' => ['filterType' => 'set', 'values' => ['2026-09']],
    ]])->assertOk();
    $byCompany = $this->postJson('/api/tables/payroll-costs/rows', [...PAYROLL_ROWS, 'filterModel' => [
        'company' => ['filterType' => 'set', 'values' => ['Beta Srl']],
    ]])->assertOk();

    expect($byPeriod->json('items.*.id'))->toBe([$data['idle']->id])
        ->and($byCompany->json('items.*.id'))->toBe([$data['y']->id]);
});

it('AC-010: sorts by total cost and by default on the month descending', function () {
    $data = payrollGridData();
    Sanctum::actingAs(payrollViewer());

    $asc = $this->postJson('/api/tables/payroll-costs/rows', [...PAYROLL_ROWS, 'sortModel' => [['colId' => 'total_cost', 'sort' => 'asc']]])->assertOk();
    $default = $this->postJson('/api/tables/payroll-costs/rows', PAYROLL_ROWS)->assertOk();

    expect($asc->json('items.*.id'))->toBe([$data['idle']->id, $data['y']->id, $data['x']->id])
        ->and($default->json('items.*.period'))->toBe(['2026-10', '2026-10', '2026-09']);
});

it('AC-010: a computed column is rejected as a sort or filter column', function () {
    payrollGridData();
    Sanctum::actingAs(payrollViewer());

    $this->postJson('/api/tables/payroll-costs/rows', [...PAYROLL_ROWS, 'sortModel' => [['colId' => 'allocated_cost', 'sort' => 'desc']]])
        ->assertUnprocessable();
    $this->postJson('/api/tables/payroll-costs/rows', [...PAYROLL_ROWS, 'filterModel' => [
        'allocated_cost' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 0],
    ]])->assertUnprocessable();
});
