<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * THE single place that decides who may approve a purchase request (spec 0245,
 * D-1/D-2/D-5): the active direct superiors of the requester; when the requester
 * has none, the manager of the chosen business function, provided active and not
 * the requester. Used by the write validation and by the approver-options endpoint.
 */
final class PurchaseRequestApproverResolver
{
    public const string SOURCE_REPORTS_TO = 'reports_to';

    public const string SOURCE_BUSINESS_FUNCTION = 'business_function';

    public const string SOURCE_NONE = 'none';

    /**
     * @return array{source: string, approvers: Collection<int, User>}
     */
    public function resolve(User $requester, ?BusinessFunction $function): array
    {
        // Step 1: direct superiors of the requester
        $superiors = $this->superiorsOf($requester);

        if ($superiors->isNotEmpty()) {
            return ['source' => self::SOURCE_REPORTS_TO, 'approvers' => $superiors];
        }

        // Step 2: fallback to the manager of the business function
        $manager = $function?->manager_id !== null ? User::query()->find($function->manager_id) : null;

        if ($manager instanceof User && $manager->is_active && (int) $manager->id !== (int) $requester->id) {
            return ['source' => self::SOURCE_BUSINESS_FUNCTION, 'approvers' => collect([$manager])];
        }

        return ['source' => self::SOURCE_NONE, 'approvers' => collect()];
    }

    public function allows(int $approverId, User $requester, ?BusinessFunction $function): bool
    {
        return $this->resolve($requester, $function)['approvers']
            ->contains(static fn (User $approver): bool => (int) $approver->id === $approverId);
    }

    /**
     * @return Collection<int, User>
     */
    private function superiorsOf(User $requester): Collection
    {
        $profile = EmploymentProfile::query()->where('user_id', $requester->id)->first();

        if ($profile === null) {
            return collect();
        }

        return $profile->reportsTo()->where('users.is_active', true)->get()->values();
    }
}
