<?php

namespace App\Http\Resources;

use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail projection of a FinancialAccount (spec 0189). The card number is NEVER
 * emitted: only the masked form built from the stored last four digits.
 *
 * @mixin FinancialAccount
 */
class FinancialAccountResource extends JsonResource
{
    private const string MASK_PREFIX = '**** ';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'company' => $this->company === null ? null : ['id' => $this->company->id, 'name' => $this->company->denomination],
            'iban' => $this->iban,
            'account_number' => $this->account_number,
            'address_line' => $this->address_line,
            'postal_code' => $this->postal_code,
            'country' => $this->reference($this->country),
            'state' => $this->reference($this->state),
            'province' => $this->reference($this->province),
            'city' => $this->reference($this->city),
            'card_type' => $this->card_type,
            'card_circuit' => $this->card_circuit,
            'linked_account' => $this->linkedAccount === null
                ? null
                : ['id' => $this->linkedAccount->id, 'name' => $this->linkedAccount->name, 'iban' => $this->linkedAccount->iban],
            'card_holder' => $this->card_holder,
            'card_number_masked' => $this->card_last_four === null ? null : self::MASK_PREFIX.$this->card_last_four,
            'card_expiry' => $this->card_expiry,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function reference(mixed $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }
}
