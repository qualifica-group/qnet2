<?php

use App\Models\ExportRun;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `RegistryScopable` (spec 0199): scopes `opportunities`, `quotes`,
 * `work-orders` and `tasks` rows/values/columns/export to one client via
 * `registryId`/`registry_id`, plus the `quotes/for-select` `registry_id`
 * filter. AC-001, AC-002, AC-005.
 */
uses(RefreshDatabase::class);

/**
 * The rows payload of a domain; `tasks` defaults to the "mine" assignment
 * filter, so it needs the `visible` one to list every task the actor sees.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function registryScopeRows(string $domain, array $extra = []): array
{
    $base = ['startRow' => 0, 'endRow' => 25];

    if ($domain === 'tasks') {
        $base['advancedFilters'] = ['assignment' => ['visible']];
    }

    return [...$base, ...$extra];
}

function registryScopeActor(string $domain, array $abilities = ['viewAny']): User
{
    if ($domain === 'tasks') {
        return taskActorWith($abilities);
    }

    foreach (['viewAny', 'view', 'viewAll', 'export'] as $ability) {
        Permission::findOrCreate("{$domain}.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(array_map(static fn (string $a): string => "{$domain}.{$a}", [...$abilities, 'viewAll']));

    return $user;
}

/**
 * Two clients, each with one Opportunity -> Offerta -> Commessa and one Task.
 *
 * @return array{a: Registry, b: Registry, opportunityA: Opportunity, quoteA: Quote, workOrderA: WorkOrder, taskA: Task}
 */
function registryScopeFixture(): array
{
    $a = Registry::factory()->create();
    $b = Registry::factory()->create();
    $opportunityA = Opportunity::factory()->create(['registry_id' => $a->id]);
    $opportunityB = Opportunity::factory()->create(['registry_id' => $b->id]);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunityA->id]);
    $quoteB = Quote::factory()->create(['opportunity_id' => $opportunityB->id]);
    $workOrderA = WorkOrder::factory()->create(['quote_id' => $quoteA->id]);
    WorkOrder::factory()->create(['quote_id' => $quoteB->id]);
    $taskA = Task::factory()->create(['registry_id' => $a->id]);
    Task::factory()->create(['registry_id' => $b->id]);

    return compact('a', 'b', 'opportunityA', 'quoteA', 'workOrderA', 'taskA');
}

dataset('registry scoped domains', [
    'opportunities' => ['opportunities', 'opportunityA'],
    'quotes' => ['quotes', 'quoteA'],
    'work-orders' => ['work-orders', 'workOrderA'],
    'tasks' => ['tasks', 'taskA'],
]);

it('rows with registryId return only the client\'s records', function (string $domain, string $key) {
    $fixture = registryScopeFixture();
    Sanctum::actingAs(registryScopeActor($domain));

    $response = $this->postJson("/api/tables/{$domain}/rows", registryScopeRows($domain, ['registryId' => $fixture['a']->id]))
        ->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($fixture[$key]->id);
})->with('registry scoped domains');

it('rows without registryId return every record, unchanged', function (string $domain) {
    registryScopeFixture();
    Sanctum::actingAs(registryScopeActor($domain));

    $response = $this->postJson("/api/tables/{$domain}/rows", registryScopeRows($domain))->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
})->with('registry scoped domains');

it('a client with no records yields an empty page', function (string $domain) {
    registryScopeFixture();
    $empty = Registry::factory()->create();
    Sanctum::actingAs(registryScopeActor($domain));

    $response = $this->postJson("/api/tables/{$domain}/rows", registryScopeRows($domain, ['registryId' => $empty->id]))
        ->assertOk();

    expect($response->json('items'))->toBeEmpty()
        ->and($response->json('pagination.total'))->toBe(0);
})->with('registry scoped domains');

it('registryId composes in AND with the opportunityId scope on quotes', function () {
    $fixture = registryScopeFixture();
    $otherOpportunity = Opportunity::factory()->create(['registry_id' => $fixture['b']->id]);
    Sanctum::actingAs(registryScopeActor('quotes'));

    $response = $this->postJson('/api/tables/quotes/rows', [
        ...registryScopeRows('quotes'), 'registryId' => $fixture['a']->id, 'opportunityId' => $otherOpportunity->id,
    ])->assertOk();

    expect($response->json('items'))->toBeEmpty();
});

it('registryId composes in AND with the quoteId scope on work-orders', function () {
    $fixture = registryScopeFixture();
    $otherQuote = Quote::factory()->create();
    Sanctum::actingAs(registryScopeActor('work-orders'));

    $response = $this->postJson('/api/tables/work-orders/rows', [
        ...registryScopeRows('work-orders'), 'registryId' => $fixture['a']->id, 'quoteId' => $otherQuote->id,
    ])->assertOk();

    expect($response->json('items'))->toBeEmpty();
});

it('tasks: registryId composes with tree mode (roots of the client only)', function () {
    $fixture = registryScopeFixture();
    Task::factory()->create(['registry_id' => $fixture['a']->id, 'parent_task_id' => $fixture['taskA']->id]);
    Sanctum::actingAs(registryScopeActor('tasks'));

    $response = $this->postJson('/api/tables/tasks/rows', [
        ...registryScopeRows('tasks'), 'registryId' => $fixture['a']->id, 'tree' => true,
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($fixture['taskA']->id);
});

it('an unrelated domain ignores registryId', function () {
    $fixture = registryScopeFixture();
    Permission::findOrCreate('registries.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('registries.viewAny');
    Sanctum::actingAs($actor);

    $withScope = $this->postJson('/api/tables/registries/rows', registryScopeRows('registries', ['registryId' => $fixture['a']->id]))
        ->assertOk();
    $without = $this->postJson('/api/tables/registries/rows', registryScopeRows('registries'))
        ->assertOk();

    expect($withScope->json('pagination.total'))->toBe($without->json('pagination.total'));
});

it('registryId non-numeric or nonexistent -> 422; null is accepted as absent', function () {
    registryScopeFixture();
    Sanctum::actingAs(registryScopeActor('opportunities'));

    $this->postJson('/api/tables/opportunities/rows', registryScopeRows('opportunities', ['registryId' => 'x']))
        ->assertStatus(422)->assertJsonValidationErrors('registryId');
    $this->postJson('/api/tables/opportunities/rows', registryScopeRows('opportunities', ['registryId' => 999999]))
        ->assertStatus(422)->assertJsonValidationErrors('registryId');
    $this->postJson('/api/tables/opportunities/rows', registryScopeRows('opportunities', ['registryId' => null]))
        ->assertOk()->assertJsonPath('pagination.total', 2);
});

it('without the domain viewAny the endpoint stays 403 even with registryId', function (string $domain) {
    $fixture = registryScopeFixture();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/tables/{$domain}/rows", registryScopeRows($domain, ['registryId' => $fixture['a']->id]))
        ->assertForbidden();
})->with('registry scoped domains');

it('values are scoped to the client', function () {
    $fixture = registryScopeFixture();
    Sanctum::actingAs(registryScopeActor('opportunities'));

    $names = fn (array $extra) => $this->postJson('/api/tables/opportunities/values', ['columnId' => 'name', ...$extra])
        ->assertOk()->json('data.values') ?? [];

    $all = $names([]);
    $scoped = $names(['registryId' => $fixture['a']->id]);

    expect($all)->toHaveCount(2)
        ->and($scoped)->toBe([$fixture['opportunityA']->name]);
});

it('values are scoped to the client on work-orders (subquery through quotes)', function () {
    $fixture = registryScopeFixture();
    Sanctum::actingAs(registryScopeActor('work-orders'));

    $this->postJson('/api/tables/work-orders/values', ['columnId' => 'code', 'registryId' => $fixture['a']->id])
        ->assertOk()
        ->assertJsonPath('data.values', [$fixture['workOrderA']->code]);
});

it('columns accepts registry_id without changing the shape; invalid -> 422', function () {
    $fixture = registryScopeFixture();
    Sanctum::actingAs(registryScopeActor('tasks'));

    $plain = $this->getJson('/api/tables/tasks/columns')->assertOk()->json();
    $scoped = $this->getJson('/api/tables/tasks/columns?registry_id='.$fixture['a']->id)->assertOk()->json();

    expect($scoped)->toBe($plain);
    $this->getJson('/api/tables/tasks/columns?registry_id=999999')->assertStatus(422);
});

it('export is scoped to the client and freezes registryId in the run state', function (string $domain, string $key) {
    Storage::fake('local');
    $fixture = registryScopeFixture();
    Sanctum::actingAs(registryScopeActor($domain, ['viewAny', 'export']));

    $response = $this->postJson("/api/exports/{$domain}", [
        'format' => 'csv',
        'columns' => [['colId' => match ($domain) {
            'opportunities' => 'name',
            'tasks' => 'title',
            default => 'code',
        }, 'header' => 'Col']],
        'registryId' => $fixture['a']->id,
        ...($domain === 'tasks' ? ['advancedFilters' => ['assignment' => ['visible']]] : []),
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->state['registryId'])->toBe($fixture['a']->id)
        ->and($run->fresh()->row_count)->toBe(1);
})->with('registry scoped domains');

it('quotes for-select registry_id returns only the client\'s offers; hydration by ids bypasses it', function () {
    $fixture = registryScopeFixture();
    $other = Quote::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $scoped = $this->getJson('/api/quotes/for-select?registry_id='.$fixture['a']->id)->assertOk();
    expect(collect($scoped->json('data.items') ?? $scoped->json('items'))->pluck('id')->all())->toBe([$fixture['quoteA']->id]);

    $hydrated = $this->getJson('/api/quotes/for-select?registry_id='.$fixture['a']->id.'&ids[]='.$other->id)->assertOk();
    expect(collect($hydrated->json('data.items') ?? $hydrated->json('items'))->pluck('id')->all())
        ->toEqualCanonicalizing([$fixture['quoteA']->id, $other->id]);

    $this->getJson('/api/quotes/for-select?registry_id=999999')->assertStatus(422);
    expect($this->getJson('/api/quotes/for-select')->assertOk()->json('pagination.total') ?? null)->not->toBeNull();
});
