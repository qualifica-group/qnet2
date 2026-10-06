<?php

use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\MigrationStatus;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// seedMigrationsConfig/fakeMigrationsBaseUrl/migrationsSuperAdminActor/
// runMigrationJobFor live in tests/Helpers/MigrationHelpers.php.

if (! function_exists('legacyQuoteServiceProduct')) {
    /**
     * A sellable product migrated from the legacy `services` table, under a
     * category with a business function so the opportunity coverage holds.
     */
    function legacyQuoteServiceProduct(int $legacyId): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->saleOnly()->create([
            'category_id' => $category->id,
            'old_source' => 'services',
            'old_id' => $legacyId,
        ]);
    }
}

if (! function_exists('legacyQuoteRecord')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyQuoteRecord(array $overrides = []): array
    {
        return array_merge([
            'id' => 42,
            'opportunity_id' => 7,
            'title' => 'Legacy offer',
            'description' => 'Legacy description',
            'quote_date' => '2024-03-01',
            'status' => 0,
            'accepted_at' => null,
            'validated_at' => null,
            'declined_at' => null,
            'renewal_date' => null,
            'commercial_referent_id' => null,
            'reporter_referent_id' => null,
            'supervisor_user_id' => null,
            'manager_user_ids' => [],
            'company_site_id' => null,
            'operational_site_id' => null,
            'payment_method_id' => null,
            'lines' => [
                ['id' => 900, 'product_id' => 10, 'description' => null, 'quantity' => 2, 'unit_price' => 50, 'vat_rate_id' => null, 'commissions' => []],
            ],
            'created_at' => '2024-03-01 10:00:00',
            'updated_at' => '2024-03-05 11:00:00',
        ], $overrides);
    }
}

if (! function_exists('runLegacyQuotesImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    function runLegacyQuotesImport(array $records, ?User $actor = null): MigrationRun
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/quotes*' => Http::response([
                'items' => $records,
                'pagination' => ['total' => count($records)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'quotes']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

if (! function_exists('legacyQuoteWarnings')) {
    /**
     * @return array<int, string>
     */
    function legacyQuoteWarnings(MigrationRun $run): array
    {
        return collect($run->report)->where('level', 'warning')->pluck('message')->values()->all();
    }
}

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
                ['role' => 'commercial', 'recipient_id' => 31, 'percentage' => 10, 'amount' => null],
                ['role' => 'supplier', 'recipient_id' => 34, 'percentage' => null, 'amount' => 15],
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
        ->and($commissions[CommissionRecipientRole::Supplier->value]->recipient_id)->toBe($supplier->id);
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

it('skips a line whose product is not migrated with a warning, keeping the quote', function () {
    legacyQuoteServiceProduct(10);

    $run = runLegacyQuotesImport([legacyQuoteRecord([
        'lines' => [
            ['id' => 900, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []],
            ['id' => 901, 'product_id' => 99, 'quantity' => 1, 'unit_price' => 10, 'commissions' => []],
        ],
    ])]);

    $quote = Quote::query()->where('old_id', 42)->sole();

    expect($run->created_rows)->toBe(1)
        ->and(QuoteLine::query()->where('quote_id', $quote->id)->pluck('old_id')->all())->toBe([900])
        ->and(legacyQuoteWarnings($run))->toContain('Line 901 skipped: product (legacy service id 99) not migrated.');
});

it('maps the legacy status onto the quote set', function (int $legacyStatus, string $systemKey) {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord(['status' => $legacyStatus])]);

    $statusId = Quote::query()->where('old_id', 42)->value('quote_workflow_status_id');

    expect(QuoteWorkflowStatus::query()->whereKey($statusId)->value('system_key'))->toBe($systemKey);
})->with([
    'presented stays open' => [0, 'open'],
    'rejected is closed lost' => [5, 'closed_lost'],
    'contract is closed won' => [2, 'closed_won'],
]);

it('moves an offer accepted by the client to the first pending status of its set', function () {
    legacyQuoteServiceProduct(10);
    $pending = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Pending, 'sort_order' => 5]);

    runLegacyQuotesImport([legacyQuoteRecord(['status' => 6])]);

    expect(Quote::query()->where('old_id', 42)->value('quote_workflow_status_id'))->toBe($pending->id);
});

it('opens the contract of a won offer aligned to the legacy dates and status', function () {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord([
        'status' => 2,
        'accepted_at' => '2024-04-01',
        'validated_at' => '2024-04-02',
        'renewal_date' => '2025-04-01',
    ])]);

    $contract = Contract::query()->where('quote_id', Quote::query()->where('old_id', 42)->value('id'))->sole();

    expect($contract->contract_status_id)->toBe(ContractStatus::query()->where('name', 'Programmato')->value('id'))
        ->and($contract->accepted_at->toDateString())->toBe('2024-04-01')
        ->and($contract->validated_at->toDateString())->toBe('2024-04-02')
        ->and($contract->renewal_date->toDateString())->toBe('2025-04-01');
});

it('terminates the contract of a legacy terminated offer on its declined date', function () {
    legacyQuoteServiceProduct(10);

    runLegacyQuotesImport([legacyQuoteRecord(['status' => 8, 'declined_at' => '2024-06-30'])]);

    $contract = Contract::query()->where('quote_id', Quote::query()->where('old_id', 42)->value('id'))->sole();

    expect($contract->contract_status_id)->toBe(ContractStatus::query()->where('system_key', 'terminated')->value('id'))
        ->and($contract->terminated_at->toDateString())->toBe('2024-06-30');
});

it('leaves a won offer without product lines open, with a warning and no contract', function () {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['status' => 2, 'lines' => []])]);

    $quote = Quote::query()->where('old_id', 42)->sole();

    expect(QuoteWorkflowStatus::query()->whereKey($quote->quote_workflow_status_id)->value('system_key'))->toBe('open')
        ->and(Contract::query()->where('quote_id', $quote->id)->exists())->toBeFalse()
        ->and(legacyQuoteWarnings($run))->toContain('Legacy status 2 needs at least one product line; the offer was left open.');
});

it('writes the legacy managers without notifying anyone nor logging activity', function () {
    Notification::fake();
    legacyQuoteServiceProduct(10);
    $first = User::factory()->create(['old_id' => 51]);
    $second = User::factory()->create(['old_id' => 52]);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    runLegacyQuotesImport([legacyQuoteRecord(['manager_user_ids' => [51, 52], 'supervisor_user_id' => 51, 'status' => 2])], $actor);

    $quote = Quote::query()->where('old_id', 42)->sole();
    $positions = $quote->managers()->get()->mapWithKeys(fn (User $user) => [$user->id => (int) $user->pivot->position])->all();

    expect($positions)->toBe([$first->id => 1, $second->id => 2])
        ->and($quote->operator_id)->toBe($second->id)
        ->and($this->opportunity->managers()->pluck('users.id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
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
