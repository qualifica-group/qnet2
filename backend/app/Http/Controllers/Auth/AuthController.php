<?php

namespace App\Http\Controllers\Auth;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AbilitiesService;
use App\Services\AuthService;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    /**
     * Relations the authenticated-user payload carries: the personal-data card
     * the profile screen renders, plus the employment Sede (user directive
     * 2026-08-03) a create form defaults its "Sede operativa"/"Operatore"
     * from. The Sede path matches UserService's for-select eager load — the
     * label EmploymentResource composes reads the site's primary address.
     *
     * Public because ImpersonationController returns the very same `user`
     * payload under the same client-side cache key.
     *
     * @var array<int, string>
     */
    public const array ME_RELATIONS = [
        'personalData.contacts',
        'personalData.addresses',
        'employment.operationalSite.addresses.city',
    ];

    public function __construct(private readonly AuthService $authService) {}

    /**
     * Autenticazione: emette un token di accesso.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->login(
            $validated['email'],
            $validated['password'],
            $request->deviceName(),
        );

        return $this->ok([
            'token' => $result->token,
            'token_type' => 'Bearer',
            'user' => $this->authenticatedUser($result->user),
        ], 'Authenticated.');
    }

    /**
     * Revoca il token corrente.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->ok(null, __('auth.logged_out'));
    }

    /**
     * Invia il link di reset password.
     *
     * Risposta volutamente generica: non rivela se l'email è registrata.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->sendPasswordResetLink($request->validated()['email']);

        if ($status === Password::RESET_THROTTLED) {
            return $this->fail(__($status), HttpStatusEnum::TOO_MANY_REQUESTS->value);
        }

        return $this->ok(null, __('passwords.sent'));
    }

    /**
     * Reimposta la password a partire da un token valido.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->ok(null, __('passwords.reset'));
    }

    /**
     * Ruota il token corrente.
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $this->authService->refresh($request->user());

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed.');
    }

    /**
     * Utente attualmente autenticato.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->authenticatedUser($request->user()));
    }

    /**
     * Ability map (ruoli + permessi) dell'utente autenticato.
     */
    public function abilities(Request $request, AbilitiesService $abilities): JsonResponse
    {
        return $this->ok($abilities->for($request->user()));
    }

    /**
     * Aggiorna il profilo dell'utente autenticato.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile(
            $request->user(),
            $request->accountAttributes(),
            $request->toProfile(),
            $request->moduleOpenPreferences(),
        );

        return $this->ok($this->authenticatedUser($user), __('auth.profile_updated'));
    }

    /**
     * Cambia la password dell'utente autenticato.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->validated()['password']);

        return $this->ok(null, __('auth.password_updated'));
    }

    /**
     * Carica (o sostituisce) l'avatar dell'utente autenticato.
     */
    public function uploadAvatar(UploadAvatarRequest $request, AvatarService $avatars): JsonResponse
    {
        $user = $request->user();

        $avatars->set($user, $request->avatarFile());

        return $this->ok($this->authenticatedUser($user->refresh()), __('auth.avatar_updated'));
    }

    /**
     * Rimuove l'avatar dell'utente autenticato.
     */
    public function deleteAvatar(Request $request, AvatarService $avatars): JsonResponse
    {
        $user = $request->user();

        $avatars->remove($user);

        return $this->ok($this->authenticatedUser($user->refresh()), __('auth.avatar_removed'));
    }

    /**
     * Every payload that carries the AUTHENTICATED user goes through here, so
     * they all describe the same account: the client caches this single shape
     * under one key, and a partially-loaded variant returned by one endpoint
     * would silently drop keys the others provide (spec 0015 `employment`
     * included, which the request create form defaults its Sede from).
     */
    private function authenticatedUser(User $user): UserResource
    {
        return new UserResource($user->loadMissing(self::ME_RELATIONS));
    }
}
