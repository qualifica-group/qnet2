<?php

namespace App\Http\Resources;

use App\Models\RevenueType;
use App\Services\ChartOfAccounts\RevenueTypeRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item of GET /api/revenue-types/for-select (spec 0350, D-10): the sub-account
 * reference plus its account and group, for the grouped picker. Expects
 * `chartAccount.parent` loaded.
 *
 * @mixin RevenueType
 */
class RevenueTypeForSelectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->chartAccount;
        $group = $account->parent;

        return [
            ...RevenueTypeRef::from($this->resource),
            'group' => ['code' => $group->code, 'name' => $group->name],
            'account' => ['code' => $account->code, 'name' => $account->name],
        ];
    }
}
