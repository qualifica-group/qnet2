<?php

use App\Enums\CommissionRecipientRole;

it('maps each role to its default morph alias, the first of its allow-list (spec 0089 D-8, spec 0090 D-4)', function () {
    expect(CommissionRecipientRole::Commercial->recipientType())->toBe('referent')
        ->and(CommissionRecipientRole::Reporter->recipientType())->toBe('referent')
        ->and(CommissionRecipientRole::Supervisor->recipientType())->toBe('user')
        ->and(CommissionRecipientRole::Supplier->recipientType())->toBe('registry');
});

it('admits both referent and user for the three people roles, and only registry for supplier (spec 0090 D-4, INV-4)', function () {
    expect(CommissionRecipientRole::Commercial->allowedRecipientTypes())->toBe(['referent', 'user'])
        ->and(CommissionRecipientRole::Reporter->allowedRecipientTypes())->toBe(['referent', 'user'])
        ->and(CommissionRecipientRole::Supervisor->allowedRecipientTypes())->toBe(['user', 'referent'])
        ->and(CommissionRecipientRole::Supplier->allowedRecipientTypes())->toBe(['registry']);
});
