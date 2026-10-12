<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Row locks shared by the all-or-nothing line operations (status change, supplier
 * assignment): lines first, then their requests, always in id order.
 */
class PurchaseRequestLineLocker
{
    /**
     * @param  array<int, int>  $lineIds
     * @return Collection<int, PurchaseRequestLine> keyed by id
     */
    public function lines(array $lineIds): Collection
    {
        return PurchaseRequestLine::query()->whereIn('id', $lineIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, PurchaseRequestLine>  $lines
     * @return Collection<int, PurchaseRequest> keyed by id
     */
    public function requests(Collection $lines): Collection
    {
        return PurchaseRequest::query()
            ->whereIn('id', $lines->pluck('purchase_request_id')->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }
}
