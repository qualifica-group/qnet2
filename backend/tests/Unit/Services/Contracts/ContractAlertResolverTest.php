<?php

use App\Enums\ContractStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Services\Contracts\ContractAlertResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

// Touches the database (Contract/ContractStatus factories), so bind the full
// TestCase + RefreshDatabase explicitly.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Config::set('contracts.expiring_within_days', 30);
    Config::set('contracts.renewal_within_days', 30);
});

function makeAlertContract(array $attributes = [], ?ContractStatusGroup $group = null): Contract
{
    $status = ContractStatus::factory()->create(['group' => $group ?? ContractStatusGroup::Pending]);

    return Contract::factory()->create(array_merge(['contract_status_id' => $status->id], $attributes));
}

// ---------------------------------------------------------------------------
// BR-6: 'expiring'
// ---------------------------------------------------------------------------

it('resolves "expiring" when expiry_date is today (inclusive lower bound)', function () {
    $contract = makeAlertContract(['expiry_date' => Carbon::today()]);

    expect((new ContractAlertResolver)->resolve($contract))->toBe('expiring');
});

it('resolves "expiring" when expiry_date is exactly at the configured threshold (inclusive upper bound)', function () {
    $contract = makeAlertContract(['expiry_date' => Carbon::today()->addDays(30)]);

    expect((new ContractAlertResolver)->resolve($contract))->toBe('expiring');
});

it('resolves null when expiry_date is one day past the configured threshold', function () {
    $contract = makeAlertContract(['expiry_date' => Carbon::today()->addDays(31)]);

    expect((new ContractAlertResolver)->resolve($contract))->toBeNull();
});

it('resolves null when expiry_date has already passed', function () {
    $contract = makeAlertContract(['expiry_date' => Carbon::today()->subDay()]);

    expect((new ContractAlertResolver)->resolve($contract))->toBeNull();
});

// ---------------------------------------------------------------------------
// BR-6: 'renewal_due'
// ---------------------------------------------------------------------------

it('resolves "renewal_due" when renewal_date is within the window and no expiry alert applies', function () {
    $contract = makeAlertContract(['renewal_date' => Carbon::today()->addDays(10)]);

    expect((new ContractAlertResolver)->resolve($contract))->toBe('renewal_due');
});

it('resolves null when renewal_date has already passed', function () {
    $contract = makeAlertContract(['renewal_date' => Carbon::today()->subDay()]);

    expect((new ContractAlertResolver)->resolve($contract))->toBeNull();
});

// ---------------------------------------------------------------------------
// BR-6: expiry takes precedence over renewal
// ---------------------------------------------------------------------------

it('prefers "expiring" over "renewal_due" when both windows apply', function () {
    $contract = makeAlertContract([
        'expiry_date' => Carbon::today()->addDays(5),
        'renewal_date' => Carbon::today()->addDays(5),
    ]);

    expect((new ContractAlertResolver)->resolve($contract))->toBe('expiring');
});

// ---------------------------------------------------------------------------
// BR-6: closed_lost never alerts
// ---------------------------------------------------------------------------

it('never alerts a contract whose status group is closed_lost, whatever the dates', function () {
    $contract = makeAlertContract(
        ['expiry_date' => Carbon::today(), 'renewal_date' => Carbon::today()],
        ContractStatusGroup::ClosedLost,
    );

    expect((new ContractAlertResolver)->resolve($contract))->toBeNull();
});

// ---------------------------------------------------------------------------
// daysToExpiry() / daysToRenewal()
// ---------------------------------------------------------------------------

it('daysToExpiry()/daysToRenewal() return null when the corresponding date is null', function () {
    $contract = makeAlertContract();

    $resolver = new ContractAlertResolver;

    expect($resolver->daysToExpiry($contract))->toBeNull()
        ->and($resolver->daysToRenewal($contract))->toBeNull();
});

it('daysToExpiry()/daysToRenewal() return the signed day count from today', function () {
    $contract = makeAlertContract([
        'expiry_date' => Carbon::today()->addDays(7),
        'renewal_date' => Carbon::today()->subDays(3),
    ]);

    $resolver = new ContractAlertResolver;

    expect($resolver->daysToExpiry($contract))->toBe(7)
        ->and($resolver->daysToRenewal($contract))->toBe(-3);
});
