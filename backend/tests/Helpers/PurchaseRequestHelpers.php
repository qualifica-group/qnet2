<?php

declare(strict_types=1);

use App\Enums\PurchaseRequestLineStatus;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('purchaseRequestUserWith')) {
    /**
     * A user holding exactly the given purchase-requests abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function purchaseRequestUserWith(array $abilities): User
    {
        foreach (['view', 'viewAll', 'create', 'update', 'delete', 'deleteLine', 'fulfill', 'manageStatuses', 'close', 'export', 'viewActivity'] as $ability) {
            // Explicit guard: Sanctum::actingAs() switches the default one to `sanctum`.
            Permission::findOrCreate("purchase-requests.{$ability}", 'web');
        }

        // A test may have checked a permission before this call: drop the stale registry.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("purchase-requests.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('purchaseRequestPayload')) {
    /**
     * A valid write payload with `$lineCount` lines (22% VAT on each).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function purchaseRequestPayload(array $overrides = [], int $lineCount = 1): array
    {
        $company = Company::factory()->create();
        $vat = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22]);

        $lines = [];

        for ($i = 1; $i <= $lineCount; $i++) {
            $lines[] = [
                'description' => "Line {$i}",
                'quantity' => '2',
                'unit_price' => '10.005',
                'vat_rate_id' => $vat->id,
            ];
        }

        return array_merge([
            'subject' => 'Laptops',
            'requested_at' => '2026-10-09',
            'priority' => 'high',
            'requester_id' => User::factory()->create()->id,
            'function_manager_id' => User::factory()->create()->id,
            'company_id' => $company->id,
            'company_site_id' => CompanySite::factory()->create(['company_id' => $company->id])->id,
            'operational_site_id' => OperationalSite::factory()->create()->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'lines' => $lines,
        ], $overrides);
    }
}

if (! function_exists('purchaseRequestWithLines')) {
    /**
     * A persisted request with one line per given status (positions 1..n).
     *
     * @param  array<int, PurchaseRequestLineStatus>  $statuses
     * @param  array<string, mixed>  $attributes  request attributes
     */
    function purchaseRequestWithLines(array $statuses = [PurchaseRequestLineStatus::PendingApproval], array $attributes = []): PurchaseRequest
    {
        $request = PurchaseRequest::factory()->create($attributes);

        foreach (array_values($statuses) as $index => $status) {
            PurchaseRequestLine::factory()->create([
                'purchase_request_id' => $request->id,
                'position' => $index + 1,
                'status' => $status,
            ]);
        }

        return $request->fresh('lines');
    }
}

if (! function_exists('supplierRegistry')) {
    function supplierRegistry(bool $isSupplier = true): Registry
    {
        return Registry::factory()->create(['is_supplier' => $isSupplier]);
    }
}

if (! function_exists('purchaseRequestUpdatePayload')) {
    /**
     * A PUT payload that keeps the stored request unchanged (same header, same
     * lines by id): tests then override only what they exercise.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function purchaseRequestUpdatePayload(PurchaseRequest $request, array $overrides = []): array
    {
        $payload = purchaseRequestPayload([
            'requester_id' => $request->requester_id,
            'function_manager_id' => $request->function_manager_id,
            'company_id' => $request->company_id,
            'company_site_id' => $request->company_site_id,
            'operational_site_id' => $request->operational_site_id,
            'business_function_id' => $request->business_function_id,
            'subject' => $request->subject,
            'requested_at' => $request->requested_at->toDateString(),
            'priority' => $request->priority->value,
        ]);
        $payload['lines'] = $request->lines->map(fn (PurchaseRequestLine $line): array => [
            'id' => $line->id,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
        ])->all();

        return array_merge($payload, $overrides);
    }
}
