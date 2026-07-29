<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Authorization\FieldPermission;
use App\Models\QuoteLineCommission;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuoteLineCommission */
class QuoteLineCommissionResource extends JsonResource
{
    /**
     * @param  array<string, FieldPermission>  $permissions
     */
    public function __construct($resource, private readonly array $permissions)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'recipient_role' => $this->recipient_role->value,
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,
            'recipient' => $this->recipient === null ? null : [
                'id' => $this->recipient->getKey(),
                'name' => $this->recipient->name,
            ],
            'commission_type' => $this->commission_type->value,
            'value' => $this->value,
            'calculated_amount' => $this->calculated_amount,
            'internal_note' => $this->internal_note,
            'origin' => $this->origin->value,
            'commission_configuration_id' => $this->commission_configuration_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return app(QuoteCommissionPayloadRedactor::class)->redact($payload, $this->permissions);
    }
}
