<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\SupplierCommissionDirection;
use App\Migrations\MigrationOrder;
use App\Migrations\Sources\CommissionConfigurationsSource;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `commission-configurations` source (spec 0203, D-5/D-6): AC-002..AC-005,
 * AC-007 (registration/order) and AC-008 (end to end on a new offer line).
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyCommissionRulesImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/commission-configurations*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'commission-configurations']);
    runMigrationJobFor($run);

    return $run->fresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyCommissionRule(array $overrides = []): array
{
    return [
        'id' => 13, 'role' => 'supplier', 'recipient_kind' => 'company', 'recipient_id' => 15269,
        'category_id' => 70, 'product_id' => null, 'percentage' => 10, 'amount' => null,
        'note' => 'Regola storica', 'created_at' => '2023-08-04 11:49:21',
        ...$overrides,
    ];
}

/** @return array{registry: Registry, referent: Referent, category: ProductCategory} */
function migratedCommissionRuleTargets(): array
{
    return [
        'registry' => Registry::factory()->create(['old_id' => 15269, 'name' => 'Ente Alfa']),
        'referent' => Referent::factory()->create(['old_id' => 888, 'name' => 'Mario Rossi']),
        'category' => ProductCategory::factory()->create(['old_id' => 70, 'name' => 'Formazione', 'is_selectable' => true]),
    ];
}

beforeEach(fn () => seedMigrationsConfig());

it('AC-002: a supplier rule on a category becomes an active percentage rule for the migrated registry', function () {
    ['registry' => $registry, 'category' => $category] = migratedCommissionRuleTargets();

    $run = runLegacyCommissionRulesImport([legacyCommissionRule()]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)->and($run->report)->toBeNull();

    $rule = CommissionConfiguration::sole();
    expect($rule->old_id)->toBe(13)
        ->and($rule->recipient_role)->toBe(CommissionRecipientRole::Supplier)
        ->and($rule->recipient_type)->toBe('registry')
        ->and($rule->recipient_id)->toBe($registry->id)
        ->and($rule->application_scope)->toBe(CommissionApplicationScope::ProductCategory)
        ->and($rule->product_category_id)->toBe($category->id)
        ->and($rule->commission_type)->toBe(CommissionType::Percentage)
        ->and($rule->value)->toBe('10.0000')
        ->and($rule->status)->toBe(CommissionConfigurationStatus::Active)
        ->and($rule->valid_from->toDateString())->toBe('2023-08-04')
        ->and($rule->priority)->toBe(0)
        ->and($rule->internal_note)->toBe('Regola storica')
        ->and($rule->name)->toBe('Legacy - '.CommissionRecipientRole::Supplier->label().' - Ente Alfa - Formazione')
        ->and(DB::table('activity_log')->where('subject_type', 'commission_configuration')->count())->toBe(0);
});

it('AC-003: a commercial rule on a contact maps to the referent, a rule on a service to the PRODUCT scope', function () {
    ['referent' => $referent] = migratedCommissionRuleTargets();
    $product = Product::factory()->create(['old_source' => 'services', 'old_id' => 321]);

    $run = runLegacyCommissionRulesImport([
        legacyCommissionRule(['id' => 1, 'role' => 'commercial', 'recipient_kind' => 'contact', 'recipient_id' => 888]),
        legacyCommissionRule(['id' => 2, 'role' => 'commercial', 'recipient_kind' => 'contact', 'recipient_id' => 888, 'category_id' => null, 'product_id' => 321, 'percentage' => null, 'amount' => 25.5]),
    ]);

    expect($run->created_rows)->toBe(2)->and($run->failed_rows)->toBe(0);

    $onCategory = CommissionConfiguration::where('old_id', 1)->sole();
    expect($onCategory->recipient_role)->toBe(CommissionRecipientRole::Commercial)
        ->and($onCategory->recipient_type)->toBe('referent')
        ->and($onCategory->recipient_id)->toBe($referent->id);

    $onProduct = CommissionConfiguration::where('old_id', 2)->sole();
    expect($onProduct->application_scope)->toBe(CommissionApplicationScope::Product)
        ->and($onProduct->product_id)->toBe($product->id)
        ->and($onProduct->product_category_id)->toBeNull()
        ->and($onProduct->commission_type)->toBe(CommissionType::FixedAmount)
        ->and($onProduct->value)->toBe('25.5000');
});

it('AC-003: a supervisor rule on a user maps to the migrated user', function () {
    migratedCommissionRuleTargets();
    $user = User::factory()->create(['old_id' => 44]);

    $run = runLegacyCommissionRulesImport([legacyCommissionRule(['role' => 'supervisor', 'recipient_kind' => 'user', 'recipient_id' => 44])]);

    expect($run->created_rows)->toBe(1);
    expect(CommissionConfiguration::sole()->recipient_id)->toBe($user->id);
});

it('AC-004: unimportable rules fail with a readable message and create nothing', function (array $override, string $message) {
    migratedCommissionRuleTargets();
    ProductCategory::factory()->create(['old_id' => 71, 'name' => 'Contenitore', 'is_selectable' => false]);

    $run = runLegacyCommissionRulesImport([legacyCommissionRule($override)]);

    expect($run->failed_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0)
        ->and(CommissionConfiguration::count())->toBe(0)
        ->and($run->report[0]['level'])->toBe('error')
        ->and($run->report[0]['message'])->toContain($message);
})->with([
    'no role' => [['role' => null, 'recipient_kind' => 'user', 'recipient_id' => 1], 'no commission role'],
    'no recipient' => [['recipient_id' => null], 'no recipient'],
    'recipient not migrated' => [['recipient_id' => 999999], 'Recipient not migrated'],
    'kind not allowed for the role' => [['role' => 'supplier', 'recipient_kind' => 'contact', 'recipient_id' => 888], 'not allowed for the SUPPLIER role'],
    'category not migrated' => [['category_id' => 12345], 'Category not migrated'],
    'category not selectable' => [['category_id' => 71], 'not selectable'],
    'product not migrated' => [['category_id' => null, 'product_id' => 777], 'Product not migrated'],
    'no destination' => [['category_id' => null, 'product_id' => null], 'neither a category nor a product'],
    'no amount' => [['percentage' => null, 'amount' => null], 'neither a percentage nor an amount'],
]);

it('AC-005: a re-run skips the already imported rules without duplicating', function () {
    migratedCommissionRuleTargets();

    runLegacyCommissionRulesImport([legacyCommissionRule()]);
    $second = runLegacyCommissionRulesImport([legacyCommissionRule()]);

    expect($second->created_rows)->toBe(0)->and($second->skipped_rows)->toBe(1)->and(CommissionConfiguration::count())->toBe(1);
});

it('AC-007: the source is registered, ordered right after registries, and previews its columns', function () {
    $phases = MigrationOrder::phases();
    $registries = array_search(['registries'], $phases, true);

    expect(config('migrations.definitions'))->toHaveKey('commission-configurations')
        ->and($phases[$registries + 1])->toBe(['product-suppliers', 'commission-configurations']);

    $columns = collect(app(CommissionConfigurationsSource::class)->columns())->pluck('id')->all();
    expect($columns)->toBe(['id', 'role', 'recipient_kind', 'recipient_id', 'category_id', 'product_id', 'percentage', 'amount', 'note', 'created_at']);
});

it('AC-008: after the import a new offer line of a supplier product in the covered category creates the supplier commission', function () {
    ['registry' => $registry, 'category' => $category] = migratedCommissionRuleTargets();
    $category->update(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'supplier_id' => $registry->id,
        'product_typology_id' => ProductTypology::factory()->supplierCommission(SupplierCommissionDirection::Paid)->create()->id,
    ]);
    runLegacyCommissionRulesImport([legacyCommissionRule(['percentage' => 12.5])]);

    QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    $opportunity = Opportunity::factory()->create(['commercial_id' => Referent::factory()->create()->id]);
    foreach (['view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['quotes.view', 'quotes.create', 'quotes.update']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/quotes', [
        'title' => 'Dopo migrazione',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
    ])->assertCreated();

    $commission = collect($response->json('data.offer_lines.0.commissions'))->firstWhere('recipient_role', 'SUPPLIER');
    expect($commission)->not->toBeNull()
        ->and($commission['commission_type'])->toBe('PERCENTAGE')
        ->and((float) $commission['value'])->toBe(12.5);
});
