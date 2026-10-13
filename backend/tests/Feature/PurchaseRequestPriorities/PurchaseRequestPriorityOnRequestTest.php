<?php

use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestPriority;
use App\Models\User;
use App\Notifications\PurchaseRequestSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function priorityByName(string $name): PurchaseRequestPriority
{
    return PurchaseRequestPriority::query()->where('name', $name)->firstOrFail();
}

function priorityGridRows(object $test, array $state = [], string $domain = 'purchase-requests'): array
{
    return $test->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50] + $state)->assertOk()->json('items');
}

// ---------------------------------------------------------------------------
// AC-003 - write and detail contract
// ---------------------------------------------------------------------------

it('AC-003: creates a request with a valid priority and the detail exposes {id,name,color,icon}', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));
    $urgent = priorityByName('Urgente');

    $this->postJson('/api/purchase-requests', purchaseRequestPayload(['purchase_request_priority_id' => $urgent->id]))
        ->assertCreated()
        ->assertJsonPath('data.priority', ['id' => $urgent->id, 'name' => 'Urgente', 'color' => 'orange', 'icon' => 'alarm-clock']);

    expect(PurchaseRequest::query()->first()->purchase_request_priority_id)->toBe($urgent->id);
});

it('AC-003: refuses an unknown, inactive or missing priority and the old string field', function (callable $payload) {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));

    $this->postJson('/api/purchase-requests', purchaseRequestPayload($payload()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('purchase_request_priority_id');
    expect(PurchaseRequest::query()->count())->toBe(0);
})->with([
    'unknown' => [fn () => ['purchase_request_priority_id' => 999999]],
    'inactive' => [fn () => ['purchase_request_priority_id' => PurchaseRequestPriority::factory()->create(['is_active' => false])->id]],
    'missing' => [fn () => ['purchase_request_priority_id' => null]],
    'legacy string' => [fn () => ['purchase_request_priority_id' => 'high']],
]);

it('AC-003: an update keeps a priority deactivated since, but refuses switching to an inactive one', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines();
    $assigned = PurchaseRequestPriority::query()->findOrFail($request->purchase_request_priority_id);
    $assigned->update(['is_default' => false, 'is_active' => false]);
    $other = PurchaseRequestPriority::factory()->create(['is_active' => false]);

    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request->load('lines')))
        ->assertOk()
        ->assertJsonPath('data.priority.id', $assigned->id);

    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request->load('lines'), ['purchase_request_priority_id' => $other->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('purchase_request_priority_id');
});

it('AC-003: the request factory falls back to the default priority', function () {
    expect(PurchaseRequest::factory()->create()->purchaseRequestPriority->name)->toBe('Media');
});

// ---------------------------------------------------------------------------
// AC-004 - grids
// ---------------------------------------------------------------------------

it('AC-004: the RDA grid shows the lookup, filters by priority name and sorts by sort_order', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $low = priorityByName('Bassa');
    $high = priorityByName('Alta');
    $critical = priorityByName('Critica');
    // Alphabetical order would be Alta < Bassa < Critica; sort_order is Bassa < Alta < Critica.
    purchaseRequestWithLines([], ['subject' => 'Critical one', 'purchase_request_priority_id' => $critical->id]);
    purchaseRequestWithLines([], ['subject' => 'High one', 'purchase_request_priority_id' => $high->id]);
    purchaseRequestWithLines([], ['subject' => 'Low one', 'purchase_request_priority_id' => $low->id]);

    $rows = priorityGridRows($this, ['sortModel' => [['colId' => 'priority', 'sort' => 'asc']]]);
    expect(collect($rows)->pluck('subject')->all())->toBe(['Low one', 'High one', 'Critical one'])
        ->and($rows[0]['priority'])->toBe(['id' => $low->id, 'name' => 'Bassa', 'color' => 'slate', 'icon' => 'flag']);

    $desc = priorityGridRows($this, ['sortModel' => [['colId' => 'priority', 'sort' => 'desc']]]);
    expect(collect($desc)->pluck('subject')->all())->toBe(['Critical one', 'High one', 'Low one']);

    $filtered = priorityGridRows($this, ['filterModel' => ['priority' => ['filterType' => 'set', 'values' => ['Alta', 'Critica']]]]);
    expect(collect($filtered)->pluck('subject')->sort()->values()->all())->toBe(['Critical one', 'High one']);

    $values = $this->postJson('/api/tables/purchase-requests/values', ['columnId' => 'priority'])->assertOk()->json();
    expect($values['data']['values'])->toBe(['Bassa', 'Media', 'Alta', 'Urgente', 'Critica']);
});

it('AC-004: the lines grid shows the parent priority, filters by name and sorts by sort_order', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $low = priorityByName('Bassa');
    $high = priorityByName('Alta');
    $critical = priorityByName('Critica');
    purchaseRequestWithLines([PurchaseRequestLineStatus::Approved], ['subject' => 'Critical one', 'purchase_request_priority_id' => $critical->id]);
    purchaseRequestWithLines([PurchaseRequestLineStatus::Approved], ['subject' => 'High one', 'purchase_request_priority_id' => $high->id]);
    purchaseRequestWithLines([PurchaseRequestLineStatus::Approved], ['subject' => 'Low one', 'purchase_request_priority_id' => $low->id]);

    $rows = priorityGridRows($this, ['sortModel' => [['colId' => 'priority', 'sort' => 'asc']]], 'purchase-request-lines');
    expect(collect($rows)->pluck('purchase_request_subject')->all())->toBe(['Low one', 'High one', 'Critical one'])
        ->and($rows[1]['priority'])->toBe(['id' => $high->id, 'name' => 'Alta', 'color' => 'amber', 'icon' => 'flame']);

    $filtered = priorityGridRows($this, ['filterModel' => ['priority' => ['filterType' => 'set', 'values' => ['Critica']]]], 'purchase-request-lines');
    expect(collect($filtered)->pluck('purchase_request_subject')->all())->toBe(['Critical one']);
});

it('AC-004: the RDA grid does not query the priority once per row', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    foreach (['Bassa', 'Alta', 'Urgente', 'Critica'] as $name) {
        purchaseRequestWithLines([], ['purchase_request_priority_id' => priorityByName($name)->id]);
    }

    $queries = collect();
    DB::listen(fn ($query) => $queries->push($query->sql));
    priorityGridRows($this, []);

    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "purchase_request_priorities"') || str_contains($sql, 'from `purchase_request_priorities`'))->count())->toBeLessThanOrEqual(1);
});

// ---------------------------------------------------------------------------
// AC-005 - notification
// ---------------------------------------------------------------------------

it('AC-005: the notification to the function manager carries the priority name', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $manager = User::factory()->create();
    $critical = priorityByName('Critica');

    $this->postJson('/api/purchase-requests', purchaseRequestPayload([
        'purchase_request_priority_id' => $critical->id,
        'function_manager_id' => $manager->id,
        'requester_id' => User::factory()->reportsTo($manager)->create()->id,
    ]))->assertCreated();

    Notification::assertSentTo($manager, PurchaseRequestSubmittedNotification::class, function ($notification) use ($manager): bool {
        return str_contains(collect($notification->toMail($manager)->introLines)->map(fn (mixed $line): string => (string) $line)->implode(' '), 'Critica');
    });
});
