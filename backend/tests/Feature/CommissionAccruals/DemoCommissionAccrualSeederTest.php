<?php

use App\Enums\CommissionRecipientRole;
use App\Models\AgentFiscalProfile;
use App\Models\CommissionAccrual;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAgentFiscalProfileSeeder;
use Database\Seeders\DemoCommissionAccrualSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// A full DemoDataSeeder run cannot be exercised on the SQLite test driver (the geo
// dataset import rejects it), so the two seeders run on a minimal invoiced graph.

it('AC-019: the two demo seeders build profiles and the ledger, and a second run keeps the same counts', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    accrualCommission($line, '20.00', CommissionRecipientRole::Reporter);
    $invoice = accrualInvoice([[$line, '1000.00']], ['600.00', '600.00']);
    accrualCollect(accrualInstallment($invoice), '600.00', '2026-04-20');

    // The ledger rebuilt from scratch by the seeder, not left over from the collection hook
    CommissionAccrual::query()->delete();

    $this->seed(DemoAgentFiscalProfileSeeder::class);
    $this->seed(DemoCommissionAccrualSeeder::class);

    $profiles = AgentFiscalProfile::query()->count();
    $accruals = CommissionAccrual::query()->count();

    expect($profiles)->toBe(2)
        ->and($accruals)->toBeGreaterThan(0);

    $this->seed(DemoAgentFiscalProfileSeeder::class);
    $this->seed(DemoCommissionAccrualSeeder::class);

    expect(AgentFiscalProfile::query()->count())->toBe($profiles)
        ->and(CommissionAccrual::query()->count())->toBe($accruals);
});

it('AC-019: profiles of the same agent never overlap and the history agent has a closed past profile', function () {
    foreach (range(1, 4) as $ignored) {
        accrualCommission(accrualQuoteLine(), '10.00');
    }

    $this->seed(DemoAgentFiscalProfileSeeder::class);

    $history = AgentFiscalProfile::query()->whereNotNull('valid_until')->sole();
    $current = AgentFiscalProfile::query()->where('referent_id', $history->referent_id)->whereNull('valid_until')->sole();

    expect($history->valid_until->lt($current->valid_from))->toBeTrue()
        ->and(AgentFiscalProfile::query()->where('enasarco_enabled', true)->count())->toBe(2);
});

it('AC-019: the clean DatabaseSeeder does not call the two demo seeders', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());

    expect($source)->not->toContain('DemoAgentFiscalProfileSeeder')
        ->and($source)->not->toContain('DemoCommissionAccrualSeeder');
});
