<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notes\PermissionGatedNotable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The `purchase-requests` notable_types descriptor (spec 0312, D-2): the notes
 * thread of an RDA, readable under the same two conditions as
 * PurchaseRequestPolicy::view — `purchase-requests.view` AND the visibility
 * scope, delegated to PurchaseRequestVisibilityScope on the record side.
 */
final class PurchaseRequestNotable extends PermissionGatedNotable
{
    public function modelClass(): string
    {
        return PurchaseRequest::class;
    }

    public function label(Model $record): string
    {
        /** @var PurchaseRequest $record */
        return "RDA {$record->getKey()} — {$record->subject}";
    }

    protected function viewPermission(): string
    {
        return 'purchase-requests.view';
    }

    protected function detailPath(Model $record): string
    {
        return '/purchase-requests/'.$record->getKey();
    }

    protected function isVisibleTo(User $user, Model $record): bool
    {
        return $record instanceof PurchaseRequest && PurchaseRequestVisibilityScope::isVisibleTo($user, $record);
    }

    /**
     * isVisibleTo() over the permission holders: `viewAll`, or one of the
     * requester, the function manager and the author.
     */
    protected function narrowReaders(Builder $holders, Model $record): void
    {
        /** @var PurchaseRequest $record */
        $linkedIds = array_values(array_filter([$record->requester_id, $record->function_manager_id, $record->created_by]));
        $viewAllExists = $this->permissionExists(PurchaseRequestVisibilityScope::VIEW_ALL_PERMISSION);

        $holders->where(function (Builder $access) use ($linkedIds, $viewAllExists): void {
            $access->whereIn('id', $linkedIds)
                ->when($viewAllExists, fn (Builder $tiers) => $tiers->orWhere(
                    fn (Builder $viewAll) => $viewAll->permission(PurchaseRequestVisibilityScope::VIEW_ALL_PERMISSION),
                ));
        });
    }
}
