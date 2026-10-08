<?php

namespace Database\Seeders;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Products\CreateProductData;
use App\DataObjects\Products\UpdateProductData;
use App\DataObjects\ProductTypologies\CreateProductTypologyData;
use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\DataObjects\WorkOrders\UpdateWorkOrderLinePaymentData;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Enums\WorkOrderType;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Models\WorkOrderPaymentStatus;
use App\Services\CommissionConfigurationService;
use App\Services\OpportunityService;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductService;
use App\Services\ProductTypologyService;
use App\Services\QuoteService;
use App\Services\RoleAssignmentGuard;
use App\Services\WorkOrders\WorkOrderLinePaymentWriter;
use App\Services\WorkOrderService;
use Database\Seeders\Concerns\ResolvesDemoCategories;
use Database\Seeders\Concerns\SeedsWithoutMail;
use Database\Seeders\DemoCatalog\DemoCommissionShowcaseCatalogue as Catalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Showcase of the commission rules (specs 0089, 0145, 0201, 0202): one NEW
 * commessa per case — Supplier commission paid, received (percentage and
 * fixed), supplier missing, typology without Supplier calculation, and a mixed
 * one — each "Demo commissioni - ...", so the "Dati contrattuali" tab can be
 * read case by case. Everything goes through the real services (typology,
 * product, rules, opportunity, quote, commessa, payment writer), so the line
 * snapshot, the commissions and `margin_net` are computed, never inserted.
 *
 * The rules are PRODUCT-scoped on the showcase products only, so no other demo
 * offer changes. Idempotent: typology by code, supplier by name, products and
 * rules by name, commesse by title — an existing commessa is left untouched.
 * Depends on the demo products/categories, registries, referents, users and
 * the payment statuses; must run after DemoQuoteSeeder (the wipe of Quote at
 * the top of DemoDataSeeder removes these cases, this seeder recreates them).
 */
class DemoCommissionShowcaseSeeder extends Seeder
{
    use ResolvesDemoCategories;
    use SeedsWithoutMail;

    public function __construct(
        private readonly ProductTypologyService $typologies,
        private readonly ProductService $products,
        private readonly CommissionConfigurationService $rules,
        private readonly OpportunityService $opportunities,
        private readonly QuoteService $quotes,
        private readonly WorkOrderService $workOrders,
        private readonly WorkOrderLinePaymentWriter $payments,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        $this->withoutMail($this->seedShowcase(...));
    }

    private function seedShowcase(): void
    {
        $actor = $this->resolveActor();
        $referents = Referent::query()->orderBy('id')->limit(2)->get();

        if ($actor === null || $referents->count() < 2) {
            // Nothing valid to seed without an actor and two referents.
            return;
        }

        // Step 1: typology, supplier, products and their rules
        $this->ensureTypology();
        $supplier = $this->ensureSupplier();
        $category = $this->demoCategoryOrFail(Catalogue::CATEGORY);
        $products = $this->ensureProducts($category, $supplier);
        $this->ensureRules($products);

        // Step 2: one opportunity + quote + commessa per case
        foreach (array_keys(Catalogue::CASES) as $index => $caseName) {
            $lines = Catalogue::CASES[$caseName];
            $title = Catalogue::TITLE_PREFIX.$caseName;

            if (WorkOrder::query()->where('title', $title)->exists()) {
                continue;
            }

            DB::transaction(fn () => $this->seedCase($title, $lines, $products, $category, $this->ensureCustomer($index + 1), $referents->all(), $actor));
        }
    }

    private function ensureTypology(): void
    {
        $existing = ProductTypology::query()->where('code', Catalogue::TRAINING_TYPOLOGY_CODE)->first();

        if ($existing !== null) {
            // Re-run on a DB seeded before spec 0204: bring the badge color in line.
            $existing->update(['color' => Catalogue::TRAINING_TYPOLOGY_COLOR]);

            return;
        }

        // Supplier commission calculation switched off (spec 0202, D-6).
        $this->typologies->create(new CreateProductTypologyData(
            name: Catalogue::TRAINING_TYPOLOGY_NAME,
            code: Catalogue::TRAINING_TYPOLOGY_CODE,
            description: null,
            color: Catalogue::TRAINING_TYPOLOGY_COLOR,
        ));
    }

    /**
     * One customer per case: a registry admits a single open opportunity.
     */
    private function ensureCustomer(int $number): Registry
    {
        $name = sprintf('%s %d', Catalogue::CUSTOMER_NAME, $number);

        return Registry::query()->where('name', $name)->first()
            ?? Registry::factory()
                ->withPersonalData(fn ($card) => $card->company()->state(['company_name' => $name]))
                ->create(['name' => $name]);
    }

    private function ensureSupplier(): Registry
    {
        $existing = Registry::query()->where('name', Catalogue::SUPPLIER_NAME)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Registry::factory()->supplier()
            ->withPersonalData(fn ($card) => $card->company()->state(['company_name' => Catalogue::SUPPLIER_NAME]))
            ->create(['name' => Catalogue::SUPPLIER_NAME]);
    }

    /**
     * @return array<string, Product> keyed by product name, the cost product included
     */
    private function ensureProducts(ProductCategory $category, Registry $supplier): array
    {
        $vatRateId = VatRate::query()->orderBy('id')->value('id');
        $products = [];

        foreach (Catalogue::PRODUCTS as $name => [$typologyCode, $price, $hasSupplier]) {
            $products[$name] = $this->ensureProduct($category, $name, new CreateProductData(
                name: $name,
                description: null,
                cost: null,
                price: $price,
                categoryId: $category->id,
                productType: ProductType::Service,
                vatRateId: $vatRateId,
                supplierId: $hasSupplier ? $supplier->id : null,
                productTypologyId: ProductTypology::query()->where('code', $typologyCode)->value('id'),
                usages: [ProductUsage::Sale],
            ));
        }

        $products[Catalogue::COST_PRODUCT] = $this->ensureProduct($category, Catalogue::COST_PRODUCT, new CreateProductData(
            name: Catalogue::COST_PRODUCT,
            description: null,
            cost: 1.0,
            price: null,
            categoryId: $category->id,
            productType: ProductType::Service,
            vatRateId: $vatRateId,
            usages: [ProductUsage::Cost],
        ));

        return $products;
    }

    private function ensureProduct(ProductCategory $category, string $name, CreateProductData $data): Product
    {
        $product = Product::query()->where('name', $name)->where('category_id', $category->id)->first();

        if ($product === null) {
            return $this->products->create($data);
        }

        // A DemoRegistrySeeder re-run deletes the supplier (nullOnDelete): relink it.
        if ($product->supplier_id !== $data->supplierId) {
            $product = $this->products->update($product, new UpdateProductData(
                supplierId: $data->supplierId,
                supplierIdSubmitted: true,
            ));
        }

        return $product;
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function ensureRules(array $products): void
    {
        foreach (Catalogue::RULES as $productName => $rules) {
            foreach ($rules as [$role, $type, $value]) {
                $name = sprintf('Demo showcase - %s - %s', $role->value, $productName);

                if (CommissionConfiguration::query()->where('name', $name)->exists()) {
                    continue;
                }

                $this->rules->create(new CreateCommissionConfigurationData(
                    name: $name,
                    recipientRole: $role,
                    applicationScope: CommissionApplicationScope::Product,
                    productCategoryId: null,
                    productId: $products[$productName]->id,
                    recipientId: null,
                    recipientType: null,
                    commissionType: $type,
                    value: $value,
                    priority: 1,
                    validFrom: now()->subYear()->toDateString(),
                    validUntil: null,
                    status: CommissionConfigurationStatus::Active,
                    internalNote: null,
                ));
            }
        }
    }

    /**
     * @param  list<array{0: string, 1: float, 2: float, 3: float|null, 4: int|null, 5: bool}>  $lines
     * @param  array<string, Product>  $products
     * @param  array<int, Referent>  $referents  commercial, reporter
     */
    private function seedCase(string $title, array $lines, array $products, ProductCategory $category, Registry $customer, array $referents, User $actor): void
    {
        $businessFunction = $this->hierarchy->effectiveBusinessFunctionSummaries()[$category->id]
            ?? throw new RuntimeException('Showcase category has no effective business function.');

        // Step 1: the opportunity the offer hangs from
        $opportunity = $this->opportunities->create(new CreateOpportunityData(
            registryId: $customer->id,
            referentId: null,
            commercialId: $referents[0]->id,
            reporterId: $referents[1]->id,
            supervisorId: $actor->id,
            sourceId: null,
            leadId: null,
            managerSlots: null,
            productLines: [['business_function_id' => $businessFunction['id'], 'product_category_id' => $category->id]],
            startDate: null,
            estimatedValue: null,
            expectedCloseDate: null,
            successProbability: null,
            productsOfInterest: array_values(array_unique(array_map(static fn (array $line): int => $products[$line[0]]->id, $lines))),
            name: $title,
        ));

        // Step 2: the offer, so snapshot, commissions and margin come from QuoteService
        $quote = $this->quotes->create($this->quoteData($title, $opportunity->id, $lines, $products, $referents, $actor), $actor);

        // Step 3: the commessa with every revenue line, then the payment states
        $workOrder = $this->workOrders->create(new CreateWorkOrderData(
            code: null,
            quoteId: $quote->id,
            title: $title,
            type: WorkOrderType::Processing,
            startDate: now()->toDateString(),
            callbackDate: null,
            description: null,
            internalNotes: null,
            isForceClosed: false,
            forceCloseReason: null,
            quoteLineIds: $quote->offerLines()->orderBy('sort_order')->orderBy('id')->pluck('id')->all(),
            supervisorIds: [$actor->id],
            participantSlots: [],
        ));

        $this->seedPayments($workOrder, $quote, $lines, $actor);
    }

    /**
     * @param  list<array{0: string, 1: float, 2: float, 3: float|null, 4: int|null, 5: bool}>  $lines
     * @param  array<string, Product>  $products
     * @param  array<int, Referent>  $referents
     */
    private function quoteData(string $title, int $opportunityId, array $lines, array $products, array $referents, User $actor): CreateQuoteData
    {
        $offerLines = [];
        $costLines = [];

        foreach ($lines as $index => [$productName, $quantity, $unitPrice, $cost]) {
            $product = $products[$productName];
            $offerLines[] = new QuoteLineData($product->id, $quantity, $unitPrice, $product->vat_rate_id, null);

            if ($cost !== null) {
                $costProduct = $products[Catalogue::COST_PRODUCT];
                $costLines[] = new QuoteLineData($costProduct->id, 1.0, $cost, $costProduct->vat_rate_id, null, offerLineIndex: $index);
            }
        }

        return new CreateQuoteData(
            code: null,
            title: $title,
            opportunityId: $opportunityId,
            workflowStatusId: null,
            note: null,
            commercialId: $referents[0]->id,
            commercialIdSubmitted: true,
            reporterId: $referents[1]->id,
            reporterIdSubmitted: true,
            supervisorId: $actor->id,
            supervisorIdSubmitted: true,
            internalNotes: null,
            offerLines: $offerLines,
            costLines: $costLines,
        );
    }

    /**
     * @param  list<array{0: string, 1: float, 2: float, 3: float|null, 4: int|null, 5: bool}>  $lines
     */
    private function seedPayments(WorkOrder $workOrder, Quote $quote, array $lines, User $actor): void
    {
        $quoteLines = $quote->offerLines()->orderBy('sort_order')->orderBy('id')->get()->values();

        foreach ($lines as $index => [, , , , $statusOldId, $hasUnpaid]) {
            $statusId = $statusOldId === null ? null : WorkOrderPaymentStatus::query()->where('old_id', $statusOldId)->value('id');

            if ($statusId === null) {
                continue;
            }

            $this->payments->handle($workOrder, $quoteLines[$index], UpdateWorkOrderLinePaymentData::fromValidated([
                'work_order_payment_status_id' => $statusId,
                'payment_agreement' => $statusOldId === 5 ? Catalogue::PAYMENT_AGREEMENT : null,
                'has_unpaid' => $hasUnpaid,
            ]), $actor);
        }
    }

    /** Same convention as DemoQuoteSeeder::resolveActor(). */
    private function resolveActor(): ?User
    {
        return User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', RoleAssignmentGuard::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();
    }
}
