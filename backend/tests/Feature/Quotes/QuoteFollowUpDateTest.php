<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0349: "Data ricontatto" (`quotes.follow_up_date`), an optional date of
 * the Offerta edited from the record (AC-001..004) and the grid (AC-005/006).
 */
uses(RefreshDatabase::class);

function quoteFollowUpUser(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo($abilities);
    $user->givePermissionTo(Permission::findOrCreate('quotes.viewAll', 'web'));

    return $user;
}

function quoteFollowUpOfferLines(): array
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $product = Product::factory()->create(['category_id' => $category->id]);

    return [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]];
}

/** @return array<int, int> */
function quoteFollowUpGridIds(array $request): array
{
    return collect(test()->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 50, ...$request])
        ->assertOk()->json('items'))->pluck('id')->all();
}

it('adds an indexed, nullable follow_up_date column to quotes', function () {
    expect(Schema::hasColumn('quotes', 'follow_up_date'))->toBeTrue()
        ->and(Schema::hasIndex('quotes', ['follow_up_date']))->toBeTrue();
});

it('AC-001: create stores the submitted date and returns it as Y-m-d; omitted it stays null', function () {
    Sanctum::actingAs(quoteFollowUpUser(['quotes.create']));

    $with = $this->postJson('/api/quotes', [
        'opportunity_id' => Opportunity::factory()->create()->id,
        'follow_up_date' => '2026-12-01',
        'offer_lines' => quoteFollowUpOfferLines(),
    ])->assertCreated()->assertJsonPath('data.follow_up_date', '2026-12-01');

    $without = $this->postJson('/api/quotes', [
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => quoteFollowUpOfferLines(),
    ])->assertCreated()->assertJsonPath('data.follow_up_date', null);

    expect(Quote::findOrFail($with->json('data.id'))->follow_up_date->toDateString())->toBe('2026-12-01')
        ->and(Quote::findOrFail($without->json('data.id'))->follow_up_date)->toBeNull();
});

it('AC-002: PATCH sets the date, null clears it, an omitted key leaves it untouched', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteFollowUpUser(['quotes.view', 'quotes.update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['follow_up_date' => '2026-12-15'])
        ->assertOk()
        ->assertJsonPath('data.follow_up_date', '2026-12-15');

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Altro titolo'])
        ->assertOk()
        ->assertJsonPath('data.follow_up_date', '2026-12-15');

    $this->patchJson("/api/quotes/{$quote->id}", ['follow_up_date' => null])
        ->assertOk()
        ->assertJsonPath('data.follow_up_date', null);

    expect($quote->fresh()->follow_up_date)->toBeNull();
});

it('AC-003: a date not in Y-m-d is a 422 on create and update and writes nothing', function (string $value) {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteFollowUpUser(['quotes.view', 'quotes.update', 'quotes.create']));

    $this->patchJson("/api/quotes/{$quote->id}", ['follow_up_date' => $value])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['follow_up_date']);

    $this->postJson('/api/quotes', ['opportunity_id' => Opportunity::factory()->create()->id, 'follow_up_date' => $value])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['follow_up_date']);

    expect($quote->fresh()->follow_up_date)->toBeNull()
        ->and(Quote::query()->count())->toBe(1);
})->with(['01/12/2026', '2026-12-01 10:00', 'domani']);

it('AC-004: without quotes.update the PATCH is 403 and the field reads as readonly', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteFollowUpUser(['quotes.view']));

    $this->patchJson("/api/quotes/{$quote->id}", ['follow_up_date' => '2026-12-01'])->assertForbidden();

    $field = $this->getJson("/api/quotes/{$quote->id}")->assertOk()->json('permissions.fields.follow_up_date');

    expect($quote->fresh()->follow_up_date)->toBeNull()
        ->and($field['editable'])->toBeFalse()
        ->and($field['readonly'])->toBeTrue();
});

it('AC-004: a writer sees the field editable and not required', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteFollowUpUser(['quotes.view', 'quotes.update']));

    $field = $this->getJson("/api/quotes/{$quote->id}")->assertOk()->json('permissions.fields.follow_up_date');

    expect($field['editable'])->toBeTrue()
        ->and($field['required'])->toBeFalse();
});

it('AC-005: the grid declares an editable, nullable date column', function () {
    Sanctum::actingAs(quoteFollowUpUser(['quotes.viewAny', 'quotes.view', 'quotes.update']));

    $column = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'follow_up_date');

    expect($column)->not->toBeNull()
        ->and($column['label'])->toBe('quotes.columns.followUpDate')
        ->and($column['type'])->toBe('date')
        ->and($column['filterType'])->toBe('date')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['editable'])->toBeTrue();
});

it('AC-005: rows carry the date and the cell edit writes it, clears it and rejects a bad format', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteFollowUpUser(['quotes.viewAny', 'quotes.view', 'quotes.update']));

    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'follow_up_date', 'value' => '2026-12-20'])->assertOk();
    expect($quote->fresh()->follow_up_date->toDateString())->toBe('2026-12-20');

    $row = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))
        ->firstWhere('id', $quote->id);
    expect(substr((string) $row['follow_up_date'], 0, 10))->toBe('2026-12-20');

    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'follow_up_date', 'value' => '20/12/2026'])->assertStatus(422);
    expect($quote->fresh()->follow_up_date->toDateString())->toBe('2026-12-20');

    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'follow_up_date', 'value' => null])->assertOk();
    expect($quote->fresh()->follow_up_date)->toBeNull();
});

it('AC-006: the grid sorts and date-filters on follow_up_date', function () {
    $late = Quote::factory()->create(['follow_up_date' => '2026-12-20']);
    $early = Quote::factory()->create(['follow_up_date' => '2026-12-05']);
    Quote::factory()->create(['follow_up_date' => null]);
    Sanctum::actingAs(quoteFollowUpUser(['quotes.viewAny', 'quotes.view']));

    $sorted = quoteFollowUpGridIds([
        'sortModel' => [['colId' => 'follow_up_date', 'sort' => 'desc']],
        'filterModel' => ['follow_up_date' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-12-01']],
    ]);
    $ranged = quoteFollowUpGridIds([
        'filterModel' => ['follow_up_date' => ['filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-12-01', 'dateTo' => '2026-12-10']],
    ]);

    expect($sorted)->toBe([$late->id, $early->id])
        ->and($ranged)->toBe([$early->id]);
});
