<?php

declare(strict_types=1);

use App\Enums\StatusSystemKey;
use App\Models\Attachment;
use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\ContractStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Contracts\ContractStatusResolver;
use App\Services\Contracts\ContractUntouchedCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Spec 0072 D-3 rev. (user decision 2026-11-20): what makes a contract
 * "worked on", and therefore kept when its offer stops being won.
 */
uses(RefreshDatabase::class);

if (! function_exists('untouchedContract')) {
    /** A contract as the lifecycle creates it: default status, nothing filled in. */
    function untouchedContract(): Contract
    {
        return Contract::factory()->create([
            'contract_status_id' => app(ContractStatusResolver::class)->defaultActiveId(),
        ]);
    }
}

it('a contract as the lifecycle creates it is untouched, also on the system "new" row', function () {
    $check = app(ContractUntouchedCheck::class);
    $onNew = Contract::factory()->create([
        'contract_status_id' => app(ContractStatusResolver::class)->systemId(StatusSystemKey::New),
    ]);

    expect($check->isUntouched(untouchedContract()))->toBeTrue()
        ->and($check->isUntouched($onNew))->toBeTrue();
});

it('a suspended contract is judged on the status it had before the suspension', function () {
    $resolver = app(ContractStatusResolver::class);
    $contract = Contract::factory()->create([
        'contract_status_id' => $resolver->systemId(StatusSystemKey::Suspended),
        'status_before_suspension_id' => $resolver->defaultActiveId(),
        'suspended_at' => now(),
    ]);
    $moved = Contract::factory()->create([
        'contract_status_id' => $resolver->systemId(StatusSystemKey::Suspended),
        'status_before_suspension_id' => ContractStatus::where('name', 'Programmato')->sole()->id,
        'suspended_at' => now(),
    ]);

    expect(app(ContractUntouchedCheck::class)->isUntouched($contract))->toBeTrue()
        ->and(app(ContractUntouchedCheck::class)->isUntouched($moved))->toBeFalse();
});

it('a contract is worked on once its own data or status moved', function (array $attributes) {
    $contract = untouchedContract();
    $contract->forceFill(value($attributes))->save();

    expect(app(ContractUntouchedCheck::class)->isUntouched($contract->fresh()))->toBeFalse();
})->with([
    'status changed' => [fn (): array => ['contract_status_id' => ContractStatus::where('name', 'Programmato')->sole()->id]],
    'validated' => [fn (): array => ['validated_at' => now()->toDateString()]],
    'terminated' => [fn (): array => ['terminated_at' => now()->toDateString()]],
    'renewal date' => [fn (): array => ['renewal_date' => '2027-01-01']],
    'expiry date' => [fn (): array => ['expiry_date' => '2027-12-31']],
    'payment notes' => [fn (): array => ['payment_notes' => 'Bonifico']],
    'comments' => [fn (): array => ['comments' => 'Nota']],
]);

it('a contract is worked on once it has a renewal, a document or a work order on its offer', function (string $dependant) {
    $contract = untouchedContract();

    match ($dependant) {
        'renewal' => ContractRenewal::factory()->create(['contract_id' => $contract->id]),
        'document' => $contract->attachments()->save(Attachment::factory()->make(['uploaded_by' => User::factory()->create()->id])),
        'work order' => WorkOrder::factory()->create(['quote_id' => $contract->quote_id]),
    };

    expect(app(ContractUntouchedCheck::class)->isUntouched($contract))->toBeFalse();
})->with(['renewal', 'document', 'work order']);
