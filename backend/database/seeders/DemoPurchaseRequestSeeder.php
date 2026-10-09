<?php

namespace Database\Seeders;

use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestPriority;
use App\Models\BusinessFunction;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Models\VatRate;
use App\Services\PurchaseRequests\PurchaseRequestAmountCalculator;
use Illuminate\Database\Seeder;

/**
 * Seed fake purchase requests (spec 0208): one open with a line per interesting
 * status and one with a single pending line. Idempotent: each request is keyed
 * by its subject. Skipped quietly when the reference data it hangs on (users,
 * a company with a site, an operational site, a business function) is missing.
 */
class DemoPurchaseRequestSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->orderBy('id')->limit(2)->get();
        $site = CompanySite::query()->whereNotNull('company_id')->orderBy('id')->first();
        $operationalSite = OperationalSite::query()->orderBy('id')->first();
        $function = BusinessFunction::query()->orderBy('id')->first();

        if ($users->isEmpty() || $site === null || $operationalSite === null || $function === null) {
            return;
        }

        $header = [
            'requester_id' => $users->first()->id,
            'function_manager_id' => $function->manager_id ?? $users->last()->id,
            'company_id' => $site->company_id,
            'company_site_id' => $site->id,
            'operational_site_id' => $operationalSite->id,
            'business_function_id' => $function->id,
            'requested_at' => now()->toDateString(),
        ];
        $vatRateId = VatRate::query()->orderByDesc('rate')->value('id');

        $this->request('Portatili per il nuovo team (demo)', PurchaseRequestPriority::High, $header, $vatRateId, [
            ['Portatile 14 pollici', '3', '950.00', PurchaseRequestLineStatus::Approved],
            ['Docking station USB-C', '3', '120.00', PurchaseRequestLineStatus::PendingApproval],
            ['Monitor 27 pollici', '2', '280.00', PurchaseRequestLineStatus::Rejected],
            ['Mouse e tastiera', '3', '45.00', PurchaseRequestLineStatus::Ordered],
        ]);
        $this->request('Cancelleria trimestrale (demo)', PurchaseRequestPriority::Low, $header, $vatRateId, [
            ['Risme di carta A4', '20', '4.50', PurchaseRequestLineStatus::PendingApproval],
        ]);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array{0: string, 1: string, 2: string, 3: PurchaseRequestLineStatus}>  $lines
     */
    private function request(string $subject, PurchaseRequestPriority $priority, array $header, ?int $vatRateId, array $lines): void
    {
        if (PurchaseRequest::query()->where('subject', $subject)->exists()) {
            return;
        }

        $calculator = new PurchaseRequestAmountCalculator;
        $rate = $vatRateId === null ? null : (string) VatRate::query()->whereKey($vatRateId)->value('rate');

        $request = new PurchaseRequest(['subject' => $subject, 'priority' => $priority] + $header);
        $request->forceFill(['created_by' => $header['requester_id']])->save();

        $computed = [];

        foreach ($lines as $index => [$description, $quantity, $unitPrice, $status]) {
            $amounts = $calculator->line($quantity, $unitPrice, $rate);
            $computed[] = $amounts;

            $line = new PurchaseRequestLine([
                'position' => $index + 1,
                'description' => $description,
                'vat_rate_id' => $vatRateId,
                'quantity' => $amounts['quantity'],
                'unit_price' => $amounts['unit_price'],
            ]);
            $line->forceFill([
                'purchase_request_id' => $request->id,
                'status' => $status,
                'taxable_amount' => $amounts['taxable_amount'],
                'vat_amount' => $amounts['vat_amount'],
                'total_amount' => $amounts['total_amount'],
            ])->save();
        }

        $request->forceFill($calculator->totals($computed))->save();
    }
}
