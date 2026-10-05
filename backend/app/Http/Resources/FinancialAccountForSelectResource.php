<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;

/**
 * For-select projection of a FinancialAccount (GET
 * /api/financial-accounts/for-select, spec 0189): `label` = name, `subtitle` =
 * iban (omitted when null), `meta.type` lets a picker tell the types apart.
 *
 * @mixin FinancialAccount
 */
class FinancialAccountForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->iban,
            'meta' => ['type' => $this->type->value],
        ];
    }
}
