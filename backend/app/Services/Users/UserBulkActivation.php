<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Authorization\AuthorizationRegistry;
use App\DataObjects\Users\UpdateUserData;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Activate or deactivate many users (spec 0310): each id goes through the same
 * UserService::update as the single toggle of the detail, in its own
 * transaction, so the termination rule (spec 0272 D-8), the business function
 * rule (spec 0275 D-4) and the activity log are identical. A refused id is
 * reported with its reason and never blocks the others (D-1).
 */
final class UserBulkActivation
{
    private const string FIELD = 'is_active';

    public function __construct(
        private readonly UserService $users,
        private readonly AuthorizationRegistry $authorization,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array{updated: list<int>, failed: list<array{id: int, name: string|null, message: string}>}
     */
    public function apply(User $actor, bool $isActive, array $ids): array
    {
        $targets = User::query()->whereKey($ids)->get()->keyBy('id');
        $updated = [];
        $failed = [];

        foreach ($ids as $id) {
            $target = $targets->get($id);
            $refusal = $target === null
                ? __('users.bulk_activation.not_found')
                : $this->refusal($actor, $target, $isActive);

            if ($refusal === null) {
                /** @var User $target */
                $refusal = $this->write($actor, $target, $isActive);
            }

            if ($refusal === null) {
                $updated[] = $id;
            } else {
                $failed[] = ['id' => $id, 'name' => $target?->name, 'message' => $refusal];
            }
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    /**
     * Why the actor may not change this user, checked before any write.
     */
    private function refusal(User $actor, User $target, bool $isActive): ?string
    {
        // D-2: never lock yourself out, same as self-delete.
        if (! $isActive && $actor->is($target)) {
            return __('users.bulk_activation.self');
        }

        $editable = Gate::forUser($actor)->allows('update', $target)
            && $this->authorization->resolve('users')->fieldPermissions($actor, $target)[self::FIELD]->editable;

        return $editable ? null : __('users.bulk_activation.forbidden');
    }

    /**
     * Apply the change through the single-user write path; returns the reason
     * when the user did not end up in the requested state.
     */
    private function write(User $actor, User $target, bool $isActive): ?string
    {
        try {
            $saved = $this->users->update($actor, $target, new UpdateUserData(is_active: $isActive));
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
        }

        // Spec 0272 D-8: a past termination date keeps the user inactive.
        return $saved->is_active === $isActive ? null : __('users.bulk_activation.terminated');
    }
}
