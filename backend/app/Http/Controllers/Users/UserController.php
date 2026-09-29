<?php

namespace App\Http\Controllers\Users;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\Users\UserOnboardingService;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CRUD endpoints for the Users resource, backing the backend-driven table
 * row-actions (view / edit / delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (UserPolicy), Service call, response. No business logic, no queries.
 *
 * Authorization is re-enforced server-side on every action because these routes
 * are hit by frontend row-actions, which are NOT the source of truth.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned user.
 *
 * @see UserService
 */
class UserController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserService $service,
        private readonly UserOnboardingService $onboarding,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/users/{user} — single user (view row-action).
     */
    public function show(Request $request, User $user): JsonResponse
    {
        try {
            $this->authorize('view', $user);

            return $this->okWithPermissions(
                new UserResource($this->service->loadProfileTree($user)),
                $this->buildPermissions($request->user(), $user),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/users — create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', User::class);

            $data = $request->toData();
            $user = $this->service->create($request->user(), $data, $request->toProfile(), $request->toEmployment());

            $this->startOnboarding($user, $data->password !== null);

            return $this->okWithPermissions(
                new UserResource($user),
                $this->buildPermissions($request->user(), $user),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/users/{user} — update an existing user (edit row-action).
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $user = $this->service->update($request->user(), $user, $request->toData(), $request->toProfile(), $request->toEmployment());

            return $this->okWithPermissions(new UserResource($user), $this->buildPermissions($request->user(), $user));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/users/{user}/resend-welcome — resend the invite email while the
     * user has not completed the first access (spec 0177).
     */
    public function resendWelcome(User $user): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $this->onboarding->resendWelcome($user);

            return $this->ok(null, __('auth.welcome_resent'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * DELETE /api/users/{user} — delete a user (delete row-action).
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $this->authorize('delete', $user);

            $this->service->delete($user);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/users/{user}/avatar — upload/replace a user's avatar (admin).
     *
     * Gated by the same `users.update` ability as editing the user: managing a
     * user's avatar is part of managing the user.
     */
    public function uploadAvatar(UploadAvatarRequest $request, User $user, AvatarService $avatars): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $avatars->set($user, $request->avatarFile());

            return $this->ok(new UserResource($user->refresh()), __('auth.avatar_updated'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * DELETE /api/users/{user}/avatar — remove a user's avatar (admin).
     */
    public function deleteAvatar(User $user, AvatarService $avatars): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $avatars->remove($user);

            return $this->ok(new UserResource($user->refresh()), __('auth.avatar_removed'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?User $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('users'), $actor, $model);
    }

    /**
     * The user is already committed: a failing email dispatch must not turn the
     * 201 into an error (the admin can resend). Only ids/class are logged, never
     * the token or exception payload.
     */
    private function startOnboarding(User $user, bool $temporaryPassword): void
    {
        try {
            $this->onboarding->start($user, $temporaryPassword);
        } catch (Throwable $exception) {
            Log::warning('Welcome email dispatch failed after user creation.', [
                'user' => $user->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
