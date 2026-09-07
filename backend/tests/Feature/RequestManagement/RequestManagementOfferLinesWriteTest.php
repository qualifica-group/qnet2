<?php

declare(strict_types=1);

use App\Enums\CategoryManagementMode;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\QuoteLineType;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// "Linee dell'offerta" in Gestione Richieste (user directive 2026-08-07: "un
// componente dove si inseriscono le linee dell'offerta"). REQUIREMENT CHANGE
// vs spec 0086 AC-022, which made `offer_lines` read-only on this module: the
// rows are now writable from both the work panel (PATCH) and the create form
// (POST), through the SAME ValidatesQuoteLines rules and the SAME QuoteService
// write path the Offerte module uses — commissions excluded, they stay the
// Offerte form's own block. The grid column remains read-only (its own test,
// RequestManagementOfferLinesTest).

uses(RefreshDatabase::class);

if (! function_exists('offerLineWriteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function offerLineWriteActor(array $abilities = ['view', 'update', 'create', 'viewAll']): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('offerLineCategory')) {
    /** A product category with its own business function, plus a product filed on it. */
    function offerLineCategory(CategoryManagementMode $mode = CategoryManagementMode::Multiple): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'management_mode' => $mode,
        ]);
    }
}

if (! function_exists('offerLineMatrixActor')) {
    /**
     * A role-bearing actor carrying one `role_field_permissions` row: the DB
     * matrix only ever restricts actors reached through a role, so a
     * direct-permission actor cannot exercise this gate.
     *
     * @param  array<string, mixed>  $matrixRow
     */
    function offerLineMatrixActor(array $matrixRow): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'offer-lines-role-'.uniqid()]);
        $role->givePermissionTo(['request-management.viewAny', 'request-management.update']);
        $role->fieldPermissions()->create($matrixRow);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('offerLineRequest')) {
    /** A request (Offerta) whose Opportunity carries one product line on $category. */
    function offerLineRequest(User $operator, ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
        OpportunityProductLine::factory()->for($opportunity)->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

// ---------------------------------------------------------------------------
// GET — the panel now carries the full editable row, not a name-only summary
// ---------------------------------------------------------------------------

it('GET projects each offer line with the fields the row editor needs', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fibra 1000']);
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'line_type' => QuoteLineType::Revenue,
        'quantity' => 2,
        'unit_price' => 100,
        'vat_rate_id' => $vatRate->id,
    ]);
    Sanctum::actingAs($actor);

    $line = $this->getJson("/api/request-management/{$quote->id}")->assertOk()->json('data.offer_lines.0');

    expect($line['product_id'])->toBe($product->id)
        ->and($line['product']['name'])->toBe('Fibra 1000')
        ->and((float) $line['quantity'])->toBe(2.0)
        ->and((float) $line['unit_price'])->toBe(100.0)
        ->and($line['vat_rate_id'])->toBe($vatRate->id)
        ->and($line['vat_rate']['rate'])->not->toBeNull()
        ->and($line)->toHaveKeys(['net_amount', 'vat_amount', 'total_amount', 'sort_order']);
});

// ---------------------------------------------------------------------------
// PATCH — full replace through QuoteService, with every derived value refreshed
// ---------------------------------------------------------------------------

it('PATCH writes the offer lines and freezes the server-computed amounts', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'vat_rate_id' => $vatRate->id,
        ]],
    ])->assertOk();

    expect($response->json('data.offer_lines'))->toHaveCount(1);

    $line = $quote->offerLines()->sole();
    expect($line->product_id)->toBe($product->id)
        ->and((float) $line->net_amount)->toBe(200.0)
        ->and((float) $line->vat_amount)->toBe(44.0)
        ->and((float) $line->total_amount)->toBe(244.0)
        // D-9: the header aggregates are recalculated by the same write path
        // the Offerte module uses — never left stale by this channel.
        ->and((float) $quote->fresh()->revenue_net)->toBe(200.0);
});

it('PATCH full-replaces the set: an omitted persisted row is deleted', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $kept = Product::factory()->create(['category_id' => $category->id]);
    $dropped = Product::factory()->create(['category_id' => $category->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $dropped->id, 'line_type' => QuoteLineType::Revenue]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $kept->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertOk();

    expect($quote->offerLines()->pluck('product_id')->all())->toBe([$kept->id]);
});

it('PATCH without the key leaves the persisted rows untouched', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id, 'line_type' => QuoteLineType::Revenue]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'next_callback_at' => '2026-09-01T10:30',
    ])->assertOk();

    expect($quote->offerLines()->count())->toBe(1);
});

it('PATCH never touches the COST lines', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $costLine = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'line_type' => QuoteLineType::Cost,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertOk();

    expect(QuoteLine::query()->whereKey($costLine->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Commissions stay the Offerte form's block
// ---------------------------------------------------------------------------

it('PATCH refuses a submitted commissions block (prohibited on this channel)', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
            'commissions' => [[
                'recipient_role' => CommissionRecipientRole::Commercial->value,
                'recipient_type' => 'referent',
                'recipient_id' => Referent::factory()->create()->id,
                'commission_type' => CommissionType::Percentage->value,
                'value' => 5,
                'origin' => CommissionOrigin::ManualOverride->value,
            ]],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.commissions');
});

it('PATCH keeps a manual commission the Offerte form set up, recalculated on the new amount', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'line_type' => QuoteLineType::Revenue,
        'quantity' => 1,
        'unit_price' => 100,
        'net_amount' => 100,
    ]);
    $commission = QuoteLineCommission::factory()->create([
        'quote_line_id' => $line->id,
        'recipient_role' => CommissionRecipientRole::Commercial,
        'recipient_type' => (new Referent)->getMorphClass(),
        'recipient_id' => Referent::factory()->create()->id,
        'commission_type' => CommissionType::Percentage,
        'value' => 10,
        'calculated_amount' => 10,
        'origin' => CommissionOrigin::ManualOverride,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'id' => $line->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]],
    ])->assertOk();

    $commission->refresh();
    expect($commission->origin)->toBe(CommissionOrigin::ManualOverride)
        // 10% of the new 200 net: the override survives, its amount follows.
        ->and((float) $commission->calculated_amount)->toBe(20.0);
});

// ---------------------------------------------------------------------------
// The shared rules apply verbatim on this channel
// ---------------------------------------------------------------------------

it('PATCH rejects a row with a non-positive quantity (AC-034)', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 0, 'unit_price' => 10]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.quantity');
});

it('PATCH refuses a second row when the classification is managed on a single category (spec 0077)', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory(CategoryManagementMode::Single);
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 20],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines');
});

it('PATCH widens the opportunity coverage with an off-category product (D-7, multiple mode)', function () {
    $actor = offerLineWriteActor();
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $otherCategory = offerLineCategory();
    $product = Product::factory()->create(['category_id' => $otherCategory->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertOk();

    expect($quote->opportunity->productLines()->pluck('product_category_id')->all())
        ->toContain($otherCategory->id);
});

it('a locked actor cannot write the offer lines', function () {
    $actor = offerLineWriteActor(['view', 'viewAll']);
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertForbidden();

    expect($quote->offerLines()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// POST — the create form can open a request that already knows its rows
// ---------------------------------------------------------------------------

it('create: the submitted offer lines land on the created Offerta', function () {
    $actor = offerLineWriteActor(['create']);
    $category = offerLineCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50]],
    ])->assertCreated();

    expect($response->json('data.offer_lines'))->toHaveCount(1);

    $quote = Quote::query()->sole();
    expect($quote->offerLines()->sole()->product_id)->toBe($product->id)
        ->and((float) $quote->revenue_net)->toBe(150.0);
});

it('create: no offer line submitted still opens an empty offer (AC-028)', function () {
    $actor = offerLineWriteActor(['create']);
    $category = offerLineCategory();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
    ])->assertCreated();

    expect(Quote::query()->sole()->offerLines()->count())->toBe(0);
});

it('create: a second row on a single-managed classification is refused, nothing is created', function () {
    $actor = offerLineWriteActor(['create']);
    $category = offerLineCategory(CategoryManagementMode::Single);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 20],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines');

    expect(Opportunity::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// GRID CELL — the third channel (user directive 2026-09-07), onto the very
// same updateWork(): the generic inline-edit engine, whose value is the WHOLE
// row collection and whose per-row rules are App\Quotes\QuoteLineRules — the
// one definition the two channels above already validate against.
// ---------------------------------------------------------------------------

it('cell: PATCH replaces the offer REVENUE rows through the generic engine', function () {
    // The generic table endpoints gate on `viewAny` before anything else
    // (TableController::updateRow), unlike the module's own PATCH above.
    $actor = offerLineWriteActor(['viewAny', 'view', 'update', 'viewAll']);
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 150]],
    ])->assertOk();

    $line = $quote->offerLines()->sole();
    expect($line->product_id)->toBe($product->id)
        ->and((float) $line->quantity)->toBe(2.0)
        ->and((float) $line->unit_price)->toBe(150.0);
});

it('cell: the value is a ROW collection — a bare list of product ids is refused', function () {
    $actor = offerLineWriteActor(['viewAny', 'view', 'update', 'viewAll']);
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [$product->id],
    ])->assertStatus(422)->assertJsonValidationErrors('value.0.product_id');

    expect($quote->offerLines()->count())->toBe(0);
});

it('cell: a row breaking a shared quote-line rule is refused, never rounded away', function () {
    $actor = offerLineWriteActor(['viewAny', 'view', 'update', 'viewAll']);
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    // `quantity` is `gt:0` (AC-034) in the shared definition.
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $product->id, 'quantity' => 0, 'unit_price' => 10]],
    ])->assertStatus(422)->assertJsonValidationErrors('value.0.quantity');
});

it('cell: commissions stay this endpoint\'s forbidden block, exactly as on the panel', function () {
    $actor = offerLineWriteActor(['viewAny', 'view', 'update', 'viewAll']);
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
            // Non-empty on purpose: Laravel's `prohibited` passes on an empty
            // value, so an empty array would prove nothing.
            'commissions' => [['recipient_role' => 'operator']],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('value.0.commissions');
});

it('cell: the write needs the module update permission, like every other cell', function () {
    $actor = offerLineWriteActor(['viewAny', 'view', 'viewAll']);
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertForbidden();
});

it('cell: the per-field matrix decides too — offer_lines denied is read-only in the config AND a 403 on write', function () {
    $actor = offerLineMatrixActor([
        'resource' => 'request-management',
        'field' => 'offer_lines',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $category = offerLineCategory();
    $quote = offerLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    // The UI hint first: the cell never offers the editor...
    expect($columns['offer_lines']['editable'])->toBeFalse()
        // ...while a sibling editable column is untouched, so this is the ONE
        // key's gate and not a blanket denial.
        ->and($columns['next_callback_at']['editable'])->toBeTrue();

    // ...and the endpoint refuses regardless of what the client believes.
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertForbidden();

    expect($quote->offerLines()->count())->toBe(0);
});
