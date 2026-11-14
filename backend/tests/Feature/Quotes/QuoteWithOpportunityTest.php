<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0396: POST /api/quotes/with-opportunity (AC-001..009) and the
 * `product_category_ids` of POST /api/quotes/form-context (AC-011).
 */
uses(RefreshDatabase::class);

if (! function_exists('withOppActor')) {
    /**
     * @param  array<int, string>  $abilities  `quotes.*` / `opportunities.*` names
     */
    function withOppActor(array $abilities = ['quotes.create', 'opportunities.create']): User
    {
        $user = User::factory()->create();

        foreach ([...$abilities, 'quotes.viewAll', 'opportunities.viewAll'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate($ability, 'web'));
        }

        return $user;
    }
}

if (! function_exists('withOppCategory')) {
    function withOppCategory(bool $multipleOpen = false): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'multiple_open_opportunities' => $multipleOpen,
        ]);
    }
}

if (! function_exists('withOppPayload')) {
    /**
     * A valid {opportunity, quote} body on $category (both blocks share it so
     * the offer line never needs a coverage addition).
     *
     * @param  array<string, mixed>  $opportunity
     * @param  array<string, mixed>  $quote
     * @return array{opportunity: array<string, mixed>, quote: array<string, mixed>}
     */
    function withOppPayload(?Registry $registry = null, ?ProductCategory $category = null, array $opportunity = [], array $quote = []): array
    {
        $category ??= withOppCategory();
        $product = Product::factory()->create(['category_id' => $category->id]);

        return [
            'opportunity' => array_merge([
                'registry_id' => ($registry ?? Registry::factory()->create())->id,
                'product_lines' => [
                    ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
                ],
            ], $opportunity),
            'quote' => array_merge([
                'title' => 'Offerta',
                'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
            ], $quote),
        ];
    }
}

it('AC-001: creates the opportunity and the quote together', function () {
    Sanctum::actingAs(withOppActor());
    $ga = User::factory()->create();

    $response = $this->postJson('/api/quotes/with-opportunity', withOppPayload(quote: ['manager_slots' => [$ga->id]]))
        ->assertCreated()
        ->assertJsonPath('message', 'Created');

    $opportunity = Opportunity::query()->sole();
    $quote = Quote::query()->sole();

    expect($response->json('data.id'))->toBe($quote->id)
        ->and($response->json('data.opportunity_id'))->toBe($opportunity->id)
        ->and($quote->opportunity_id)->toBe($opportunity->id)
        ->and($quote->code)->toStartWith('QUO-')
        ->and($quote->offerLines()->count())->toBe(1)
        ->and($opportunity->productLines()->count())->toBe(1)
        ->and($opportunity->managers()->pluck('users.id')->all())->toBe([$ga->id]);
});

it('AC-002: an invalid quote block -> 422 quote.* and no opportunity is left behind', function () {
    Sanctum::actingAs(withOppActor());
    $payload = withOppPayload();
    unset($payload['quote']['offer_lines']);

    $this->postJson('/api/quotes/with-opportunity', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('quote.offer_lines');

    expect(Opportunity::query()->count())->toBe(0)->and(Quote::query()->count())->toBe(0);
});

it('AC-002: a quote line the service rejects rolls the opportunity back', function () {
    Sanctum::actingAs(withOppActor());
    $payload = withOppPayload(quote: ['attribute_values' => ['no_such_code' => 'x']]);

    $response = $this->postJson('/api/quotes/with-opportunity', $payload);

    expect($response->status())->toBe(422)
        ->and(array_keys($response->json('errors')))->each->toStartWith('quote.')
        ->and(Opportunity::query()->count())->toBe(0);
});

it('AC-003: an invalid opportunity block -> 422 opportunity.* and nothing created', function () {
    Sanctum::actingAs(withOppActor());

    $this->postJson('/api/quotes/with-opportunity', withOppPayload(opportunity: ['product_lines' => []]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('opportunity.product_lines');

    expect(Opportunity::query()->count())->toBe(0)->and(Quote::query()->count())->toBe(0);
});

it('AC-004: a blocking open opportunity -> 422 on registry_id and existing_opportunity_id, nothing created', function () {
    Sanctum::actingAs(withOppActor());
    $registry = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $registry->id]);

    $response = $this->postJson('/api/quotes/with-opportunity', withOppPayload($registry))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['opportunity.registry_id', 'opportunity.existing_opportunity_id']);

    expect($response->json('errors')['opportunity.existing_opportunity_id'])->toBe([(string) $open->id])
        ->and(Opportunity::query()->count())->toBe(1)
        ->and(Quote::query()->count())->toBe(0);
});

it('AC-005: new lines on an exempt category are created beside an open opportunity', function () {
    Sanctum::actingAs(withOppActor());
    $registry = Registry::factory()->create();
    Opportunity::factory()->create(['registry_id' => $registry->id]);

    $this->postJson('/api/quotes/with-opportunity', withOppPayload($registry, withOppCategory(multipleOpen: true)))
        ->assertCreated();

    expect(Opportunity::query()->count())->toBe(2);
});

it('AC-006: without opportunities.create or quotes.create -> 403 and nothing created', function (string $missing) {
    $abilities = array_values(array_diff(['quotes.create', 'opportunities.create'], [$missing]));
    Sanctum::actingAs(withOppActor($abilities));

    $this->postJson('/api/quotes/with-opportunity', withOppPayload())->assertForbidden();

    expect(Opportunity::query()->count())->toBe(0)->and(Quote::query()->count())->toBe(0);
})->with(['quotes.create', 'opportunities.create']);

it('AC-007: lead_id and opportunity_id are refused with prefixed keys', function () {
    Sanctum::actingAs(withOppActor());
    $payload = withOppPayload(opportunity: ['lead_id' => 1], quote: ['opportunity_id' => 1]);

    $this->postJson('/api/quotes/with-opportunity', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['opportunity.lead_id', 'quote.opportunity_id']);

    expect(Opportunity::query()->count())->toBe(0);
});

it('AC-008: a quote GA missing from the new opportunity team is promoted, no appartenenza 422', function () {
    Sanctum::actingAs(withOppActor());
    $ga = User::factory()->create();

    $this->postJson('/api/quotes/with-opportunity', withOppPayload(quote: ['manager_slots' => [$ga->id]]))
        ->assertCreated();

    expect(Opportunity::query()->sole()->managers()->pluck('users.id')->all())->toContain($ga->id)
        ->and(Quote::query()->sole()->managers()->pluck('users.id')->all())->toBe([$ga->id]);
});

it('AC-009: a non-editable field in either block -> 422 on the prefixed key', function (string $resource, string $field, string $block) {
    foreach (['quotes.create', 'opportunities.create', 'quotes.viewAll', 'opportunities.viewAll'] as $ability) {
        Permission::findOrCreate($ability, 'web');
    }
    $role = Role::create(['name' => 'locked-'.$resource]);
    $role->givePermissionTo(['quotes.create', 'opportunities.create', 'quotes.viewAll', 'opportunities.viewAll']);
    $role->fieldPermissions()->create(['resource' => $resource, 'field' => $field, 'visible' => true, 'editable' => false, 'required' => false]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $payload = withOppPayload(null, null, $block === 'opportunity' ? ['general_notes' => 'x'] : [], $block === 'quote' ? ['internal_notes' => 'x'] : []);

    $this->postJson('/api/quotes/with-opportunity', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors("{$block}.{$field}");

    expect(Quote::query()->count())->toBe(0);
})->with([
    'quote block' => ['quotes', 'internal_notes', 'quote'],
    'opportunity block' => ['opportunities', 'general_notes', 'opportunity'],
]);

it('AC-011: form-context with product_category_ids resolves those categories; without it the response is unchanged', function () {
    $category = withOppCategory();
    $attribute = Attribute::factory()->create(['code' => 'preview_code', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value]);
    Sanctum::actingAs(withOppActor(['quotes.create']));

    $with = $this->postJson('/api/quotes/form-context', ['product_category_ids' => [$category->id]])->assertOk();
    $without = $this->postJson('/api/quotes/form-context', [])->assertOk();

    expect(collect($with->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['preview_code'])
        ->and($without->json('data.applicable_attributes'))->toBe([]);

    $this->postJson('/api/quotes/form-context', ['product_category_ids' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('product_category_ids.0');
});
