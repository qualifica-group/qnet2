<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0098: the dynamic "Informazioni aggiuntive" section on the Commessa —
// resolved from THIS work order's own `quoteLines` (D-1), in the new
// independent `work_order` usage context (D-2). Covers AC-003 through
// AC-019 (AC-001/002 live in tests/Unit/Models/WorkOrderAttributeValuesTest;
// AC-020..025 are FE/i18n scope).

uses(RefreshDatabase::class);

if (! function_exists('workOrderAttrUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderAttrUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        // `viewAll`: this suite is about the attribute pipeline, not the
        // membership scoping (user directive 2026-09-02) — the actor keeps
        // seeing every commessa it creates, whether or not it is a
        // Responsabile/Partecipante of it.
        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

if (! function_exists('workOrderAttrCategory')) {
    /**
     * A product category carrying one WORK_ORDER-context attribute, plus a
     * product filed on it.
     *
     * @return array{category: ProductCategory, product: Product, attribute: Attribute}
     */
    function workOrderAttrCategory(string $code, string $type = 'text', bool $required = false, array $extra = []): array
    {
        $category = ProductCategory::factory()->create();
        $attribute = Attribute::factory()->create(array_merge(['code' => $code, 'type' => $type], $extra));
        $category->attributes()->attach($attribute->id, [
            'is_required' => $required, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value,
        ]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        return ['category' => $category, 'product' => $product, 'attribute' => $attribute];
    }
}

if (! function_exists('createWorkOrderWithLine')) {
    function createWorkOrderWithLine(Quote $quote, Product $product, array $extra = []): TestResponse
    {
        $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

        return \Pest\Laravel\postJson('/api/work-orders', array_merge([
            ...workOrderRequiredFields(),
            'quote_id' => $quote->id,
            'title' => 'Commessa',
            'type' => 'processing',
            'quote_line_ids' => [$line->id],
        ], $extra));
    }
}

// ---------------------------------------------------------------------------
// AC-003/004 — the third context is independent of product/quote
// ---------------------------------------------------------------------------

it('AC-003: assigning an attribute to work_order context does not alter the same category\'s product/quote sets', function () {
    $category = ProductCategory::factory()->create();
    $productAttribute = Attribute::factory()->create(['code' => 'p_only']);
    $quoteAttribute = Attribute::factory()->create(['code' => 'q_only']);
    $workOrderAttribute = Attribute::factory()->create(['code' => 'wo_only']);

    $category->attributes()->attach($productAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $category->attributes()->attach($quoteAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $category->attributes()->attach($workOrderAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'work_order']);

    $product = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::Product);
    $quote = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::Quote);
    $workOrder = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::WorkOrder);

    expect($product->pluck('code')->all())->toBe(['p_only'])
        ->and($quote->pluck('code')->all())->toBe(['q_only'])
        ->and($workOrder->pluck('code')->all())->toBe(['wo_only']);
});

it('AC-004: inherits_work_order_attributes=false blocks ONLY the work_order inheritance, quote keeps inheriting', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->notInheritingIn(AttributeContext::WorkOrder)->create();

    $quoteAttribute = Attribute::factory()->create(['code' => 'q_inherited']);
    $workOrderAttribute = Attribute::factory()->create(['code' => 'wo_inherited']);
    $root->attributes()->attach($quoteAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $root->attributes()->attach($workOrderAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'work_order']);

    $quoteSet = app(AttributeSetResolver::class)->resolve([$child->id], AttributeContext::Quote);
    $workOrderSet = app(AttributeSetResolver::class)->resolve([$child->id], AttributeContext::WorkOrder);

    expect($quoteSet->pluck('code')->all())->toBe(['q_inherited'])
        ->and($workOrderSet->pluck('code')->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-005/006 — effective-attributes + layout accept the new context
// ---------------------------------------------------------------------------

it('AC-005: GET effective-attributes?context=work_order responds 200 with the effective set', function () {
    $actor = workOrderAttrUserWith([]);
    Permission::findOrCreate('product-categories.view');
    $actor->givePermissionTo('product-categories.view');
    ['category' => $category] = workOrderAttrCategory('wo_field');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=work_order")->assertOk();

    expect(collect($response->json('data'))->pluck('code')->all())->toBe(['wo_field']);
});

it('AC-006: GET|PUT attribute-layouts with context=work_order persists a row distinct from product/quote for the same (category, form_mode)', function () {
    Permission::findOrCreate('product-categories.view');
    Permission::findOrCreate('product-categories.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['product-categories.view', 'product-categories.update']);
    ['category' => $category, 'attribute' => $attribute] = workOrderAttrCategory('layout_field');
    $category->attributes()->attach(
        Attribute::factory()->create(['code' => 'quote_layout_field'])->id,
        ['is_required' => false, 'sort_order' => 0, 'context' => 'quote'],
    );
    Sanctum::actingAs($actor);

    $layoutBlob = [
        'sections' => [[
            'id' => 'sec-1', 'title' => 'General', 'description' => null, 'variant' => 'default',
            'collapsible' => true, 'default_collapsed' => false, 'columns' => 2, 'sort_order' => 0,
            'rows' => [['id' => 'row-1', 'items' => [['attribute_code' => $attribute->code, 'width' => 'half']]]],
        ]],
    ];

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'work_order', 'form_mode' => 'create', 'layout' => $layoutBlob,
    ])->assertOk();

    expect(AttributeLayout::query()->where('product_category_id', $category->id)->count())->toBe(1);

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'quote', 'form_mode' => 'create',
        'layout' => ['sections' => [['id' => 'sec-1', 'title' => 'Q', 'description' => null, 'variant' => 'default', 'collapsible' => true, 'default_collapsed' => false, 'columns' => 2, 'sort_order' => 0, 'rows' => [['id' => 'row-1', 'items' => [['attribute_code' => 'quote_layout_field', 'width' => 'half']]]]]]],
    ])->assertOk();

    expect(AttributeLayout::query()->where('product_category_id', $category->id)->count())->toBe(2);

    $workOrderLayout = $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=work_order&form_mode=create")->assertOk();
    expect($workOrderLayout->json('data.layout.sections.0.rows.0.items.0.attribute_code'))->toBe('layout_field');
});

// ---------------------------------------------------------------------------
// AC-007/008/009 — WorkOrderAttributeResolver: OWN quoteLines only
// ---------------------------------------------------------------------------

it('AC-007: resolve() unions dedup-per-code the work_order attributes of the categories of the work order\'s OWN quoteLines, strictest is_required wins', function () {
    $categoryOne = ProductCategory::factory()->create();
    $categoryTwo = ProductCategory::factory()->create();
    $shared = Attribute::factory()->create(['code' => 'shared_code', 'type' => 'text']);
    $categoryOne->attributes()->attach($shared->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'work_order']);
    $categoryTwo->attributes()->attach($shared->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'work_order']);
    $productA = Product::factory()->create(['category_id' => $categoryOne->id]);
    $productB = Product::factory()->create(['category_id' => $categoryTwo->id]);

    $actor = workOrderAttrUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $lineA = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productA->id]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productB->id]);

    $created = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => $quote->id, 'title' => 'Both lines', 'type' => 'processing',
        'quote_line_ids' => [$lineA->id, $lineB->id],
        'attribute_values' => ['shared_code' => 'value'],
    ])->assertCreated();

    $byCode = collect($created->json('data.applicable_attributes'))->keyBy('code');
    expect($byCode['shared_code']['is_required'])->toBeTrue();
});

it('AC-008: two work orders of the SAME quote with different-category lines resolve DIFFERENT attribute sets (proof of D-1)', function () {
    ['product' => $productA] = workOrderAttrCategory('field_a');
    ['product' => $productB] = workOrderAttrCategory('field_b');
    $actor = workOrderAttrUserWith(['create']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $lineA = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productA->id]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productB->id]);

    $workOrderA = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'title' => 'WO A', 'type' => 'processing',
        'quote_line_ids' => [$lineA->id],
    ])->assertCreated();

    $workOrderB = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'title' => 'WO B', 'type' => 'processing',
        'quote_line_ids' => [$lineB->id],
    ])->assertCreated();

    expect(collect($workOrderA->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['field_a'])
        ->and(collect($workOrderB->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['field_b']);
});

it('AC-009: a work order with no linked quoteLines resolves an empty set and a null layout, no errors', function () {
    $actor = workOrderAttrUserWith(['create']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $created = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'title' => 'No lines', 'type' => 'processing',
    ])->assertCreated();

    expect($created->json('data.applicable_attributes'))->toBe([])
        ->and($created->json('data.attribute_layout'))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-010..013 — write-side validation
// ---------------------------------------------------------------------------

it('AC-010: POST with valid attribute_values persists them; the column cannot be set via mass assignment', function () {
    ['product' => $product] = workOrderAttrCategory('warehouse_size');
    $actor = workOrderAttrUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $created = createWorkOrderWithLine($quote, $product, ['attribute_values' => ['warehouse_size' => '120']])
        ->assertCreated();

    expect($created->json('data.attribute_values.warehouse_size'))->toBe('120');

    $workOrder = WorkOrder::find($created->json('data.id'));
    expect($workOrder->attribute_values)->toBe(['warehouse_size' => '120']);
});

it('AC-011: a code not applicable to the work order\'s own lines -> 422 keyed attribute_values.<code>', function () {
    ['product' => $product] = workOrderAttrCategory('applicable_field');
    $actor = workOrderAttrUserWith(['create']);
    Sanctum::actingAs($actor);

    $otherAttribute = Attribute::factory()->create(['code' => 'not_applicable_here', 'type' => 'text']);
    $otherCategory = ProductCategory::factory()->create();
    $otherCategory->attributes()->attach($otherAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'work_order']);

    $quote = Quote::factory()->create();
    createWorkOrderWithLine($quote, $product, ['attribute_values' => ['not_applicable_here' => 'x']])
        ->assertStatus(422)->assertJsonValidationErrors('attribute_values.not_applicable_here');
});

it('AC-012: a required attribute submitted empty -> 422; the SAME code omitted entirely is not enforced (sparse semantics)', function () {
    ['product' => $product] = workOrderAttrCategory('mandatory_field', required: true);
    $actor = workOrderAttrUserWith(['create']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    createWorkOrderWithLine($quote, $product, ['attribute_values' => ['mandatory_field' => '']])
        ->assertStatus(422)->assertJsonValidationErrors('attribute_values.mandatory_field');

    createWorkOrderWithLine($quote, $product)->assertCreated();
});

it('AC-013: PATCH sparse merge — a code omitted from the payload keeps its persisted value', function () {
    ['product' => $product] = workOrderAttrCategory('code_one');
    $categoryTwoProduct = workOrderAttrCategory('code_two');
    $actor = workOrderAttrUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $category = ProductCategory::where('id', $product->category_id)->sole();
    $category->attributes()->attach($categoryTwoProduct['attribute']->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'work_order']);

    $quote = Quote::factory()->create();
    $created = createWorkOrderWithLine($quote, $product, [
        'attribute_values' => ['code_one' => 'first', 'code_two' => 'second'],
    ])->assertCreated();

    $this->patchJson("/api/work-orders/{$created->json('data.id')}", [
        'attribute_values' => ['code_one' => 'updated'],
    ])->assertOk()
        ->assertJsonPath('data.attribute_values.code_one', 'updated')
        ->assertJsonPath('data.attribute_values.code_two', 'second');
});

// ---------------------------------------------------------------------------
// AC-014/015 — ordering + transactional rollback
// ---------------------------------------------------------------------------

it('AC-014: a PATCH changing quote_line_ids AND attribute_values in the same request validates against the NEW lines', function () {
    ['product' => $productA] = workOrderAttrCategory('field_a');
    ['product' => $productB] = workOrderAttrCategory('field_b');
    $actor = workOrderAttrUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $lineA = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productA->id]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productB->id]);

    $created = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'title' => 'Swap', 'type' => 'processing',
        'quote_line_ids' => [$lineA->id], 'attribute_values' => ['field_a' => 'x'],
    ])->assertCreated();

    // Swapping to line B's category in the same request: field_b (new) is
    // accepted, field_a (stale, no longer applicable) is rejected — proving
    // the validated set is the POST-sync one, not the pre-PATCH one.
    $this->patchJson("/api/work-orders/{$created->json('data.id')}", [
        'quote_line_ids' => [$lineB->id], 'attribute_values' => ['field_a' => 'still x'],
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_values.field_a');

    $this->patchJson("/api/work-orders/{$created->json('data.id')}", [
        'quote_line_ids' => [$lineB->id], 'attribute_values' => ['field_b' => 'y'],
    ])->assertOk()->assertJsonPath('data.attribute_values.field_b', 'y');
});

it('AC-015: a 422 on attribute_values rolls back the quote_line_ids/supervisor_ids submitted in the same request', function () {
    ['product' => $product] = workOrderAttrCategory('untouched_field');
    $actor = workOrderAttrUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $originalLine = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    $created = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'title' => 'Rollback', 'type' => 'processing',
        'quote_line_ids' => [$originalLine->id],
    ])->assertCreated();
    $workOrderId = $created->json('data.id');

    $newLine = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $newSupervisor = User::factory()->create();

    $this->patchJson("/api/work-orders/{$workOrderId}", [
        'quote_line_ids' => [$newLine->id],
        'supervisor_ids' => [$newSupervisor->id],
        'attribute_values' => ['not_applicable_at_all' => 'x'],
    ])->assertStatus(422);

    $workOrder = WorkOrder::find($workOrderId);
    expect($workOrder->quoteLines()->pluck('quote_lines.id')->all())->toBe([$originalLine->id])
        ->and($workOrder->supervisors()->pluck('users.id')->all())->not->toContain($newSupervisor->id);
});

// ---------------------------------------------------------------------------
// AC-016 — WorkOrderResource exposes the trio without N+1
// ---------------------------------------------------------------------------

it('AC-016: WorkOrderResource exposes attribute_values/applicable_attributes/attribute_layout without N+1 (preventLazyLoading stays satisfied)', function () {
    ['product' => $product] = workOrderAttrCategory('layout_probe');
    AttributeLayout::factory()->for(ProductCategory::where('id', $product->category_id)->sole(), 'productCategory')
        ->withCodes(['layout_probe'], title: 'Section')
        ->create(['context' => AttributeContext::WorkOrder->value, 'form_mode' => LayoutFormScope::All->value]);
    $actor = workOrderAttrUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $created = createWorkOrderWithLine($quote, $product, ['attribute_values' => ['layout_probe' => 'x']])
        ->assertCreated();

    // preventLazyLoading() is active outside production (backend.md §3): a
    // relation this Resource lazy-loads instead of relying on
    // DETAIL_RELATIONS' eager load would THROW, not merely be slow — so a
    // clean 200 here is itself the N+1 proof.
    $shown = $this->getJson("/api/work-orders/{$created->json('data.id')}")->assertOk();

    expect($shown->json('data.attribute_values.layout_probe'))->toBe('x')
        ->and(collect($shown->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['layout_probe'])
        ->and($shown->json('data.attribute_layout.sections.0.title'))->toBe('Section');
});

// ---------------------------------------------------------------------------
// AC-017/018 — POST /api/work-orders/form-context
// ---------------------------------------------------------------------------

it('AC-017: form-context resolves set+layout for the submitted quote_line_ids; absent/empty -> empty set, null layout, 200', function () {
    ['product' => $product] = workOrderAttrCategory('preview_field');
    $actor = workOrderAttrUserWith(['create']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

    $preview = $this->postJson('/api/work-orders/form-context', ['quote_line_ids' => [$line->id]])->assertOk();
    expect(collect($preview->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['preview_field']);

    $empty = $this->postJson('/api/work-orders/form-context', [])->assertOk();
    expect($empty->json('data.applicable_attributes'))->toBe([])
        ->and($empty->json('data.attribute_layout'))->toBeNull();
});

it('AC-017: form-context with unsaved quote_line_ids matches what saving the same lines would produce', function () {
    ['product' => $product] = workOrderAttrCategory('match_field');
    $actor = workOrderAttrUserWith(['create']);
    Sanctum::actingAs($actor);

    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

    $preview = $this->postJson('/api/work-orders/form-context', ['quote_line_ids' => [$line->id]])->assertOk();
    $saved = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'title' => 'Match', 'type' => 'processing',
        'quote_line_ids' => [$line->id],
    ])->assertCreated();

    expect($preview->json('data.applicable_attributes'))->toBe($saved->json('data.applicable_attributes'));
});

it('AC-018: form-context without work-orders.create or work-orders.update -> 403', function () {
    Sanctum::actingAs(workOrderAttrUserWith([]));

    $this->postJson('/api/work-orders/form-context', ['quote_line_ids' => []])->assertForbidden();
});

it('AC-018: form-context with only work-orders.update (no create) is authorized', function () {
    Sanctum::actingAs(workOrderAttrUserWith(['update']));

    $this->postJson('/api/work-orders/form-context', ['quote_line_ids' => []])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-019 — Contract "Programma" dialog generation
// ---------------------------------------------------------------------------

it('AC-019: POST /api/contracts/{contract}/work-orders accepts attribute_values and persists them on the generated work order', function () {
    ['product' => $product] = workOrderAttrCategory('generation_field');

    Permission::findOrCreate('contracts.program');
    Permission::findOrCreate('work-orders.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id, 'product_id' => $product->id]);

    $response = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        ...workOrderRequiredFields(),
        'title' => 'Generated', 'type' => 'processing',
        'quote_line_ids' => [$line->id],
        'attribute_values' => ['generation_field' => 'from contract'],
    ])->assertCreated();

    expect($response->json('data.attribute_values.generation_field'))->toBe('from contract');

    $workOrder = WorkOrder::find($response->json('data.id'));
    expect($workOrder->attribute_values)->toBe(['generation_field' => 'from contract']);
});
