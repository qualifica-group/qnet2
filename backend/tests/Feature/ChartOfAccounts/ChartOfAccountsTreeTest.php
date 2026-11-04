<?php

use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-002: returns the contract shape, naturally sorted, with the unplaced cost types', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.view', 'cost-types.update', 'revenue-types.viewActivity']));

    $group = ChartAccount::factory()->create(['code' => 'X9']);
    $second = ChartAccount::factory()->account($group)->create(['code' => 'X9.10']);
    $first = ChartAccount::factory()->account($group)->create(['code' => 'X9.2', 'is_active' => false]);
    CostType::factory()->create(['chart_account_id' => $first->id, 'code' => 'X9.2.002', 'nature' => 'general', 'color' => 'teal']);
    CostType::factory()->create(['chart_account_id' => $first->id, 'code' => 'X9.2.001']);
    $revenueGroup = ChartAccount::factory()->revenue()->create(['code' => 'X8']);
    $revenueAccount = ChartAccount::factory()->account($revenueGroup)->create(['code' => 'X8.01']);
    RevenueType::factory()->create(['chart_account_id' => $revenueAccount->id, 'code' => 'X8.01.001']);
    CostType::factory()->create(['name' => 'Zeta unplaced']);
    CostType::factory()->create(['name' => 'Alfa unplaced']);

    $response = $this->getJson('/api/chart-of-accounts/tree')->assertOk()->assertJsonPath('success', true);
    $data = $response->json('data');

    $group60 = collect($data['groups'])->firstWhere('code', 'X9');
    expect(array_column($data['groups'], 'code'))->toContain('X8', 'X9')
        ->and(array_search('X8', array_column($data['groups'], 'code')))->toBeLessThan(array_search('X9', array_column($data['groups'], 'code')))
        ->and($group60)->toHaveKeys(['id', 'code', 'name', 'description', 'kind', 'is_active', 'accounts'])
        ->and($group60['kind'])->toBe('cost')
        ->and(array_column($group60['accounts'], 'code'))->toBe(['X9.2', 'X9.10'])
        ->and($group60['accounts'][0]['is_active'])->toBeFalse()
        ->and(array_column($group60['accounts'][0]['sub_accounts'], 'code'))->toBe(['X9.2.001', 'X9.2.002'])
        ->and($group60['accounts'][0]['sub_accounts'][1])->toMatchArray(['source' => 'cost_type', 'nature' => 'general', 'color' => 'teal'])
        ->and($second->id)->toBe($group60['accounts'][1]['id']);

    $revenue = collect($data['groups'])->firstWhere('code', 'X8');
    expect($revenue['kind'])->toBe('revenue')
        ->and($revenue['accounts'][0]['sub_accounts'][0])->toMatchArray(['source' => 'revenue_type', 'code' => 'X8.01.001', 'nature' => null, 'color' => null]);

    expect(array_column($data['unplaced_cost_types'], 'name'))->toBe(['Alfa unplaced', 'Zeta unplaced'])
        ->and($data['unplaced_cost_types'][0])->toMatchArray(['source' => 'cost_type', 'code' => null])
        ->and($data['abilities'])->toBe([
            'chart_accounts' => ['create' => false, 'update' => false, 'delete' => false, 'view_activity' => false, 'export' => false, 'import' => false],
            'revenue_types' => ['create' => false, 'update' => false, 'delete' => false, 'view_activity' => true],
            'cost_types' => ['create' => false, 'update' => true, 'delete' => false, 'view_activity' => false],
        ]);
});

it('AC-002: the number of queries does not grow with the number of entries', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.view']));

    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/chart-of-accounts/tree')->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn (array $q) => preg_match('/from "(chart_accounts|cost_types|revenue_types)"/', $q['query']))->count();
        DB::disableQueryLog();

        return $queries;
    };

    $before = $count();

    foreach (range(1, 5) as $i) {
        $group = ChartAccount::factory()->create();
        $account = ChartAccount::factory()->account($group)->create();
        CostType::factory()->create(['chart_account_id' => $account->id, 'code' => "Q{$i}.1"]);
        RevenueType::factory()->create();
    }

    expect($before)->toBe(3)->and($count())->toBe($before);
});

it('AC-003: the tree is forbidden without chart-accounts.view', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.view', 'chart-accounts.update']));

    $this->getJson('/api/chart-of-accounts/tree')->assertForbidden();
});

it('AC-003: the tree requires authentication', function () {
    $this->getJson('/api/chart-of-accounts/tree')->assertUnauthorized();
});
