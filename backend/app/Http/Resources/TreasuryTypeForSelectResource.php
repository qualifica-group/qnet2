<?php

namespace App\Http\Resources;

use App\Models\TreasuryType;
use App\Services\ChartOfAccounts\TreasuryTypeRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item of GET /api/treasury-types/for-select (spec 0355, D-3): the sub-account
 * reference plus its account and group, for the grouped picker. Expects
 * `chartAccount.parent` loaded.
 *
 * @mixin TreasuryType
 */
class TreasuryTypeForSelectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->chartAccount;
        $group = $account->parent;

        return [
            ...TreasuryTypeRef::from($this->resource),
            'group' => ['code' => $group->code, 'name' => $group->name],
            'account' => ['code' => $account->code, 'name' => $account->name],
        ];
    }
}
