<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0272, AC-006 — the menu entry, access and validation of the agents endpoint.
uses(RefreshDatabase::class);

it('AC-006: the agents entry follows management-control.view and permissions:sync adds no permission', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'like', 'management-control.%')->pluck('name')->all())->toBe(['management-control.view']);

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('management-control-agents');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('management-control.view'));
    $section = collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'management-control-section');

    expect($section['children'][6])->toMatchArray([
        'key' => 'management-control-agents', 'label' => 'navigation.agents',
        'route' => '/management-control/agents', 'icon' => 'hand-coins',
    ]);
});

it('AC-006: 401 anonymous and 403 without the permission', function () {
    $this->getJson('/api/management-control/agents')->assertUnauthorized();

    Sanctum::actingAs(mcActor(false));
    mcGet('agents')->assertForbidden();
});

it('AC-006: invalid parameters answer 422', function (array $params) {
    Sanctum::actingAs(mcActor());

    mcGet('agents', $params)->assertUnprocessable();
})->with([
    'bad date' => [['date_from' => '2026-13-45']],
    'date_to before date_from' => [['date_from' => '2026-03-10', 'date_to' => '2026-03-09']],
    'more than 24 months' => [['date_from' => '2024-01-01', 'date_to' => '2026-01-31']],
    'unknown company' => [['company_id' => [999999]]],
    'top too low' => [['top' => 0]],
    'top too high' => [['top' => 51]],
]);

it('AC-006: the defaults and the normalised parameters are echoed in applied', function () {
    Sanctum::actingAs(mcActor());
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    $default = mcGet('agents')->assertOk()->json('data');
    expect($default['applied'])->toBe(['date_from' => null, 'date_to' => null, 'company_id' => [], 'top' => 20])
        ->and($default['today'])->toBe(mcDay(0));

    $applied = mcGet('agents', ['company_id' => [$second->id, $first->id, $second->id], 'top' => 10, 'date_from' => '2025-04-01', 'date_to' => '2026-03-31'])
        ->assertOk()->json('data.applied');

    expect($applied)->toBe(['date_from' => '2025-04-01', 'date_to' => '2026-03-31', 'company_id' => [$first->id, $second->id], 'top' => 10]);
});
