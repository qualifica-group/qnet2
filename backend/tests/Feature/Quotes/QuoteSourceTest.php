<?php

use App\Models\BusinessFunction;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0332: the Fonte is a column of the Offerta (D-1): CRUD (AC-002), grid
 * column + inline edit (AC-003), the Opportunity no longer carries it
 * (AC-004), the for-select `meta.lead_source` (AC-005).
 */
uses(RefreshDatabase::class);

function quoteSourceUser(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo($abilities);
    $user->givePermissionTo(Permission::findOrCreate('quotes.viewAll', 'web'));

    return $user;
}

function quoteSourceOfferLines(): array
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $product = Product::factory()->create(['category_id' => $category->id]);

    return [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]];
}

it('AC-001: the schema carries the Fonte on quotes and no longer on opportunities', function () {
    expect(Schema::hasColumn('quotes', 'source_id'))->toBeTrue()
        ->and(Schema::hasColumn('opportunities', 'source_id'))->toBeFalse();
});

it('AC-001: deleting a Fonte at the DB level nulls the offer source and the offer survives', function () {
    $source = Source::factory()->create();
    $quote = Quote::factory()->create(['source_id' => $source->id]);

    DB::table('sources')->where('id', $source->id)->delete();

    expect($quote->fresh())->not->toBeNull()
        ->source_id->toBeNull();
});

it('AC-002: PATCH sets the Fonte and the response carries source_id and source', function () {
    $source = Source::factory()->create(['name' => 'Passaparola']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteSourceUser(['quotes.view', 'quotes.update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['source_id' => $source->id])
        ->assertOk()
        ->assertJsonPath('data.source_id', $source->id)
        ->assertJsonPath('data.source', ['id' => $source->id, 'name' => 'Passaparola']);

    expect($quote->fresh()->source_id)->toBe($source->id);
});

it('AC-002: PATCH {source_id: null} clears it and an omitted key leaves it untouched', function () {
    $source = Source::factory()->create();
    $quote = Quote::factory()->create(['source_id' => $source->id]);
    Sanctum::actingAs(quoteSourceUser(['quotes.view', 'quotes.update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Nuovo titolo'])
        ->assertOk()
        ->assertJsonPath('data.source_id', $source->id);

    $this->patchJson("/api/quotes/{$quote->id}", ['source_id' => null])
        ->assertOk()
        ->assertJsonPath('data.source_id', null)
        ->assertJsonPath('data.source', null);
});

it('AC-002: a nonexistent source_id is a 422 on update and on create', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteSourceUser(['quotes.view', 'quotes.update', 'quotes.create']));

    $this->patchJson("/api/quotes/{$quote->id}", ['source_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source_id']);

    $this->postJson('/api/quotes', ['opportunity_id' => Opportunity::factory()->create()->id, 'source_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source_id']);
});

it('AC-002: create persists the submitted Fonte on the offer', function () {
    $source = Source::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteSourceUser(['quotes.create']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Con fonte',
        'opportunity_id' => $opportunity->id,
        'source_id' => $source->id,
        'offer_lines' => quoteSourceOfferLines(),
    ])->assertCreated()->assertJsonPath('data.source_id', $source->id);

    expect(Quote::findOrFail($response->json('data.id'))->source_id)->toBe($source->id);
});

it('AC-002: an actor without update rights cannot change the Fonte (403)', function () {
    $source = Source::factory()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteSourceUser(['quotes.view']));

    $this->patchJson("/api/quotes/{$quote->id}", ['source_id' => $source->id])->assertForbidden();

    expect($quote->fresh()->source_id)->toBeNull();
});

it('AC-003: the Offerte grid declares a filterable, sortable, editable source column', function () {
    Sanctum::actingAs(quoteSourceUser(['quotes.viewAny', 'quotes.view', 'quotes.update']));

    $column = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'source');

    expect($column)->not->toBeNull()
        ->and($column['label'])->toBe('quotes.columns.source')
        ->and($column['filterType'])->toBe('set')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['editable'])->toBeTrue()
        ->and($column['relation']['resource'])->toBe('sources');
});

it('AC-003: rows project the source, the set filter narrows on it and the cell edit writes quotes.source_id', function () {
    $source = Source::factory()->create(['name' => 'Fiera']);
    $withSource = Quote::factory()->create(['source_id' => $source->id]);
    $without = Quote::factory()->create();
    Sanctum::actingAs(quoteSourceUser(['quotes.viewAny', 'quotes.view', 'quotes.update']));

    $rows = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'));

    expect($rows->firstWhere('id', $withSource->id)['source'])->toBe(['id' => $source->id, 'name' => 'Fiera'])
        ->and($rows->firstWhere('id', $without->id)['source'])->toBeNull();

    $filtered = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0,
        'endRow' => 10,
        'filterModel' => ['source' => ['filterType' => 'set', 'values' => ['Fiera']]],
    ])->assertOk()->json('items');

    expect(collect($filtered)->pluck('id')->all())->toBe([$withSource->id]);

    $this->patchJson("/api/tables/quotes/rows/{$without->id}", ['column' => 'source', 'value' => $source->id])
        ->assertOk()
        ->assertJsonPath('data.source.id', $source->id);

    expect($without->fresh()->source_id)->toBe($source->id);
});

it('AC-004: the Opportunity resource and grid no longer carry the Fonte, and a submitted source_id is not written', function () {
    $source = Source::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(opportunityFromLeadActor(['viewAny', 'view', 'update'], []));

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.source_id')
        ->assertJsonMissingPath('data.source');

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['name' => 'Rinominata', 'source_id' => $source->id])->assertOk();

    expect($opportunity->fresh()->getAttributes())->not->toHaveKey('source_id');

    $columns = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.columns'))->pluck('id');
    expect($columns)->not->toContain('source');
});

it('AC-005: for-select exposes the lead Fonte as meta.lead_source, null without a lead', function () {
    $source = Source::factory()->create(['name' => 'Campagna web']);
    $lead = Lead::factory()->create(['source_id' => $source->id]);
    $fromLead = Opportunity::factory()->create(['lead_id' => $lead->id]);
    $standalone = Opportunity::factory()->create();
    Sanctum::actingAs(opportunityFromLeadActor(['viewAny'], []));

    $items = collect($this->getJson('/api/opportunities/for-select?per_page=50')->assertOk()->json('items'));

    expect($items->firstWhere('id', $fromLead->id)['meta']['lead_source'])->toBe(['id' => $source->id, 'name' => 'Campagna web'])
        ->and($items->firstWhere('id', $standalone->id)['meta']['lead_source'])->toBeNull();
});
