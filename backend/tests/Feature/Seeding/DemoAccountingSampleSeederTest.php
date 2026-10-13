<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\OperationalSite;
use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\DemoAccountingSampleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The reference data the accounting chain hangs on, a super-admin to act as and
 * `$workOrders` invoiceable work orders (two revenue lines each).
 */
function seedAccountingSampleContext(int $workOrders): void
{
    test()->seed(RolePermissionSeeder::class);
    User::factory()->create()->assignRole('super-admin');
    User::factory()->count(2)->create();

    $company = Company::factory()->create();
    CompanySite::factory()->create(['company_id' => $company->id]);
    OperationalSite::factory()->create();
    BusinessFunction::factory()->create();
    Registry::factory()->supplier()->count(2)->create();
    VatRate::factory()->create(['rate' => '22']);
    FinancialAccount::factory()->bankAccount()->create(['company_id' => $company->id]);
    // The methods are cycled by position: the partial collection (third work order)
    // lands on the single-installment one, which has no later installment to spread on.
    $method = PaymentMethod::factory()->create(['installments_count' => 1, 'payment_days' => 30, 'days_between_installments' => 0]);
    PaymentMethod::factory()->create(['installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);

    foreach (range(1, $workOrders) as $ignored) {
        proformaWorkOrder([['consultancy', null], ['consultancy', null]], $method);
    }
}

it('skips with no super-admin to act as', function (): void {
    $this->seed(DemoAccountingSampleSeeder::class);

    expect(PurchaseRequest::query()->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(0);
});

it('seeds purchasing and accounting through the real services, covering every status', function (): void {
    seedAccountingSampleContext(4);

    $this->seed(DemoAccountingSampleSeeder::class);

    $lineStatuses = PurchaseRequestLine::query()->pluck('status')->map->value->unique();
    $orderStatuses = PurchaseOrder::query()->pluck('status')->map->value->unique();
    $billable = PurchaseOrder::query()->whereIn('status', [
        PurchaseOrderStatus::SentToSupplier, PurchaseOrderStatus::PartiallyFulfilled, PurchaseOrderStatus::Fulfilled,
    ])->count();

    expect(PurchaseRequest::query()->count())->toBe(8)
        ->and($lineStatuses->all())->toContain(
            PurchaseRequestLineStatus::PendingApproval->value,
            PurchaseRequestLineStatus::Approved->value,
            PurchaseRequestLineStatus::Rejected->value,
            PurchaseRequestLineStatus::OnHold->value,
            PurchaseRequestLineStatus::Ordered->value,
        )
        ->and($orderStatuses->sort()->values()->all())->toBe(collect(PurchaseOrderStatus::values())->sort()->values()->all())
        ->and(PurchaseOrder::query()->whereHas('lines', fn ($query) => $query->whereNotNull('purchase_request_line_id'))->exists())->toBeTrue()
        ->and(PurchaseInvoice::query()->count())->toBe($billable)
        ->and(PurchaseInvoice::query()->get()->every(fn (PurchaseInvoice $document): bool => bccomp((string) $document->installments()->sum('amount'), (string) $document->total_amount, 2) === 0))->toBeTrue()
        // four work orders: three issued documents and one request left pending
        ->and(Invoice::query()->count())->toBe(3)
        ->and(ProformaRequest::query()->count())->toBe(4)
        ->and(Invoice::query()->whereHas('installments', fn ($query) => $query->whereNotNull('collected_at'))->count())->toBe(3);
});

it('appends on every run without invoicing a work order twice', function (): void {
    seedAccountingSampleContext(2);

    $this->seed(DemoAccountingSampleSeeder::class);
    $this->seed(DemoAccountingSampleSeeder::class);

    expect(PurchaseRequest::query()->count())->toBe(16)
        ->and(ProformaRequest::query()->count())->toBe(2)
        ->and(Invoice::query()->count())->toBe(2)
        ->and(PurchaseOrder::query()->whereDoesntHave('purchaseInvoices')->whereIn('status', [
            PurchaseOrderStatus::SentToSupplier, PurchaseOrderStatus::PartiallyFulfilled, PurchaseOrderStatus::Fulfilled,
        ])->count())->toBe(0);
});
