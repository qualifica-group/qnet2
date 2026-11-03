<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Services\Shared\AbstractDocumentForSelectService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * GET /api/contracts/for-select (ADR 0011, spec 0347). Rows are restricted by
 * ContractVisibilityScope exactly like the index (spec 0316, D-3). A contract
 * has no code of its own: it is identified by its quote's code and title.
 */
final class ContractForSelectService extends AbstractDocumentForSelectService
{
    protected function baseQuery(): Builder
    {
        return ContractVisibilityScope::scopeToActor(
            Contract::query()
                ->select(['contracts.id', 'contracts.quote_id', 'contracts.accepted_at'])
                ->with(['quote:id,code,title,opportunity_id', 'quote.opportunity:id,registry_id', 'quote.opportunity.registry:id,name']),
            Auth::user(),
        );
    }

    protected function applySearch(Builder $query, string $term): void
    {
        $query->whereHas('quote', fn (Builder $quote) => $quote
            ->where('code', 'like', '%'.$term.'%')
            ->orWhere('title', 'like', '%'.$term.'%'));
    }

    protected function applyOrder(Builder $query): Builder
    {
        return $query->orderByDesc('contracts.accepted_at')->orderByDesc('contracts.id');
    }

    protected function keyColumn(): string
    {
        return 'contracts.id';
    }
}
