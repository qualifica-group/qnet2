<?php

use App\Models\PayrollCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

// Spec 0430, AC-001 (endpoint), AC-002, AC-003, AC-006.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    $this->fixture = payrollSetUp();
});

/**
 * @return list<array<string, mixed>> the stored rows without ids and timestamps
 */
function payrollStored(): array
{
    return PayrollCost::query()->orderBy('company_id')->orderBy('user_id')->get()
        ->map(fn (PayrollCost $cost): array => collect($cost->toArray())->except(['id', 'created_at', 'updated_at'])->all())
        ->all();
}

it('AC-001: the import answers 401 anonymously, 403 without view and 403 with view but without import', function () {
    $file = payrollFile(payrollReferenceRows());

    $this->post('/api/payroll-costs/import', ['file' => $file, 'dry_run' => '1'], ['Accept' => 'application/json'])->assertUnauthorized();

    payrollImport(User::factory()->create()->givePermissionTo('payroll-costs.import'), $file, true)->assertForbidden();
    payrollImport(User::factory()->create()->givePermissionTo('payroll-costs.view'), $file, true)->assertForbidden();
    expect(PayrollCost::query()->count())->toBe(0);
});

it('AC-002: a Windows-1252 tab file previews exactly and writes nothing, then applies on dry_run=false', function () {
    $actor = payrollImporterUser();
    $file = fn () => payrollFile(payrollReferenceRows(), "\t", 'costs.txt', windows1252: true);

    $preview = payrollImport($actor, $file(), true)->assertOk();

    expect($preview->json('data'))->toBe([
        'applied' => false,
        'period' => '2026-10',
        'summary' => ['rows' => 3, 'people' => 2, 'companies' => 2, 'total_cost' => 4622.06, 'replaced_rows' => 0],
        'errors' => [],
    ])->and(PayrollCost::query()->count())->toBe(0);

    $applied = payrollImport($actor, $file(), false)->assertOk();

    expect($applied->json('data.applied'))->toBeTrue()
        ->and(PayrollCost::query()->count())->toBe(3);

    $first = PayrollCost::query()->where('company_id', $this->fixture['companies']['1']->id)->where('user_id', $this->fixture['users']['a']->id)->sole();
    expect($first->period)->toBe('2026-10')
        ->and($first->tax_code)->toBe(PAYROLL_TAX_A)
        ->and($first->salary_cost)->toBe('1234.56')
        ->and($first->total_cost)->toBe('1547.06')
        ->and($first->ordinary_hours)->toBe('160.00')
        ->and($first->hired_on->toDateString())->toBe('2020-03-01')
        ->and($first->imported_by)->toBe($actor->id);

    $second = PayrollCost::query()->where('company_id', $this->fixture['companies']['3']->id)->where('user_id', $this->fixture['users']['a']->id)->sole();
    expect($second->contribution_cost)->toBe('-50.00');

    $terminated = PayrollCost::query()->where('user_id', $this->fixture['users']['b']->id)->sole();
    expect($terminated->terminated_on->toDateString())->toBe('2026-10-15');
});

it('AC-003: the same content as tab, semicolon, comma csv and xlsx produces the same rows', function () {
    $actor = payrollImporterUser();
    $rows = payrollReferenceRows();
    $files = [
        'tab' => fn () => payrollFile($rows, "\t", 'costs.csv', windows1252: true),
        'semicolon' => fn () => payrollFile($rows, ';'),
        'comma' => fn () => payrollFile($rows, ','),
        'xlsx' => fn () => payrollXlsx($rows),
    ];

    $stored = [];
    foreach ($files as $kind => $makeFile) {
        payrollImport($actor, $makeFile(), false)->assertOk()->assertJsonPath('data.applied', true);
        $stored[$kind] = payrollStored();
        PayrollCost::query()->delete();
    }

    expect($stored['tab'])->toHaveCount(3)
        ->and($stored['semicolon'])->toBe($stored['tab'])
        ->and($stored['comma'])->toBe($stored['tab'])
        ->and($stored['xlsx'])->toBe($stored['tab']);
});

it('AC-006: a loaded month is replaced as a whole, other months are untouched and deletions are logged', function () {
    $actor = payrollImporterUser();
    PayrollCost::factory()->count(3)->create(['period' => '2026-10']);
    $september = PayrollCost::factory()->create(['period' => '2026-09']);
    $file = fn () => payrollFile([payrollRow(), payrollRow(['Codice fiscale dipendente' => PAYROLL_TAX_B])]);

    payrollImport($actor, $file(), true)->assertOk()->assertJsonPath('data.summary.replaced_rows', 3);
    expect(PayrollCost::query()->where('period', '2026-10')->count())->toBe(3);

    payrollImport($actor, $file(), false)->assertOk()->assertJsonPath('data.applied', true);

    $october = PayrollCost::query()->where('period', '2026-10')->pluck('user_id')->sort()->values()->all();
    expect($october)->toBe(collect([$this->fixture['users']['a']->id, $this->fixture['users']['b']->id])->sort()->values()->all())
        ->and(PayrollCost::query()->whereKey($september->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('subject_type', 'payroll_cost')->where('event', 'deleted')->count())->toBe(3);
});
