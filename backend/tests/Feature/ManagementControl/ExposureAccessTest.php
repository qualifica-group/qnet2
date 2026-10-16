<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0269, AC-001 / AC-002 — the menu entry and the access and validation rules.
uses(RefreshDatabase::class);

it('AC-001: the exposure entry follows management-control.view and permissions:sync adds no permission', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'like', 'management-control.%')->pluck('name')->all())->toBe(['management-control.view']);

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('management-control-exposure');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('management-control.view'));
    $section = collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'management-control-section');

    expect(array_column($section['children'], 'key'))->toBe(['management-control', 'management-control-exposure', 'management-control-cash-flow', 'management-control-trend'])
        ->and($section['children'][1])->toMatchArray([
            'key' => 'management-control-exposure', 'label' => 'navigation.exposure',
            'route' => '/management-control/exposure', 'icon' => 'calendar-clock',
        ]);
});

it('AC-002: 401 anonymous and 403 without the permission', function () {
    $this->getJson('/api/management-control/exposure')->assertUnauthorized();

    Sanctum::actingAs(mcActor(false));
    mcGet('exposure')->assertForbidden();
});

it('AC-002: invalid parameters answer 422, valid ones echo in applied', function (array $params) {
    Sanctum::actingAs(mcActor());

    mcGet('exposure', $params)->assertUnprocessable();
})->with([
    'bad date' => [['date_from' => '2026-13-45']],
    'date_to before date_from' => [['date_from' => '2026-03-10', 'date_to' => '2026-03-09']],
    'unknown company' => [['company_id' => [999999]]],
    'unknown company site' => [['company_site_id' => [999999]]],
    'unknown operational site' => [['operational_site_id' => [999999]]],
    'document_type out of list' => [['document_type' => 'credit_note']],
    'group_by out of list' => [['group_by' => 'customer']],
    'top zero' => [['top' => 0]],
    'top too high' => [['top' => 26]],
]);

it('AC-002: the defaults and the normalised parameters are echoed in applied', function () {
    Sanctum::actingAs(mcActor());
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    $default = mcGet('exposure')->assertOk()->json('data');
    expect($default['applied'])->toBe([
        'date_from' => null, 'date_to' => null, 'company_id' => [], 'company_site_id' => [], 'operational_site_id' => [],
        'document_type' => 'invoice', 'group_by' => 'company', 'top' => 10,
    ])->and($default['today'])->toBe(mcDay(0));

    $applied = mcGet('exposure', [
        'company_id' => [$second->id, $first->id, $second->id], 'document_type' => 'all', 'group_by' => 'operational_site', 'top' => 25,
        'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
    ])->assertOk()->json('data.applied');

    expect($applied)->toMatchArray([
        'company_id' => [$first->id, $second->id], 'document_type' => 'all', 'group_by' => 'operational_site', 'top' => 25,
        'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
    ]);
});
