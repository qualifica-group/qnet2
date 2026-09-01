<?php

use App\Enums\CommissionRecipientRole;

it('maps each role to its single morph alias (spec 0089 D-8)', function () {
    expect(CommissionRecipientRole::Commercial->recipientType())->toBe('referent')
        ->and(CommissionRecipientRole::Reporter->recipientType())->toBe('referent')
        ->and(CommissionRecipientRole::Supervisor->recipientType())->toBe('user')
        ->and(CommissionRecipientRole::Supplier->recipientType())->toBe('registry');
});
