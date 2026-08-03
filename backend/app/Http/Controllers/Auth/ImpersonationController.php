<?php

namespace App\Http\Controllers\Auth;

use App\DataObjects\Auth\LoginResult;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * "Login as customer" impersonation (spec 0050): start/stop a session that
 * reissues the Sanctum token as the TARGET user (D-1), plus the banner state
 * endpoint (D-5). Thin controller: Policy gate, Service call, envelope —
 * business invariants (D-2/D-3) live in ImpersonationService.
 *
 * @see ImpersonationService
 */
class ImpersonationController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ImpersonationService $impersonation) {}

    /**
     * POST /api/users/{user}/impersonate — start impersonating $user.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        try {
            $this->authorize('impersonate', $user);

            $result = $this->impersonation->start($request->user(), $user);

            return $this->ok($this->loginPayload($result), __('auth.impersonation_started'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/auth/stop-impersonation — end the current impersonation
     * session and restore the original actor's identity.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $result = $this->impersonation->stop($request->user());

            return $this->ok($this->loginPayload($result), __('auth.impersonation_stopped'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/auth/impersonation — banner state (D-5): the original actor
     * when the current token is an impersonation session, else null.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $original = $this->impersonation->impersonatorFor($request->user());

            return $this->ok([
                'impersonator' => $original === null ? null : [
                    'id' => $original->id,
                    'name' => $original->name,
                    'email' => $original->email,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `{ token, token_type, user }` shape shared by start and stop
     * (data contract, spec 0050) — same envelope AuthController::login uses,
     * INCLUDING its relation set: the client caches this `user` under the same
     * key as GET /auth/me, so a leaner variant here would silently drop the
     * `employment` Sede the create forms default from.
     *
     * @return array{token: string, token_type: string, user: UserResource}
     */
    private function loginPayload(LoginResult $result): array
    {
        return [
            'token' => $result->token,
            'token_type' => 'Bearer',
            'user' => new UserResource($result->user->loadMissing(AuthController::ME_RELATIONS)),
        ];
    }
}
