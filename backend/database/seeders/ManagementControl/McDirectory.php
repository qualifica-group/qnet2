<?php

namespace Database\Seeders\ManagementControl;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\DataObjects\Products\CreateProductData;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\FinancialAccountType;
use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\AgentFiscalProfile;
use App\Models\CommissionConfiguration;
use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\CostType;
use App\Models\FinancialAccount;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Services\CommissionConfigurationService;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductService;
use Faker\Generator;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The master data of the dataset, created once and found again on a re-run (by name):
 * customers and suppliers with an Italian company card, the agents and reporters with
 * their supplier registry and fiscal profile, the commission rules on the sold
 * categories and the cost types; then every lookup the steps need, into McContext.
 */
final class McDirectory
{
    private const array COMPANY_WORDS = [
        'Officine', 'Costruzioni', 'Logistica', 'Alimentari', 'Tecnologie', 'Servizi', 'Meccanica', 'Tessile',
        'Arredamenti', 'Trasporti', 'Impianti', 'Edilizia', 'Laboratori', 'Industrie', 'Ceramiche', 'Automazioni',
    ];

    private const array LEGAL_FORMS = ['S.r.l.', 'S.p.A.', 'S.n.c.', 'S.a.s.', 'S.r.l.s.'];

    private const string WITHHOLDING_RATE = '23.00';

    private const string WITHHOLDING_BASE_PERCENT = '50.00';

    private const string AGENT_VAT_RATE = '22.00';

    public function __construct(
        private readonly CommissionConfigurationService $rules,
        private readonly CategoryHierarchy $hierarchy,
        private readonly ProductService $products,
    ) {}

    /**
     * @param  list<array{company_id: int, company_site_id: int|null, bank_id: int}>  $companies
     * @param  list<int>  $deliveryUserIds  the people who lead and staff the commesse
     */
    public function build(Generator $faker, User $actor, array $companies, array $deliveryUserIds): McContext
    {
        // Step 1: reference data the dataset adds (cost types, rules)
        $costTypes = $this->costTypes();
        $categories = $this->categories();
        $this->commissionRules($categories);
        $this->soldProducts($categories);

        // Step 2: registries and agents
        $suppliers = $this->suppliers($faker);
        $customers = $this->customers($faker);

        // Step 3: the lookups, all by name
        return new McContext(
            actor: $actor,
            companies: $companies,
            customers: $customers,
            suppliers: $suppliers,
            agents: $this->referents(McCatalogue::AGENTS, $faker),
            reporters: $this->referents(McCatalogue::REPORTERS, $faker),
            products: Product::query()->get()->keyBy('name')->all(),
            categories: $categories,
            costTypes: $costTypes,
            customerPaymentMethodIds: PaymentMethod::query()->whereIn('name', McCatalogue::CUSTOMER_PAYMENT_METHODS)->pluck('id')->all(),
            supplierPaymentMethodId: (int) PaymentMethod::query()->where('name', McCatalogue::SUPPLIER_PAYMENT_METHOD)->value('id'),
            vatRateId: (int) VatRate::query()->where('rate', self::AGENT_VAT_RATE)->value('id'),
            supervisors: User::query()->whereIn('id', $deliveryUserIds)->with('employment')->orderBy('id')->get(),
            latePayers: collect($customers)->mapWithKeys(fn (Registry $customer): array => [$customer->id => $faker->boolean(McCatalogue::LATE_PAYER_PERCENT)])->all(),
        );
    }

    /**
     * @return array<string, int> cost type id by name
     */
    private function costTypes(): array
    {
        $sortOrder = CostType::query()->max('sort_order') ?? 0;

        foreach (McCatalogue::COST_TYPES as $name => [$nature, $color]) {
            $type = CostType::query()->firstOrNew(['name' => $name]);
            $type->fill(['nature' => $nature, 'color' => $color, 'is_active' => true, 'sort_order' => $type->sort_order ?? ++$sortOrder])->save();
        }

        return CostType::query()->pluck('id', 'name')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * The sold categories with their effective business function.
     *
     * @return array<string, array{category_id: int, business_function_id: int}>
     */
    private function categories(): array
    {
        $functions = $this->hierarchy->effectiveBusinessFunctionSummaries();
        $categories = [];

        foreach (array_keys(McCatalogue::COMMISSION_RULES) as $name) {
            $category = ProductCategory::query()->where('name', $name)->first()
                ?? throw new RuntimeException("Demo category {$name} is missing: run DemoProductCategorySeeder first.");
            $categories[$name] = ['category_id' => $category->id, 'business_function_id' => (int) $functions[$category->id]['id']];
        }

        return $categories;
    }

    /**
     * @param  array<string, array{category_id: int, business_function_id: int}>  $categories
     */
    private function commissionRules(array $categories): void
    {
        foreach (McCatalogue::COMMISSION_RULES as $categoryName => $percents) {
            foreach ([CommissionRecipientRole::Commercial, CommissionRecipientRole::Reporter] as $index => $role) {
                $name = sprintf('%s%s - %s', McCatalogue::TITLE_PREFIX, $role->value, $categoryName);
                if (CommissionConfiguration::query()->where('name', $name)->exists()) {
                    continue;
                }

                $this->rules->create(new CreateCommissionConfigurationData(
                    name: $name,
                    recipientRole: $role,
                    applicationScope: CommissionApplicationScope::ProductCategory,
                    productCategoryId: $categories[$categoryName]['category_id'],
                    productId: null,
                    recipientId: null,
                    recipientType: null,
                    commissionType: CommissionType::Percentage,
                    value: $percents[$index],
                    priority: 1,
                    validFrom: McCatalogue::PERIOD_START,
                    validUntil: null,
                    status: CommissionConfigurationStatus::Active,
                    internalNote: null,
                ));
            }
        }
    }

    /**
     * The products the dataset sells, created once through the product service.
     *
     * @param  array<string, array{category_id: int, business_function_id: int}>  $categories
     */
    private function soldProducts(array $categories): void
    {
        $typologyId = ProductTypology::query()->where('code', McCatalogue::SOLD_TYPOLOGY_CODE)->value('id');
        $vatRateId = VatRate::query()->where('rate', self::AGENT_VAT_RATE)->value('id');
        $catalogue = [
            ...array_map(fn (array $item): array => [$item[0], $item[1]], McCatalogue::TRAINING_PRODUCTS),
            ...array_map(fn (array $item): array => [$item[0], $item[2]], McCatalogue::CONSULTING_PRODUCTS),
        ];

        foreach ($catalogue as $name => [$categoryName, $price]) {
            if (Product::query()->where('name', $name)->exists()) {
                continue;
            }

            $this->products->create(new CreateProductData(
                name: $name,
                description: null,
                cost: null,
                price: $price,
                categoryId: $categories[$categoryName]['category_id'],
                productType: ProductType::Service,
                vatRateId: $vatRateId,
                productTypologyId: $typologyId,
                usages: [ProductUsage::Sale],
            ));
        }
    }

    /**
     * @return array<string, Registry> by name
     */
    private function suppliers(Generator $faker): array
    {
        $suppliers = [];
        foreach (McCatalogue::SUPPLIERS as $name => [$vatNumber]) {
            $suppliers[$name] = $this->registry($faker, $name, $vatNumber, supplier: true);
        }

        return $suppliers;
    }

    /**
     * @return list<Registry>
     */
    private function customers(Generator $faker): array
    {
        $customers = [];
        $names = [];

        while (count($customers) < McCatalogue::CUSTOMERS) {
            $name = sprintf('%s %s %s', $faker->randomElement(self::COMPANY_WORDS), $faker->lastName(), $faker->randomElement(self::LEGAL_FORMS));
            if (isset($names[$name])) {
                continue;
            }

            $names[$name] = true;
            $customers[] = $this->registry($faker, $name, $faker->numerify('0##########'), supplier: false);
        }

        return $customers;
    }

    private function registry(Generator $faker, string $name, string $vatNumber, bool $supplier): Registry
    {
        $existing = Registry::query()->where('name', $name)->first();
        if ($existing !== null) {
            return $existing;
        }

        $factory = $supplier ? Registry::factory()->supplier() : Registry::factory();

        return $factory->withPersonalData(fn ($card) => $card->company()
            ->state(['company_name' => $name, 'first_name' => $faker->firstName(), 'last_name' => $faker->lastName(), 'vat_number' => $vatNumber])
            ->has(Contact::factory()->phone()->primary(), 'contacts'))
            ->create(['name' => $name]);
    }

    /**
     * Agents and reporters: an Italian referent, the supplier registry its statements are paid to and a
     * fiscal profile valid on the whole period (withholding on half the base, VAT 22%).
     *
     * @param  list<string>  $names
     * @return list<Referent>
     */
    private function referents(array $names, Generator $faker): array
    {
        $typeId = ReferentType::query()->where('name', 'Commercial')->value('id');

        return array_map(function (string $name) use ($faker, $typeId): Referent {
            $referent = Referent::query()->firstOrCreate(['name' => $name], ['referent_type_id' => $typeId, 'contact_scope' => 'external']);

            if ($referent->supplier_registry_id === null) {
                $referent->update(['supplier_registry_id' => $this->registry($faker, $name, $faker->numerify('0##########'), supplier: true)->id]);
            }

            AgentFiscalProfile::query()->firstOrCreate(['referent_id' => $referent->id, 'valid_from' => McCatalogue::PERIOD_START], [
                'withholding_enabled' => true, 'withholding_rate' => self::WITHHOLDING_RATE, 'withholding_base_percent' => self::WITHHOLDING_BASE_PERCENT,
                'enasarco_enabled' => false, 'vat_rate_id' => VatRate::query()->where('rate', self::AGENT_VAT_RATE)->value('id'),
            ]);

            return $referent;
        }, $names);
    }

    /**
     * The companies that sell: the ones with a bank account, each with its first site.
     *
     * @return list<array{company_id: int, company_site_id: int|null, bank_id: int}>
     */
    public function sellingCompanies(): array
    {
        /** @var Collection<int, FinancialAccount> $banks */
        $banks = FinancialAccount::query()->where('type', FinancialAccountType::BankAccount->value)->whereNotNull('company_id')->orderBy('id')->get();

        return $banks->unique('company_id')->values()->map(fn (FinancialAccount $bank): array => [
            'company_id' => (int) $bank->company_id,
            'company_site_id' => CompanySite::query()->where('company_id', $bank->company_id)->orderBy('id')->value('id'),
            'bank_id' => $bank->id,
        ])->all();
    }
}
