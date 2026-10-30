<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\MigrationStatus;
use App\Enums\ProductUsage;
use App\Enums\WorkflowStatusGroup;
use App\Models\CommissionConfiguration;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// seedMigrationsConfig/fakeMigrationsBaseUrl/migrationsSuperAdminActor/
// runMigrationJobFor live in tests/Helpers/MigrationHelpers.php, the legacy
// quote fixtures in tests/Helpers/QuoteMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    $this->opportunity = Opportunity::factory()->create(['old_id' => 7]);
});

it('creates the quote with remapped roles, lines, VAT, commissions and legacy anchors', function () {
    legacyQuoteServiceProduct(10);
    $vatRate = VatRate::factory()->create(['old_id' => 3, 'rate' => 22]);
    $commercial = Referent::factory()->create(['old_id' => 31]);
    $reporter = Referent::factory()->create(['old_id' => 32]);
    $supervisor = User::factory()->create(['old_id' => 33]);
    $supplier = Registry::factory()->create(['old_id' => 34]);
    $companySite = CompanySite::factory()->create(['old_id' => 35]);
    $paymentMethod = PaymentMethod::factory()->create(['old_id' => 36]);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'commercial_referent_id' => 31,
        'reporter_referent_id' => 32,
        'supervisor_user_id' => 33,
        'company_site_id' => 35,
        'payment_method_id' => 36,
        'lines' => [[
            'id' => 900, 'product_id' => 10, 'description' => 'Extra notes', 'quantity' => 2, 'unit_price' => 50, 'vat_rate_id' => 3,
            'commissions' => [
                ['role' => 'commercial', 'recipient_id' => 31, 'commission_type' => 'PERCENTAGE', 'value' => 10],
                ['role' => 'supplier', 'recipient_id' => 34, 'commission_type' => 'FIXED_AMOUNT', 'value' => 15, 'supplier_commission_direction' => 'PAID'],
            ],
        ]],
    ])]);

    $quote = Quote::query()->where('old_id', 42)->sole();
    $line = QuoteLine::query()->where('quote_id', $quote->id)->sole();
    $commissions = $line->commissions()->get()->keyBy(fn ($commission) => $commission->recipient_role->value);

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($quote->code)->toBe('QUO-0042')
        ->and($quote->title)->toBe('Legacy offer')
        ->and($quote->internal_notes)->toBe('Legacy description')
        ->and($quote->commercial_id)->toBe($commercial->id)
        ->and($quote->reporter_id)->toBe($reporter->id)
        ->and($quote->supervisor_id)->toBe($supervisor->id)
        ->and($quote->company_site_id)->toBe($companySite->id)
        ->and($quote->company_id)->toBe($companySite->company_id)
        ->and($quote->payment_method_id)->toBe($paymentMethod->id)
        ->and($quote->created_at->toDateTimeString())->toBe('2024-03-01 10:00:00')
        ->and($quote->updated_at->toDateTimeString())->toBe('2024-03-05 11:00:00')
        ->and((float) $quote->revenue_net)->toBe(100.0)
        ->and((float) $quote->revenue_vat)->toBe(22.0)
        ->and($line->old_id)->toBe(900)
        ->and($line->vat_rate_id)->toBe($vatRate->id)
        ->and($line->additional_description)->toBe('Extra notes')
        ->and($commissions[CommissionRecipientRole::Commercial->value]->commission_type)->toBe(CommissionType::Percentage)
        ->and($commissions[CommissionRecipientRole::Commercial->value]->recipient_id)->toBe($commercial->id)
        ->and($commissions[CommissionRecipientRole::Supplier->value]->commission_type)->toBe(CommissionType::FixedAmount)
        ->and($commissions[CommissionRecipientRole::Supplier->value]->recipient_id)->toBe($supplier->id)
        ->and($line->fresh()->supplier_commission_direction?->value)->toBe('PAID');
});

it('freezes the RECEIVED direction the legacy sends for a supplier commission of an Ente line', function () {
    legacyQuoteServiceProduct(10);
    Registry::factory()->create(['old_id' => 34]);

    runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [[
            'id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 100,
            'commissions' => [['role' => 'supplier', 'recipient_id' => 34, 'commission_type' => 'PERCENTAGE', 'value' => 20, 'supplier_commission_direction' => 'RECEIVED']],
        ]],
    ])]);

    $line = QuoteLine::query()->where('quote_id', Quote::query()->where('old_id', 42)->value('id'))->sole();
    expect($line->supplier_commission_direction?->value)->toBe('RECEIVED');
});

it('falls back to a sequential code with a warning when QUO-{id} is taken', function () {
    legacyQuoteServiceProduct(10);
    Quote::factory()->create(['opportunity_id' => $this->opportunity->id])->forceFill(['code' => 'QUO-0042'])->save();

    $run = runLegacyQuotesImport([legacyQuoteRecord()]);

    expect(Quote::query()->where('old_id', 42)->value('code'))->not->toBe('QUO-0042')
        ->and(legacyQuoteWarnings($run))->toContain('Code QUO-0042 already taken; a sequential code was generated.');
});

it('resolves the line product on old_source services, never a cost product sharing the old_id', function () {
    $costProduct = Product::factory()->create(['old_source' => 'equipments', 'old_id' => 10]);
    $serviceProduct = legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord()]);

    $productId = QuoteLine::query()->where('old_id', 900)->value('product_id');

    expect($productId)->toBe($serviceProduct->id)
        ->and($productId)->not->toBe($costProduct->id);
});

// REQUIREMENT CHANGED (user decision 2026-10-14): a line, cost or commission
// that cannot be imported fails the whole quote instead of being dropped.
it('fails the quote when one of its lines cannot be imported', function (array $overrides, string $reason) {
    legacyQuoteServiceProduct(10);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []]],
        ...$overrides,
    ])]);

    expect($run->failed_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0)
        ->and(Quote::query()->where('old_id', 42)->exists())->toBeFalse()
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])->toBe("Failed to import the record: {$reason}");
})->with([
    'line without product' => [
        ['lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []], ['id' => 901, 'product_id' => 0, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []]]],
        'Line 901 has no product; the quote is not imported.',
    ],
    'line product not migrated' => [
        ['lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []], ['id' => 901, 'product_id' => 99, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []]]],
        'Line 901: product (legacy service id 99) not migrated; the quote is not imported.',
    ],
    'commission recipient not migrated' => [
        ['lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => [['role' => 'supervisor', 'recipient_id' => 555, 'commission_type' => 'PERCENTAGE', 'value' => 5]]]]],
        'Line 900: supervisor commission recipient (legacy id 555) not migrated; the quote is not imported.',
    ],
    'supplier commission without direction' => [
        ['lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => [['role' => 'supplier', 'recipient_id' => 34, 'commission_type' => 'PERCENTAGE', 'value' => 5]]]]],
        'Line 900: supplier commission without a valid direction; the quote is not imported.',
    ],
    'cost product not migrated' => [
        ['cost_lines' => [['id' => 'quotationemployees:20', 'product_source' => 'user_levels', 'product_id' => 2, 'quantity' => 1, 'unit_price' => 20, 'offer_line_id' => 900]]],
        'Cost line quotationemployees:20: cost product (user_levels legacy id 2) not migrated; the quote is not imported.',
    ],
]);

it('maps the legacy outcome onto the quote set', function (string $outcome, string $systemKey) {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord(['outcome' => $outcome])]);

    $statusId = Quote::query()->where('old_id', 42)->value('quote_workflow_status_id');

    expect(QuoteWorkflowStatus::query()->whereKey($statusId)->value('system_key'))->toBe($systemKey);
})->with([
    'open stays open' => ['open', 'open'],
    'lost is closed lost' => ['lost', 'closed_lost'],
    'won is closed won' => ['won', 'closed_won'],
]);

it('moves an offer accepted by the client to the first pending status of its set', function () {
    legacyQuoteServiceProduct(10);
    $pending = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Pending, 'sort_order' => 5]);

    runLegacyQuotesImport([legacyQuoteRecord(['outcome' => 'accepted_by_client'])]);

    expect(Quote::query()->where('old_id', 42)->value('quote_workflow_status_id'))->toBe($pending->id);
});

it('opens the contract of a won offer aligned to the legacy dates and status', function () {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord([
        'outcome' => 'won',
        'contract' => [
            'status' => 'scheduled',
            'accepted_at' => '2024-04-01',
            'validated_at' => '2024-04-02',
            'renewal_date' => '2025-04-01',
            'terminated_at' => null,
        ],
    ])]);

    $contract = Contract::query()->where('quote_id', Quote::query()->where('old_id', 42)->value('id'))->sole();

    expect($contract->contract_status_id)->toBe(ContractStatus::query()->where('name', 'Programmato')->value('id'))
        ->and($contract->accepted_at->toDateString())->toBe('2024-04-01')
        ->and($contract->validated_at->toDateString())->toBe('2024-04-02')
        ->and($contract->renewal_date->toDateString())->toBe('2025-04-01');
});

it('terminates the contract of a legacy terminated offer on its declined date', function () {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord([
        'outcome' => 'won',
        'contract' => ['status' => 'terminated', 'accepted_at' => null, 'validated_at' => null, 'renewal_date' => null, 'terminated_at' => '2024-06-30'],
    ])]);

    $contract = Contract::query()->where('quote_id', Quote::query()->where('old_id', 42)->value('id'))->sole();

    expect($contract->contract_status_id)->toBe(ContractStatus::query()->where('system_key', 'terminated')->value('id'))
        ->and($contract->terminated_at->toDateString())->toBe('2024-06-30');
});

// REQUIREMENT CHANGED (user decision 2026-10-13): a legacy offer closed without
// migrated lines keeps its outcome instead of staying open.
it('closes a legacy offer without product lines, with a warning', function (string $outcome, string $systemKey) {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['outcome' => $outcome, 'lines' => []])]);

    $quote = Quote::query()->where('old_id', 42)->sole();

    expect(QuoteWorkflowStatus::query()->whereKey($quote->quote_workflow_status_id)->value('system_key'))->toBe($systemKey)
        ->and(legacyQuoteWarnings($run))->toContain("Legacy outcome '{$outcome}' closed the offer without product lines.");
})->with([
    'won' => ['won', 'closed_won'],
    'lost' => ['lost', 'closed_lost'],
]);

it('writes the legacy managers without notifying anyone nor logging activity', function () {
    Notification::fake();
    legacyQuoteServiceProduct(10);
    $first = User::factory()->create(['old_id' => 51]);
    $second = User::factory()->create(['old_id' => 52]);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    runLegacyQuotesImport([legacyQuoteRecord(['manager_user_ids' => [51, 52], 'supervisor_user_id' => 51, 'outcome' => 'won'])], $actor);

    $quote = Quote::query()->where('old_id', 42)->sole();
    $positions = $quote->managers()->get()->mapWithKeys(fn (User $user) => [$user->id => (int) $user->pivot->position])->all();

    expect($positions)->toBe([$first->id => 1, $second->id => 2])
        ->and($quote->operator_id)->toBe($second->id)
        ->and($this->opportunity->managers()->pluck('users.id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('adds to the opportunity only the quote managers it lacks (G-16)', function () {
    legacyQuoteServiceProduct(10);
    $opportunityManager = User::factory()->create(['old_id' => 51]);
    $quoteManager = User::factory()->create(['old_id' => 52]);
    $this->opportunity->managers()->attach($opportunityManager->id, ['position' => 1]);

    runLegacyQuotesImport([legacyQuoteRecord(['manager_user_ids' => [51, 52]])], migrationsSuperAdminActor());

    $quote = Quote::query()->where('old_id', 42)->sole();

    expect($quote->managers()->pluck('users.id')->sort()->values()->all())->toBe([$opportunityManager->id, $quoteManager->id])
        ->and($this->opportunity->managers()->pluck('users.id')->sort()->values()->all())->toBe([$opportunityManager->id, $quoteManager->id]);
});

it('skips an already imported quote on a second run', function () {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord()]);
    $second = runLegacyQuotesImport([legacyQuoteRecord()]);

    expect($second->skipped_rows)->toBe(1)
        ->and($second->created_rows)->toBe(0)
        ->and(Quote::query()->where('old_id', 42)->count())->toBe(1);
});

it('fails the row when the parent opportunity is not migrated', function () {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['opportunity_id' => 999])]);

    expect($run->failed_rows)->toBe(1)
        ->and(Quote::query()->count())->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])
        ->toContain('Unresolved opportunity_id (legacy id 999)');
});

it('writes the legacy offer date and the opportunity callback (G-14)', function () {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord(['quote_date' => '2024-02-20', 'callback_date' => '2024-04-10'])]);

    $quote = Quote::query()->where('old_id', 42)->sole();

    expect($quote->quote_date->toDateString())->toBe('2024-02-20')
        ->and($quote->next_callback_at->toDateTimeString())->toBe('2024-04-10 00:00:00')
        ->and($quote->next_callback_reminded_at)->toBeNull();
});

it('AC-009: saves the remapped Fonte of the first quotation and leaves the others without', function () {
    legacyQuoteServiceProduct(10);
    $source = Source::factory()->create(['old_id' => 5]);

    runLegacyQuotesImport([
        legacyQuoteRecord(['source_id' => 5]),
        legacyQuoteRecord(['id' => 43, 'lines' => [
            ['id' => 901, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []],
        ]]),
    ]);

    expect(Quote::query()->where('old_id', 42)->value('source_id'))->toBe($source->id)
        ->and(Quote::query()->where('old_id', 43)->value('source_id'))->toBeNull();
});

it('AC-009: warns and keeps the offer without Fonte when the legacy source is not migrated', function () {
    legacyQuoteServiceProduct(10);

    $run = runLegacyQuotesImport([legacyQuoteRecord(['source_id' => 999])]);

    expect($run->created_rows)->toBe(1)
        ->and(Quote::query()->where('old_id', 42)->value('source_id'))->toBeNull()
        ->and(legacyQuoteWarnings($run))->toBe(['Unresolved source_id (legacy id 999).']);
});

it('reports a legacy customer other than the opportunity one, keeping the opportunity (G-15)', function () {
    legacyQuoteServiceProduct(10);

    $run = runLegacyQuotesImport([
        legacyQuoteRecord(['customer_differs_from_opportunity' => true]),
        legacyQuoteRecord(['id' => 43, 'lines' => [
            ['id' => 901, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []],
        ]]),
    ]);

    expect($run->created_rows)->toBe(2)
        ->and(Quote::query()->where('old_id', 42)->value('opportunity_id'))->toBe($this->opportunity->id)
        ->and(legacyQuoteWarnings($run))->toBe([
            "Legacy customer differs from the opportunity's; the offer stays under the opportunity's customer.",
        ]);
});

it('imports the legacy costs as cost lines imputed to the offer line of the same service (G-13)', function () {
    legacyQuoteServiceProduct(10);
    legacyQuoteServiceProduct(11);
    $costProduct = Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 3]);
    VatRate::factory()->create(['old_id' => 6, 'rate' => 22]);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [
            ['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 100, 'commissions' => []],
            ['id' => 901, 'product_id' => 11, 'quantity' => 1, 'unit_price' => 200, 'commissions' => []],
        ],
        'cost_lines' => [
            ['id' => 1, 'product_id' => 3, 'description' => 'Docente', 'quantity' => 2, 'unit_price' => 30, 'vat_rate_id' => 6, 'supplier_id' => null, 'offer_line_id' => 901],
            ['id' => 2, 'product_id' => 3, 'description' => null, 'quantity' => 1, 'unit_price' => 15, 'vat_rate_id' => null, 'supplier_id' => null, 'offer_line_id' => null],
        ],
    ])]);

    $quote = Quote::query()->where('old_id', 42)->sole();
    $costLines = $quote->costLines()->orderBy('sort_order')->get();

    expect($run->report)->toBeNull()
        ->and($costLines)->toHaveCount(2)
        ->and($costLines[0]->product_id)->toBe($costProduct->id)
        ->and($costLines[0]->offer_line_id)->toBe(QuoteLine::query()->where('old_id', 901)->value('id'))
        ->and($costLines[0]->additional_description)->toBe('Docente')
        ->and($costLines[0]->old_id)->toBeNull()
        ->and($costLines[1]->offer_line_id)->toBeNull()
        ->and((float) $quote->cost_net)->toBe(75.0)
        ->and((float) $quote->margin_net)->toBe(225.0);
});

it('imports the legacy employee costs as cost lines on the employee level product (G-13)', function () {
    legacyQuoteServiceProduct(10);
    $expenseReport = Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 6]);
    $employeeLevel = Product::factory()->costOnly()->create(['old_source' => 'user_levels', 'old_id' => 6]);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 300, 'commissions' => []]],
        'cost_lines' => [
            ['id' => 'quotationemployees:19', 'product_source' => 'user_levels', 'product_id' => 6, 'description' => null, 'quantity' => 8, 'unit_price' => 20, 'vat_rate_id' => null, 'supplier_id' => null, 'offer_line_id' => 900],
        ],
    ])]);

    $costLine = Quote::query()->where('old_id', 42)->sole()->costLines()->sole();

    expect($costLine->product_id)->toBe($employeeLevel->id)
        ->and($costLine->product_id)->not->toBe($expenseReport->id)
        ->and((float) $costLine->quantity)->toBe(8.0)
        ->and((float) $costLine->net_amount)->toBe(160.0)
        ->and($costLine->offer_line_id)->toBe(QuoteLine::query()->where('old_id', 900)->value('id'))
        ->and($run->report)->toBeNull();
});

it('imports revenue and cost lines whose products sit on categories that are not active, history only (user directive 2026-10-13)', function () {
    $service = legacyQuoteServiceProduct(10);
    $service->category->update(['is_active' => false]);
    $costProduct = Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 3]);
    $costProduct->category->update(['is_active' => false]);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 100, 'commissions' => []]],
        'cost_lines' => [['id' => 1, 'product_id' => 3, 'description' => null, 'quantity' => 1, 'unit_price' => 15, 'vat_rate_id' => null, 'supplier_id' => null, 'offer_line_id' => 900]],
    ])]);

    $quote = Quote::query()->where('old_id', 42)->sole();

    expect($run->report)->toBeNull()
        ->and($quote->offerLines()->sole()->product_id)->toBe($service->id)
        ->and($quote->costLines()->sole()->product_id)->toBe($costProduct->id);
});

it('keeps a cost whose offer line is not on the quote as a generic cost, with a warning', function () {
    legacyQuoteServiceProduct(10);
    Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 3]);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 100, 'commissions' => []]],
        'cost_lines' => [['id' => 1, 'product_id' => 3, 'quantity' => 1, 'unit_price' => 10, 'offer_line_id' => 901]],
    ])]);

    $quote = Quote::query()->where('old_id', 42)->sole();

    expect($quote->costLines()->count())->toBe(1)
        ->and($quote->costLines()->value('offer_line_id'))->toBeNull()
        ->and(legacyQuoteWarnings($run))->toBe(['Cost line 1: its offer line (legacy id 901) was not imported, kept as a generic cost.']);
});

it('reports a legacy cost supplier other than the product default one, never writing it', function () {
    legacyQuoteServiceProduct(10);
    $supplier = Registry::factory()->supplier()->create(['old_id' => 70]);
    productWithSupplier(['old_source' => 'expense_reports', 'old_id' => 3, 'usages' => [ProductUsage::Cost]], $supplier);
    Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 4]);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'cost_lines' => [
            ['id' => 1, 'product_id' => 3, 'quantity' => 1, 'unit_price' => 10, 'supplier_id' => 70],
            ['id' => 2, 'product_id' => 3, 'quantity' => 1, 'unit_price' => 10, 'supplier_id' => 71],
            ['id' => 3, 'product_id' => 4, 'quantity' => 1, 'unit_price' => 10, 'supplier_id' => 70],
        ],
    ])]);

    expect(Quote::query()->where('old_id', 42)->sole()->costLines()->count())->toBe(3)
        ->and(legacyQuoteWarnings($run))->toBe([
            "Cost line 2: legacy supplier (legacy id 71) differs from the product's default (legacy id 70).",
            "Cost line 3: legacy supplier (legacy id 70) differs from the product's: the product has no default supplier.",
        ]);
});

it('AC-009: lines of both tabs carry the default supplier of their product, null without suppliers', function () {
    $supplier = Registry::factory()->supplier()->create();
    $revenueProduct = legacyQuoteServiceProduct(10);
    ProductSupplier::factory()->create(['product_id' => $revenueProduct->id, 'supplier_id' => Registry::factory()->supplier()->create()->id, 'is_default' => false]);
    ProductSupplier::factory()->default()->create(['product_id' => $revenueProduct->id, 'supplier_id' => $supplier->id]);
    legacyQuoteServiceProduct(11);
    $costProduct = productWithSupplier(['old_source' => 'expense_reports', 'old_id' => 3, 'usages' => [ProductUsage::Cost]], $supplier);
    Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 4]);

    runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [
            ['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 100],
            ['id' => 901, 'product_id' => 11, 'quantity' => 1, 'unit_price' => 50],
        ],
        'cost_lines' => [
            ['id' => 1, 'product_id' => 3, 'quantity' => 1, 'unit_price' => 10],
            ['id' => 2, 'product_id' => 4, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])]);

    $quote = Quote::query()->where('old_id', 42)->sole();
    $bySort = fn ($lines) => $lines->orderBy('sort_order')->pluck('supplier_id')->all();

    expect($bySort($quote->offerLines()))->toBe([$supplier->id, null])
        ->and($bySort($quote->costLines()))->toBe([$supplier->id, null])
        ->and($costProduct->id)->not->toBeNull();
});

it('0320 D-23: a legacy cost line keeps its historical price even when its supplier is paid through a commission', function () {
    legacyQuoteServiceProduct(10);
    $supplier = Registry::factory()->supplier()->create();
    $costProduct = productWithSupplier([
        'old_source' => 'expense_reports',
        'old_id' => 3,
        'usages' => [ProductUsage::Cost],
        'product_typology_id' => ProductTypology::factory()->supplierCommission()->create()->id,
    ], $supplier);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Supplier,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $costProduct->id,
        'recipient_type' => 'registry',
        'recipient_id' => $supplier->id,
    ]);

    runLegacyQuotesImport([legacyQuoteRecord([
        'cost_lines' => [['id' => 1, 'product_id' => 3, 'quantity' => 1, 'unit_price' => 40]],
    ])]);

    $line = Quote::query()->where('old_id', 42)->sole()->costLines()->sole();

    expect($line->supplier_id)->toBe($supplier->id)
        ->and((float) $line->unit_price)->toBe(40.0);
});
