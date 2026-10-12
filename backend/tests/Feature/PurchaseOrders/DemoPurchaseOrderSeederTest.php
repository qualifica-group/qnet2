<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\DemoPurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('skips quietly when the reference data is missing', function () {
    $this->seed(DemoPurchaseOrderSeeder::class);

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('seeds three numbered orders once, whatever the number of runs', function () {
    User::factory()->create();
    CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    Registry::factory()->supplier()->create();

    $this->seed(DemoPurchaseOrderSeeder::class);
    $this->seed(DemoPurchaseOrderSeeder::class);

    $orders = PurchaseOrder::query()->with('lines')->orderBy('id')->get();

    expect($orders)->toHaveCount(3)
        ->and($orders->pluck('status')->all())->toBe([PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier])
        ->and($orders->pluck('number')->all())->toBe([1, 2, 3])
        ->and($orders->every(fn (PurchaseOrder $order): bool => $order->lines->isNotEmpty() && (float) $order->grand_total > 0))->toBeTrue();
});
