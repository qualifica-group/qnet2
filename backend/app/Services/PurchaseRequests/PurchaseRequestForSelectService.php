<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Services\Shared\AbstractDocumentForSelectService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * GET /api/purchase-requests/for-select (ADR 0011, spec 0347). Rows are
 * restricted by PurchaseRequestVisibilityScope exactly like the index (D-16).
 */
final class PurchaseRequestForSelectService extends AbstractDocumentForSelectService
{
    protected function baseQuery(): Builder
    {
        return PurchaseRequestVisibilityScope::scopeToActor(
            PurchaseRequest::query()
                ->select(['purchase_requests.id', 'purchase_requests.subject', 'purchase_requests.requested_at', 'purchase_requests.requester_id'])
                ->with('requester:id,name'),
            Auth::user(),
        );
    }

    protected function applySearch(Builder $query, string $term): void
    {
        $query->where('purchase_requests.subject', 'like', '%'.$term.'%')
            ->orWhereHas('requester', fn (Builder $requester) => $requester->where('name', 'like', '%'.$term.'%'));
    }

    protected function applyOrder(Builder $query): Builder
    {
        return $query->orderByDesc('purchase_requests.requested_at')->orderByDesc('purchase_requests.id');
    }

    protected function keyColumn(): string
    {
        return 'purchase_requests.id';
    }
}
