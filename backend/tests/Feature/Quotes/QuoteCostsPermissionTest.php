<?php

use App\Enums\QuoteLineType;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Services\AiAssistant\Mentions\AiMentionResolver;
use App\Services\DocumentLayouts\Rendering\QuoteRenderSubject;
use Database\Seeders\DemoRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0412: `quotes.viewCosts` ("Vedere costi e margini") gates every place
 * the offer's costs and margins appear (D-2). AC-001..AC-006.
 */
uses(RefreshDatabase::class);

/**
 * A quote with one revenue line (1000) and one cost line (400), created over
 * HTTP by an actor holding the permission, so the aggregates are real.
 */
function costsPermissionQuote(): Quote
{
    Sanctum::actingAs(quoteTableUserWith(['create', 'view']));

    $id = test()->postJson('/api/quotes', [
        'title' => 'Offerta con costi',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => quoteHttpRevenueProduct()->id, 'quantity' => 1, 'unit_price' => 1000]],
        'cost_lines' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_price' => 400]],
    ])->assertCreated()->json('data.id');

    return Quote::findOrFail($id);
}

/**
 * Every quote ability in $abilities plus `viewAll`, never `viewCosts`.
 *
 * @param  array<int, string>  $abilities
 */
function costsPermissionBlindActor(array $abilities): User
{
    return quoteScopedUserWith([...$abilities, 'viewAll']);
}

it('AC-001: permissions:sync creates quotes.viewCosts and admin holds it', function () {
    Artisan::call('permissions:sync');
    $this->seed(DemoRolesSeeder::class);

    expect(Permission::where('name', 'quotes.viewCosts')->exists())->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('quotes.viewCosts'))->toBeTrue();
});

it('AC-001/D-4: the migration grants it to the roles that see the offers, not to the department and no-costs roles', function () {
    $view = Permission::findOrCreate('quotes.view');
    $seller = Role::create(['name' => 'consulente-test'])->givePermissionTo($view);
    $department = Role::create(['name' => 'reparto-test'])->givePermissionTo($view);
    $withheld = Role::create(['name' => 'tecnico-consulenza-senza-costi'])->givePermissionTo($view);
    $other = Role::create(['name' => 'senza-offerte']);
    Permission::where('name', 'quotes.viewCosts')->delete();

    (require database_path('migrations/2026_12_18_100000_add_quotes_view_costs_permission.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($seller->fresh()->hasPermissionTo('quotes.viewCosts'))->toBeTrue()
        ->and($department->fresh()->hasPermissionTo('quotes.viewCosts'))->toBeFalse()
        ->and($withheld->fresh()->hasPermissionTo('quotes.viewCosts'))->toBeFalse()
        ->and($other->fresh()->hasPermissionTo('quotes.viewCosts'))->toBeFalse();
});

it('AC-002: without viewCosts the detail has no cost lines, cost or margin, and view_costs is false', function () {
    $quote = costsPermissionQuote();
    Sanctum::actingAs(costsPermissionBlindActor(['view']));

    $data = $this->getJson("/api/quotes/{$quote->id}")->assertOk()
        ->assertJsonPath('permissions.actions.view_costs', false)
        ->assertJsonPath('permissions.fields.cost_lines.visible', false)
        ->json('data');

    expect($data)->not->toHaveKey('cost_lines')
        ->and($data['summary'])->not->toHaveKeys(['cost', 'margin'])
        ->and($data['summary']['revenue']['net'])->toBe('1000.00');
});

it('AC-003: without viewCosts cost_lines cannot be written, and an edit without them keeps the costs', function () {
    $quote = costsPermissionQuote();
    Sanctum::actingAs(costsPermissionBlindActor(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['cost_lines' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('cost_lines');

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Titolo nuovo'])->assertOk();

    expect(QuoteLine::where('quote_id', $quote->id)->where('line_type', QuoteLineType::Cost)->count())->toBe(1)
        ->and($quote->fresh()->cost_net)->toBe('400.00')
        ->and($quote->fresh()->margin_net)->toBe('600.00');
});

it('AC-004: without viewCosts the grid has no cost columns, values, filters, sort or export', function () {
    Storage::fake('local');
    costsPermissionQuote();
    Sanctum::actingAs(costsPermissionBlindActor(['viewAny', 'export']));

    $config = $this->getJson('/api/tables/quotes/columns')->assertOk()->json('data');
    expect(collect($config['columns'])->pluck('id'))->not->toContain('cost_net')->not->toContain('margin_net')
        ->and(collect($config['filters'])->pluck('columnId'))->not->toContain('cost_net')->not->toContain('margin_net');

    $row = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');
    expect($row)->not->toHaveKeys(['cost_net', 'margin_net'])->and($row)->toHaveKey('revenue_net');

    $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'margin_net', 'sort' => 'asc']],
    ])->assertUnprocessable();

    $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['cost_net' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 400]],
    ])->assertUnprocessable();

    $this->postJson('/api/exports/quotes', ['format' => 'csv', 'columns' => [['colId' => 'code', 'header' => 'Codice'], ['colId' => 'cost_net', 'header' => 'Costo']]])
        ->assertUnprocessable();
});

it('AC-004/D-2: without viewCosts the statistics leave out the margin widget', function () {
    costsPermissionQuote();
    Sanctum::actingAs(costsPermissionBlindActor(['viewAny']));

    $keys = collect($this->getJson('/api/stats/quotes')->assertOk()->json('data.widgets'))->pluck('key');

    expect($keys)->toContain('revenue_net')->not->toContain('margin_net');
});

it('AC-005: without viewCosts the contract detail has no offer cost or margin', function () {
    $contract = Contract::factory()->for(costsPermissionQuote())->create();
    foreach (['contracts.view', 'contracts.viewAll'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['contracts.view', 'contracts.viewAll']);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/contracts/{$contract->id}")->assertOk()->json('data');

    expect($data['quote'])->not->toHaveKeys(['cost_net', 'margin_net'])
        ->and($data['quote']['revenue_net'])->toBe('1000.00')
        ->and($data['summary'])->not->toHaveKeys(['cost', 'margin']);
});

it('D-2: without viewCosts the AI mention and the document layout leave the costs empty', function () {
    $quote = costsPermissionQuote();
    $actor = costsPermissionBlindActor(['view']);

    // REQUIREMENT CHANGED (spec 0433, ticket 07): the AI mention gives the model only type and id, never the record
    // content; the model reads the quote through the connector with the person's permissions, costs included.
    $mention = app(AiMentionResolver::class)->resolve($actor, 'quotes', $quote->id);
    expect(array_keys(get_object_vars($mention)))->toBe(['module', 'id', 'label']);

    $subject = QuoteRenderSubject::for($quote);
    $subject->prepare();
    expect($subject->productRows('cost_lines', $actor))->toBe([])
        ->and($subject->productRows('offer_lines', $actor))->toHaveCount(1)
        ->and($subject->resolveVariable('totals', 'margin_net', $actor))->toBe('')
        ->and($subject->resolveVariable('totals', 'cost_gross', $actor))->toBe('')
        ->and($subject->resolveVariable('totals', 'revenue_net', $actor))->not->toBe('');
});

it('AC-006: with viewCosts everything stays as today', function () {
    $quote = costsPermissionQuote();
    Contract::factory()->for($quote)->create();
    $actor = quoteTableUserWith(['view', 'viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/quotes/{$quote->id}")->assertOk()
        ->assertJsonPath('permissions.actions.view_costs', true)
        ->json('data');
    expect($data['cost_lines'])->toHaveCount(1)
        ->and($data['summary']['cost']['net'])->toBe('400.00')
        ->and($data['summary']['margin']['net'])->toBe('600.00');

    $row = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');
    expect($row['cost_net'])->toBe('400.00')->and($row['margin_net'])->toBe('600.00');

    // REQUIREMENT CHANGED (spec 0433, ticket 07): the mention carries no content, with or without viewCosts.
    $mention = app(AiMentionResolver::class)->resolve($actor, 'quotes', $quote->id);
    expect(array_keys(get_object_vars($mention)))->toBe(['module', 'id', 'label']);

    $subject = QuoteRenderSubject::for($quote);
    $subject->prepare();
    expect($subject->productRows('cost_lines', $actor))->toHaveCount(1)
        ->and($subject->resolveVariable('totals', 'margin_net', $actor))->not->toBe('');
});
