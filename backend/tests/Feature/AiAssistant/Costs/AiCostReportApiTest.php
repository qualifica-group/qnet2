<?php

use App\Models\AiCostEntry;
use App\Models\AiMessage;
use App\Models\AiSpendCounter;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\AiAssistant\Costs\AiMoney;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-26 10:00:00');
});

/**
 * A ledger row with equal shares over the given business functions (none = one "no function" share).
 *
 * @param  list<int>  $functionIds
 */
function aicr_entry(User $user, string $date, string $cost, array $functionIds = [], string $model = 'acme/model', int $in = 100, int $out = 50): AiCostEntry
{
    $entry = AiCostEntry::query()->create([
        'user_id' => $user->id, 'model_id' => $model, 'input_tokens' => $in, 'output_tokens' => $out,
        'cached_tokens' => 10, 'reasoning_tokens' => 5, 'cost_usd' => $cost, 'reserved_usd' => $cost, 'incurred_on' => $date,
    ]);
    $ids = $functionIds === [] ? [null] : $functionIds;
    foreach (AiMoney::split($cost, count($ids)) as $i => $share) {
        $entry->shares()->create(['business_function_id' => $ids[$i], 'cost_usd' => $share]);
    }

    return $entry;
}

it('answers 401 anonymous and 403 to every other role, even with every permission', function () {
    $this->getJson('/api/ai-assistant/costs')->assertUnauthorized();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ai-assistant.use'));
    Sanctum::actingAs($user);

    $this->getJson('/api/ai-assistant/costs')->assertForbidden();
});

it('starts from the current month and leaves out other months', function () {
    Sanctum::actingAs(aiapi_super_admin());
    $ada = User::factory()->create(['name' => 'Ada']);
    aicr_entry($ada, '2026-10-31', '9.000000000000');
    aicr_entry($ada, '2026-11-01', '1.500000000000');
    aicr_entry($ada, '2026-11-26', '0.250000000000');

    $response = $this->getJson('/api/ai-assistant/costs')->assertOk();

    expect($response->json('data.period'))->toBe(['from' => '2026-11-01', 'to' => '2026-11-26'])
        ->and($response->json('data.totals'))->toBe([
            'cost_usd' => '1.750000000000', 'requests' => 2, 'input_tokens' => 200, 'output_tokens' => 100,
            'cache_read_tokens' => 20, 'reasoning_tokens' => 10,
        ])
        ->and(array_column($response->json('data.by_day'), 'date'))->toBe(['2026-11-01', '2026-11-26']);
});

it('honours an explicit period, inclusive at both ends', function () {
    Sanctum::actingAs(aiapi_super_admin());
    $ada = User::factory()->create();
    aicr_entry($ada, '2026-10-31', '9.000000000000');
    aicr_entry($ada, '2026-11-01', '1.000000000000');

    $response = $this->getJson('/api/ai-assistant/costs?from=2026-10-31&to=2026-11-01')->assertOk();

    expect($response->json('data.totals.cost_usd'))->toBe('10.000000000000')
        ->and($response->json('data.totals.requests'))->toBe(2);
});

it('splits a call equally over the business functions and keeps the no-function share, summing to the total', function () {
    Sanctum::actingAs(aiapi_super_admin());
    [$sales, $hr] = [BusinessFunction::factory()->create(['name' => 'Sales']), BusinessFunction::factory()->create(['name' => 'HR'])];
    $ada = User::factory()->create();
    aicr_entry($ada, '2026-11-02', '0.300000000000', [$sales->id, $hr->id]);
    aicr_entry($ada, '2026-11-03', '0.100000000000', [$sales->id]);
    aicr_entry($ada, '2026-11-03', '0.050000000000');

    $rows = collect($this->getJson('/api/ai-assistant/costs')->assertOk()->json('data'));
    $byFunction = collect($rows['by_function'])->keyBy(fn ($row) => $row['business_function_id'] ?? 'none');

    expect($byFunction['none'])->toBe(['business_function_id' => null, 'name' => null, 'cost_usd' => '0.050000000000', 'requests' => 1])
        ->and($byFunction[$sales->id]['cost_usd'])->toBe('0.250000000000')
        ->and($byFunction[$sales->id]['requests'])->toBe(2)
        ->and($byFunction[$sales->id]['name'])->toBe('Sales')
        ->and($byFunction[$hr->id]['cost_usd'])->toBe('0.150000000000')
        ->and(array_column($rows['by_function'], 'cost_usd'))->toBe(['0.250000000000', '0.150000000000', '0.050000000000'])
        ->and($rows['totals']['cost_usd'])->toBe('0.450000000000')
        ->and(array_reduce($rows['by_function'], fn (string $sum, array $row) => AiMoney::add($sum, $row['cost_usd']), '0'))
        ->toBe($rows['totals']['cost_usd']);
});

it('groups by person with requests and tokens, naming a deleted person null', function () {
    Sanctum::actingAs(aiapi_super_admin());
    $ada = User::factory()->create(['name' => 'Ada']);
    $gone = User::factory()->create(['name' => 'Gone']);
    aicr_entry($ada, '2026-11-02', '1.000000000000', in: 10, out: 1);
    aicr_entry($ada, '2026-11-03', '2.000000000000', in: 20, out: 2);
    aicr_entry($gone, '2026-11-03', '0.500000000000', in: 5, out: 5);
    $goneId = $gone->id;
    DB::table('users')->where('id', $goneId)->delete();

    $byUser = collect($this->getJson('/api/ai-assistant/costs')->assertOk()->json('data.by_user'))->keyBy('user_id');

    expect($byUser[$ada->id])->toBe(['user_id' => $ada->id, 'name' => 'Ada', 'cost_usd' => '3.000000000000', 'requests' => 2, 'input_tokens' => 30, 'output_tokens' => 3])
        ->and($byUser[$goneId]['name'])->toBeNull()
        ->and($byUser[$goneId]['cost_usd'])->toBe('0.500000000000');
});

it('groups by model, most expensive first', function () {
    Sanctum::actingAs(aiapi_super_admin());
    $ada = User::factory()->create();
    aicr_entry($ada, '2026-11-02', '0.100000000000', model: 'cheap/model');
    aicr_entry($ada, '2026-11-02', '2.000000000000', model: 'big/model');
    aicr_entry($ada, '2026-11-03', '1.000000000000', model: 'big/model');

    $byModel = $this->getJson('/api/ai-assistant/costs')->assertOk()->json('data.by_model');

    expect(array_column($byModel, 'model_id'))->toBe(['big/model', 'cheap/model'])
        ->and($byModel[0])->toBe(['model_id' => 'big/model', 'cost_usd' => '3.000000000000', 'requests' => 2, 'input_tokens' => 200, 'output_tokens' => 100]);
});

it('returns an empty report with zero totals when nothing was spent', function () {
    Sanctum::actingAs(aiapi_super_admin());

    $data = $this->getJson('/api/ai-assistant/costs')->assertOk()->json('data');

    expect($data['totals']['cost_usd'])->toBe('0.000000000000')
        ->and($data['by_day'])->toBe([])->and($data['by_function'])->toBe([])
        ->and($data['by_user'])->toBe([])->and($data['by_model'])->toBe([]);
});

it('reports the month spent and the caps in force', function () {
    Sanctum::actingAs(aiapi_super_admin());
    AiSpendCounter::query()->create(['period' => '2026-11', 'user_id' => AiSpendCounter::TOTAL_USER_ID, 'spent_usd' => '12.340000000000']);
    AiSpendCounter::query()->create(['period' => '2026-10', 'user_id' => AiSpendCounter::TOTAL_USER_ID, 'spent_usd' => '99']);
    app(AiAssistantSettingsService::class)->current()->update(['spend_cap_person_usd' => '5.00', 'spend_cap_total_usd' => null]);

    $caps = $this->getJson('/api/ai-assistant/costs')->assertOk()->json('data.caps');

    expect($caps)->toBe([
        'month' => '2026-11', 'spent_total_usd' => '12.340000000000',
        'spend_cap_total_usd' => null, 'spend_cap_person_usd' => '5.000000000000',
    ]);
});

it('rejects a malformed or inverted period or one beyond the configured maximum with 422', function (string $query) {
    Sanctum::actingAs(aiapi_super_admin());
    config(['ai-assistant.costs.report_max_days' => 31]);

    $this->getJson('/api/ai-assistant/costs?'.$query)->assertUnprocessable();
})->with([
    'bad date' => 'from=26/11/2026',
    'inverted' => 'from=2026-11-10&to=2026-11-09',
    'too long' => 'from=2026-09-01&to=2026-11-01',
    'from after today' => 'from=2026-12-01',
    'not a string' => 'from[]=2026-11-01',
]);

it('never reads message text and runs a fixed number of queries whatever the rows', function () {
    Sanctum::actingAs(aiapi_super_admin());
    $function = BusinessFunction::factory()->create();
    foreach (range(1, 12) as $i) {
        aicr_entry(User::factory()->create(), '2026-11-0'.(($i % 9) + 1), '0.100000000000', [$function->id], 'model/'.$i);
    }
    $message = AiMessage::factory()->create(['content' => 'TOP-SECRET-CONVERSATION']);

    DB::enableQueryLog();
    $response = $this->getJson('/api/ai-assistant/costs')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($response->getContent())->not->toContain('TOP-SECRET-CONVERSATION')
        ->and($queries->filter(fn (string $sql) => str_contains($sql, 'ai_messages') || str_contains($sql, 'ai_conversations')))->toBeEmpty()
        ->and($queries->filter(fn (string $sql) => str_contains($sql, 'ai_cost_entries')))->not->toBeEmpty()
        ->and(count($queries))->toBeLessThanOrEqual(12)
        ->and($message->exists)->toBeTrue();
});
