<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// Spec 0288, D-3/D-4/D-5 (AC-006/007/008): a conditional section of the
// attribute layout relaxes its required attributes and is emptied while its
// controlling toggle is off — on every write surface (Prodotto, Offerta,
// Commessa, Gestione Richieste), store and update. The layout row is written
// straight on the model so the suite does not depend on the layout-editor
// contract (MT-1).

uses(RefreshDatabase::class);

const CONDITIONAL_TOGGLE = 'has_extra';
const CONDITIONAL_NOTE = 'extra_note';

/**
 * A category with a boolean toggle (open section) and a REQUIRED text
 * attribute in a second section shown only while the toggle is on.
 */
function conditionalValuesCategory(AttributeContext $context, array $categoryAttributes = []): ProductCategory
{
    $category = ProductCategory::factory()->create($categoryAttributes);
    $toggle = Attribute::factory()->create(['code' => CONDITIONAL_TOGGLE, 'type' => 'boolean']);
    $note = Attribute::factory()->create(['code' => CONDITIONAL_NOTE, 'type' => 'text']);
    $category->attributes()->attach($toggle->id, ['is_required' => false, 'sort_order' => 0, 'context' => $context->value]);
    $category->attributes()->attach($note->id, ['is_required' => true, 'sort_order' => 1, 'context' => $context->value]);

    $section = static fn (string $code, ?string $control): array => [
        'id' => (string) Str::uuid(),
        'title' => $code,
        'description' => null,
        'variant' => 'default',
        'collapsible' => false,
        'default_collapsed' => false,
        'columns' => 1,
        'sort_order' => 0,
        'visible_when' => $control === null ? null : ['attribute_code' => $control],
        'rows' => [['id' => (string) Str::uuid(), 'items' => [['attribute_code' => $code, 'width' => 'full']]]],
    ];

    AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => $context->value,
        'form_mode' => LayoutFormScope::All->value,
        'layout' => ['sections' => [$section(CONDITIONAL_TOGGLE, null), $section(CONDITIONAL_NOTE, CONDITIONAL_TOGGLE)]],
    ]);

    return $category;
}

/**
 * @param  array<int, string>  $abilities
 */
function conditionalActor(string $module, array $abilities): User
{
    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        Permission::findOrCreate("{$module}.{$ability}");
        $user->givePermissionTo("{$module}.{$ability}");
    }

    return $user;
}

/**
 * One write surface: `store(values)` creates a record, `update(values)`
 * PATCHes the last created one, `values()` reads its persisted map.
 *
 * @return array{store: Closure, update: Closure, values: Closure, model: class-string<Model>}
 */
function conditionalSurface(string $name): array
{
    return match ($name) {
        'product' => (function (): array {
            Sanctum::actingAs(conditionalActor('products', ['viewAny', 'view', 'create', 'update']));
            $category = conditionalValuesCategory(AttributeContext::Product);

            return [
                'model' => Product::class,
                'store' => fn (array $values): TestResponse => test()->postJson('/api/products', [
                    'name' => 'Widget', 'category_id' => $category->id, 'cost' => 10, 'price' => 20,
                    'product_type' => 'SERVICE', 'attribute_values' => $values,
                ]),
                'update' => fn (array $values): TestResponse => test()->patchJson('/api/products/'.Product::query()->latest('id')->value('id'), ['attribute_values' => $values]),
            ];
        })(),
        'quote' => (function (): array {
            Sanctum::actingAs(conditionalActor('quotes', ['viewAny', 'view', 'create', 'update']));
            $category = conditionalValuesCategory(AttributeContext::Quote, ['business_function_id' => BusinessFunction::factory()->create()->id]);
            $product = Product::factory()->create(['category_id' => $category->id]);

            return [
                'model' => Quote::class,
                'store' => fn (array $values): TestResponse => test()->postJson('/api/quotes', [
                    'title' => 'Offerta', 'opportunity_id' => Opportunity::factory()->create()->id,
                    'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
                    'attribute_values' => $values,
                ]),
                'update' => fn (array $values): TestResponse => test()->patchJson('/api/quotes/'.Quote::query()->latest('id')->value('id'), ['attribute_values' => $values]),
            ];
        })(),
        'work-order' => (function (): array {
            Sanctum::actingAs(conditionalActor('work-orders', ['viewAny', 'view', 'create', 'update', 'viewAll']));
            $category = conditionalValuesCategory(AttributeContext::WorkOrder);
            $product = Product::factory()->create(['category_id' => $category->id]);

            return [
                'model' => WorkOrder::class,
                'store' => function (array $values) use ($product): TestResponse {
                    $quote = Quote::factory()->create();
                    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

                    return test()->postJson('/api/work-orders', [
                        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'title' => 'Commessa', 'type' => 'processing',
                        'quote_line_ids' => [$line->id], 'attribute_values' => $values,
                    ]);
                },
                'update' => fn (array $values): TestResponse => test()->patchJson('/api/work-orders/'.WorkOrder::query()->latest('id')->value('id'), ['attribute_values' => $values]),
            ];
        })(),
        'request' => (function (): array {
            Sanctum::actingAs(requestManagementUserWith(['viewAny', 'view', 'create', 'update', 'viewAll', 'viewActivity']));
            $category = conditionalValuesCategory(AttributeContext::Quote, ['business_function_id' => BusinessFunction::factory()->create()->id]);

            return [
                'model' => Quote::class,
                'store' => fn (array $values): TestResponse => test()->postJson('/api/request-management', [
                    'registry_id' => Registry::factory()->create()->id,
                    'source_id' => Source::factory()->create()->id,
                    'product_lines' => [['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id]],
                    'attribute_values' => $values,
                ]),
                'update' => fn (array $values): TestResponse => test()->patchJson('/api/request-management/'.Quote::query()->latest('id')->value('id'), ['attribute_values' => $values]),
            ];
        })(),
    };
}

/** @param array{model: class-string<Model>} $surface */
function conditionalPersisted(array $surface): array
{
    return $surface['model']::query()->latest('id')->firstOrFail()->attribute_values ?? [];
}

// ---------------------------------------------------------------------------
// AC-006 — toggle off: the required attribute of the hidden section is relaxed
// ---------------------------------------------------------------------------

it('AC-006 store: toggle off and the required hidden attribute empty -> created, section empty', function (string $name): void {
    $surface = conditionalSurface($name);

    $surface['store']([CONDITIONAL_TOGGLE => false, CONDITIONAL_NOTE => ''])->assertCreated();

    $values = conditionalPersisted($surface);
    expect($values[CONDITIONAL_TOGGLE])->toBeFalse()
        ->and($values[CONDITIONAL_NOTE] ?? null)->toBeNull();
})->with(['product', 'quote', 'work-order', 'request']);

it('AC-006 store: toggle off with a value in the hidden section -> the value is emptied', function (string $name): void {
    $surface = conditionalSurface($name);

    $surface['store']([CONDITIONAL_TOGGLE => false, CONDITIONAL_NOTE => 'leftover'])->assertCreated();

    expect(conditionalPersisted($surface)[CONDITIONAL_NOTE] ?? null)->toBeNull();
})->with(['product', 'quote', 'work-order', 'request']);

it('AC-006 update: toggle off and the required hidden attribute empty -> saved, section empty', function (string $name): void {
    $surface = conditionalSurface($name);
    $surface['store']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => 'filled'])->assertCreated();

    $surface['update']([CONDITIONAL_TOGGLE => false, CONDITIONAL_NOTE => ''])->assertOk();

    $values = conditionalPersisted($surface);
    expect($values[CONDITIONAL_TOGGLE])->toBeFalse()
        ->and($values[CONDITIONAL_NOTE] ?? null)->toBeNull();
})->with(['product', 'quote', 'work-order', 'request']);

// ---------------------------------------------------------------------------
// AC-007 — toggle on: the required attribute is enforced as before
// ---------------------------------------------------------------------------

it('AC-007 store: toggle on and the required attribute empty -> 422', function (string $name): void {
    $surface = conditionalSurface($name);

    $surface['store']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.'.CONDITIONAL_NOTE);
})->with(['product', 'quote', 'work-order', 'request']);

it('AC-007 update: toggle on and the required attribute empty -> 422, nothing written', function (string $name): void {
    $surface = conditionalSurface($name);
    $surface['store']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => 'filled'])->assertCreated();

    $surface['update']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.'.CONDITIONAL_NOTE);

    expect(conditionalPersisted($surface)[CONDITIONAL_NOTE])->toBe('filled');
})->with(['product', 'quote', 'work-order', 'request']);

it('AC-007 update: a persisted toggle on keeps the section required for a note-only PATCH', function (string $name): void {
    $surface = conditionalSurface($name);
    $surface['store']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => 'filled'])->assertCreated();

    $surface['update']([CONDITIONAL_NOTE => ''])->assertStatus(422)->assertJsonValidationErrors('attribute_values.'.CONDITIONAL_NOTE);
})->with(['product', 'quote', 'work-order', 'request']);

// ---------------------------------------------------------------------------
// AC-008 — a toggle-only PATCH empties the section it controls
// ---------------------------------------------------------------------------

it('AC-008 update: a PATCH with only the toggle off empties the filled section', function (string $name): void {
    $surface = conditionalSurface($name);
    $surface['store']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => 'filled'])->assertCreated();

    $surface['update']([CONDITIONAL_TOGGLE => false])->assertOk();

    $values = conditionalPersisted($surface);
    expect($values[CONDITIONAL_TOGGLE])->toBeFalse()
        ->and($values[CONDITIONAL_NOTE])->toBeNull();
})->with(['product', 'quote', 'work-order', 'request']);

it('AC-008 update: a toggle-only PATCH back on leaves the value untouched', function (string $name): void {
    $surface = conditionalSurface($name);
    $surface['store']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => 'filled'])->assertCreated();

    $surface['update']([CONDITIONAL_TOGGLE => true])->assertOk();

    expect(conditionalPersisted($surface)[CONDITIONAL_NOTE])->toBe('filled');
})->with(['product', 'quote', 'work-order', 'request']);

it('AC-008 audit: the emptied section lands in the attribute_values diff of the request-management log', function (): void {
    $surface = conditionalSurface('request');
    $surface['store']([CONDITIONAL_TOGGLE => true, CONDITIONAL_NOTE => 'filled'])->assertCreated();

    $surface['update']([CONDITIONAL_TOGGLE => false])->assertOk();

    $opportunityId = Quote::query()->latest('id')->firstOrFail()->opportunity_id;
    $activity = Activity::query()
        ->where('subject_type', (new Opportunity)->getMorphClass())
        ->where('subject_id', $opportunityId)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->properties['attributes']['attribute_values'][CONDITIONAL_NOTE])->toBeNull()
        ->and($activity->properties['old']['attribute_values'][CONDITIONAL_NOTE])->toBe('filled');
});
