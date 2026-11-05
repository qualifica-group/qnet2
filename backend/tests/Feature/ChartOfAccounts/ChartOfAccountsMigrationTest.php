<?php

use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const SEED_MIGRATION = '2026_11_03_100100_seed_default_chart_of_accounts.php';
const SCHEMA_MIGRATION = '2026_11_03_100000_create_chart_of_accounts_tables.php';

function chartMigration(string $file): object
{
    return require database_path('migrations/'.$file);
}

it('AC-001: seeds 11 groups with kind only on groups, their accounts and the revenue sub-accounts', function () {
    $groups = DB::table('chart_accounts')->where('level', 'group')->orderBy('code')->get();

    expect($groups->pluck('code')->all())->toBe(['60', '61', '62', '63', '64', '65', '66', '67', '70', '71', '72'])
        ->and($groups->where('kind', 'cost')->count())->toBe(8)
        ->and($groups->where('kind', 'revenue')->count())->toBe(3)
        ->and($groups->whereNotNull('parent_id')->count())->toBe(0)
        ->and(DB::table('chart_accounts')->where('level', 'account')->count())->toBe(29)
        ->and(DB::table('chart_accounts')->where('level', 'account')->whereNotNull('kind')->count())->toBe(0);

    $account = ChartAccount::where('code', '61.03')->firstOrFail();
    expect($account->name)->toBe('Utenze')
        ->and($account->parent_id)->toBe(ChartAccount::where('code', '61')->value('id'));

    expect(RevenueType::orderBy('code')->pluck('name', 'code')->all())->toBe([
        '70.01.001' => 'Ricavi da consulenza',
        '70.02.001' => 'Ricavi da vendita prodotti',
        '70.03.001' => 'Provvigioni da enti',
    ]);
});

it('AC-001: places the four initial cost types under their account with the code', function () {
    $expected = [
        'Collaboratori' => ['61.01', '61.01.001'],
        'Utenze' => ['61.03', '61.03.001'],
        'Fitti' => ['62.01', '62.01.001'],
        'Altro' => ['65.02', '65.02.001'],
    ];

    foreach ($expected as $name => [$accountCode, $code]) {
        $costType = CostType::where('name', $name)->firstOrFail();

        expect($costType->code)->toBe($code)
            ->and($costType->chart_account_id)->toBe(ChartAccount::where('code', $accountCode)->value('id'));
    }
});

it('AC-001: re-running the data migration duplicates nothing and leaves other cost types unplaced', function () {
    $other = CostType::factory()->create(['name' => 'Licenze']);
    $counts = [ChartAccount::count(), RevenueType::count(), CostType::count()];

    chartMigration(SEED_MIGRATION)->up();

    expect([ChartAccount::count(), RevenueType::count(), CostType::count()])->toBe($counts)
        ->and($other->fresh()->chart_account_id)->toBeNull()
        ->and($other->fresh()->code)->toBeNull();
});

it('AC-001: matches the initial names trimmed and case-insensitive, only when the type has no account yet', function () {
    CostType::where('name', 'Fitti')->update(['name' => '  FITTI ', 'chart_account_id' => null, 'code' => null]);
    $placed = CostType::where('name', 'Utenze')->firstOrFail();
    $placed->update(['chart_account_id' => ChartAccount::where('code', '60.01')->value('id'), 'code' => 'CUSTOM']);

    chartMigration(SEED_MIGRATION)->up();

    expect(CostType::where('name', '  FITTI ')->firstOrFail()->code)->toBe('62.01.001')
        ->and($placed->fresh()->code)->toBe('CUSTOM');
});

it('AC-001: rollback removes the seeded data, then the tables and columns', function () {
    chartMigration(SEED_MIGRATION)->down();

    expect(ChartAccount::count())->toBe(0)
        ->and(RevenueType::count())->toBe(0)
        ->and(CostType::whereNotNull('code')->count())->toBe(0);

    chartMigration(SCHEMA_MIGRATION)->down();

    expect(Schema::hasTable('chart_accounts'))->toBeFalse()
        ->and(Schema::hasTable('revenue_types'))->toBeFalse()
        ->and(Schema::hasColumns('cost_types', ['chart_account_id']))->toBeFalse()
        ->and(Schema::hasColumns('cost_types', ['code']))->toBeFalse();
});
