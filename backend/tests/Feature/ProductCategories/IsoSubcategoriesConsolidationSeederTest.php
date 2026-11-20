<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use App\Models\User;
use App\Models\UserCategoryTabPreference;
use Database\Seeders\IsoSubcategoriesConsolidationSeeder as Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * One-shot (user request 2026-11-20): the subcategories of the current "ISO"
 * root give way to "ISO - Ente" (institution products) and "ISO - Consulenza"
 * (all the others); references follow, the "[OLD] ISO" branch is untouched.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->root = ProductCategory::factory()->create(['name' => 'ISO']);
    $this->institution = ProductTypology::query()->where('code', Seeder::INSTITUTION_TYPOLOGY)->firstOrFail();
    $this->consultancy = ProductTypology::factory()->create(['code' => 'consultancy', 'name' => 'Consulenza']);
    $this->subcategory = fn (string $name, ?ProductCategory $parent = null): ProductCategory => ProductCategory::factory()->create(['name' => $name, 'parent_id' => ($parent ?? $this->root)->id]);
    $this->product = fn (ProductCategory $category, ProductTypology $typology): Product => Product::factory()->create(['category_id' => $category->id, 'product_typology_id' => $typology->id]);
    $this->destination = fn (string $name): ProductCategory => ProductCategory::query()->where('parent_id', $this->root->id)->where('name', $name)->firstOrFail();
});

it('moves the products by typology and leaves only the two subcategories under ISO', function () {
    $iso9001 = ($this->subcategory)('ISO - 9001');
    $nested = ($this->subcategory)('ISO - 9001 nested', $iso9001);
    $ente = ($this->subcategory)('ISO - Ente 9001');
    ($this->subcategory)('ISO - Generico');
    $consulting = ($this->product)($iso9001, $this->consultancy);
    $deep = ($this->product)($nested, $this->consultancy);
    $body = ($this->product)($ente, $this->institution);
    $legacyRoot = ProductCategory::factory()->create(['name' => '[OLD] ISO']);
    $legacySub = ProductCategory::factory()->create(['name' => '[OLD] 9001', 'parent_id' => $legacyRoot->id]);
    $legacyProduct = ($this->product)($legacySub, $this->consultancy);

    $this->seed(Seeder::class);

    $consultancyCategory = ($this->destination)(Seeder::CONSULTANCY_CATEGORY);
    $institutionCategory = ($this->destination)(Seeder::INSTITUTION_CATEGORY);
    expect(ProductCategory::query()->where('parent_id', $this->root->id)->pluck('name')->sort()->values()->all())
        ->toBe([Seeder::CONSULTANCY_CATEGORY, Seeder::INSTITUTION_CATEGORY])
        ->and(ProductCategory::query()->whereKey([$iso9001->id, $nested->id, $ente->id])->exists())->toBeFalse()
        ->and($consulting->fresh()->category_id)->toBe($consultancyCategory->id)
        ->and($deep->fresh()->category_id)->toBe($consultancyCategory->id)
        ->and($body->fresh()->category_id)->toBe($institutionCategory->id)
        ->and($legacyProduct->fresh()->category_id)->toBe($legacySub->id)
        ->and($institutionCategory->is_active)->toBeTrue();
});

it('moves the opportunity lines, dropping the ones that become duplicates', function () {
    $iso9001 = ($this->subcategory)('ISO - 9001');
    $iso14001 = ($this->subcategory)('ISO - 14001');
    $ente = ($this->subcategory)('ISO - Ente 9001');
    ($this->product)($iso9001, $this->consultancy);
    ($this->product)($ente, $this->institution);
    $function = BusinessFunction::factory()->create();
    $opportunity = Opportunity::factory()->create();
    foreach ([$iso9001, $iso14001, $ente] as $category) {
        OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id, 'business_function_id' => $function->id, 'product_category_id' => $category->id]);
    }

    $this->seed(Seeder::class);

    expect(OpportunityProductLine::query()->where('opportunity_id', $opportunity->id)->pluck('product_category_id')->sort()->values()->all())
        ->toBe(collect([($this->destination)(Seeder::CONSULTANCY_CATEGORY)->id, ($this->destination)(Seeder::INSTITUTION_CATEGORY)->id])->sort()->values()->all());
});

it('turns a subcategory commission rule into product rules, keeping an existing product rule', function () {
    $sa8000 = ($this->subcategory)('ISO - SA8000');
    $first = ($this->product)($sa8000, $this->consultancy);
    $second = ($this->product)($sa8000, $this->consultancy);
    $reporter = Referent::factory()->create();
    $attributes = ['recipient_role' => CommissionRecipientRole::Reporter, 'recipient_type' => $reporter->getMorphClass(), 'recipient_id' => $reporter->id];
    $categoryRule = CommissionConfiguration::factory()->create([...$attributes, 'application_scope' => CommissionApplicationScope::ProductCategory, 'product_category_id' => $sa8000->id, 'product_id' => null, 'value' => 10]);
    $productRule = CommissionConfiguration::factory()->create([...$attributes, 'product_id' => $second->id, 'value' => 7]);

    $this->seed(Seeder::class);

    $rules = CommissionConfiguration::query()->where('recipient_id', $reporter->id)->get();
    expect(CommissionConfiguration::query()->whereKey($categoryRule->id)->exists())->toBeFalse()
        ->and($rules)->toHaveCount(2)
        ->and((float) $rules->firstWhere('product_id', $first->id)->value)->toBe(10.0)
        ->and($rules->firstWhere('product_id', $first->id)->application_scope)->toBe(CommissionApplicationScope::Product)
        ->and((float) $productRule->fresh()->value)->toBe(7.0);
});

it('keeps an already applied subcategory rule as suspended history on the destination', function () {
    $sa8000 = ($this->subcategory)('ISO - SA8000');
    ($this->product)($sa8000, $this->consultancy);
    $rule = CommissionConfiguration::factory()->create(['application_scope' => CommissionApplicationScope::ProductCategory, 'product_category_id' => $sa8000->id, 'product_id' => null]);
    QuoteLineCommission::factory()->create(['commission_configuration_id' => $rule->id]);

    $this->seed(Seeder::class);

    expect($rule->fresh()->status)->toBe(CommissionConfigurationStatus::Suspended)
        ->and($rule->fresh()->product_category_id)->toBe(($this->destination)(Seeder::CONSULTANCY_CATEGORY)->id);
});

it('points the favourite tabs at the destinations and changes nothing when re-run', function () {
    $iso9001 = ($this->subcategory)('ISO - 9001');
    $ente = ($this->subcategory)('ISO - Ente 9001');
    $existing = ($this->subcategory)(Seeder::INSTITUTION_CATEGORY);
    ($this->product)($iso9001, $this->consultancy);
    ($this->product)($ente, $this->institution);
    $other = ProductCategory::factory()->create();
    $preference = UserCategoryTabPreference::query()->create([
        'user_id' => User::factory()->create()->id, 'module' => 'request_management',
        'favorite_category_ids' => [$iso9001->id, $ente->id, $other->id],
    ]);

    $this->seed(Seeder::class);
    $consultancyId = ($this->destination)(Seeder::CONSULTANCY_CATEGORY)->id;
    $snapshot = ProductCategory::query()->orderBy('id')->get(['id', 'parent_id', 'name'])->toArray();
    $this->seed(Seeder::class);

    expect($preference->fresh()->favorite_category_ids)->toBe([$consultancyId, $existing->id, $other->id])
        ->and(ProductCategory::query()->orderBy('id')->get(['id', 'parent_id', 'name'])->toArray())->toBe($snapshot);
});
