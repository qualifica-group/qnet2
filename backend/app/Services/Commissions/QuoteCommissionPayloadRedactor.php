<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\Models\Quote;
use App\Models\User;

final class QuoteCommissionPayloadRedactor
{
    public function __construct(private readonly AuthorizationRegistry $authorization) {}

    /**
     * @return array<string, FieldPermission>
     */
    public function permissions(User $actor, ?Quote $quote): array
    {
        return $this->authorization->resolve('quotes')->fieldPermissions($actor, $quote);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, FieldPermission>  $permissions
     * @return array<string, mixed>
     */
    public function redact(array $payload, array $permissions): array
    {
        if (! $permissions['commission_recipient']->visible) {
            unset($payload['recipient_type'], $payload['recipient_id'], $payload['recipient']);
        }

        if (! $permissions['commission_type']->visible) {
            unset($payload['commission_type']);
        }

        if (! $permissions['commission_value']->visible) {
            unset($payload['value'], $payload['calculated_amount']);
        }

        if (! $permissions['commission_internal_note']->visible) {
            unset($payload['internal_note']);
        }

        return $payload;
    }
}
