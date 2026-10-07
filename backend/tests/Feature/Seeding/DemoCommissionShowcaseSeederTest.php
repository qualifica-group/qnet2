<?php

use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderContractDataBuilder;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoCatalog\DemoCommissionShowcaseCatalogue as Catalogue;
use Database\Seeders\DemoCommissionShowcaseSeeder;
use Database\Seeders\DemoProductCategorySeeder;
use Database\Seeders\ProductTypologySeeder;
use Database\Seeders\WorkOrderPaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedShowcaseDependencies(): void
{
    Registry::factory()->count(2)->create();
    Referent::factory()->count(2)->create();
    VatRate::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin'));

    foreach (DemoCategoryCatalogue::TREE as $branch) {
        BusinessFunction::query()->firstOrCreate(['name' => $branch['business_function']]);
    }

    test()->seed(ProductTypologySeeder::class);
    test()->seed(WorkOrderPaymentStatusSeeder::class);
    test()->seed(DemoProductCategorySeeder::class);
}

/**
 * @return array<string, mixed> the contract-data payload of the showcase commessa
 */
function showcaseContractData(string $caseTitle): array
{
    $workOrder = WorkOrder::query()->where('title', Catalogue::TITLE_PREFIX.$caseTitle)->firstOrFail();

    return app(WorkOrderContractDataBuilder::class)->build($workOrder, User::query()->orderBy('id')->firstOrFail());
}

it('creates one commessa per case, with the commission figures computed by the real services', function (): void {
    seedShowcaseDependencies();

    test()->seed(DemoCommissionShowcaseSeeder::class);

    expect(WorkOrder::query()->where('title', 'like', Catalogue::TITLE_PREFIX.'%')->count())->toBe(count(Catalogue::CASES));

    // 1. PAID: revenue = net, every commission subtracted.
    $paid = showcaseContractData('Consulenza - commissione Fornitore pagata');
    $line = $paid['lines'][0];
    expect($line['supplier_commission_direction'])->toBe('PAID')
        ->and($line['net_amount'])->toBe('1000.00')
        ->and($line['effective_revenue'])->toBe('1000.00')
        ->and($line['supplier_commission']['amount'])->toBe('100.00')
        ->and($line['commissions_amount'])->toBe('180.00')
        ->and($line['net_of_commissions'])->toBe('820.00');

    // 2. RECEIVED %: the base is the line margin (5000 - 200 imputed cost).
    $received = showcaseContractData('Ente - commissione Fornitore ricevuta %')['lines'][0];
    expect($received['supplier_commission_direction'])->toBe('RECEIVED')
        ->and($received['supplier_commission']['base_amount'])->toBe('4800.00')
        ->and($received['supplier_commission']['amount'])->toBe('960.00')
        ->and($received['effective_revenue'])->toBe('960.00')
        ->and($received['commissions_amount'])->toBe('1200.00');

    // 3. RECEIVED fixed amount.
    $fixed = showcaseContractData('Ente - commissione Fornitore ricevuta fissa')['lines'][0];
    expect($fixed['supplier_commission']['commission_type'])->toBe('FIXED_AMOUNT')
        ->and($fixed['supplier_commission']['amount'])->toBe('300.00')
        ->and($fixed['effective_revenue'])->toBe('300.00');

    // 4. RECEIVED without supplier: no Supplier commission, warning.
    $missing = showcaseContractData('Ente - fornitore mancante')['lines'][0];
    expect($missing['supplier_commission'])->toBeNull()
        ->and($missing['effective_revenue'])->toBe('0.00')
        ->and($missing['warnings'])->toContain('missing_supplier_commission');

    // 5. Typology without Supplier calculation: the rule exists but nothing is created.
    $noCalc = showcaseContractData('Tipologia senza calcolo Fornitore')['lines'][0];
    expect($noCalc['supplier_commission_direction'])->toBeNull()
        ->and($noCalc['supplier_commission'])->toBeNull()
        ->and($noCalc['effective_revenue'])->toBe('1500.00')
        ->and($noCalc['commissions_amount'])->toBe('50.00');

    // 6. Mixed: three typologies, three payment states.
    $mixed = showcaseContractData('Mista - tre tipologie');
    expect($mixed['lines'])->toHaveCount(3)
        ->and(array_column($mixed['lines'], 'supplier_commission_direction'))->toBe(['PAID', 'RECEIVED', null])
        ->and(array_column($mixed['lines'], 'effective_revenue'))->toBe(['2000.00', '960.00', '2000.00'])
        ->and(collect($mixed['lines'])->pluck('payment.status.name')->all())->each->not->toBeNull()
        ->and($mixed['lines'][2]['payment']['has_unpaid'])->toBeTrue();
});

it('is idempotent: a second run duplicates nothing', function (): void {
    seedShowcaseDependencies();

    test()->seed(DemoCommissionShowcaseSeeder::class);
    $counts = fn (): array => [
        WorkOrder::count(), Quote::count(), Product::count(), CommissionConfiguration::count(), Registry::count(),
    ];
    $first = $counts();

    test()->seed(DemoCommissionShowcaseSeeder::class);

    expect($counts())->toBe($first);
});

it('does not change the rules resolved for other products', function (): void {
    seedShowcaseDependencies();

    test()->seed(DemoCommissionShowcaseSeeder::class);

    expect(CommissionConfiguration::query()->whereNull('product_id')->count())->toBe(0);
});
