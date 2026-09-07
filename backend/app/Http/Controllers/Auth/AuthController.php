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
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Ogni payload dell'utente autenticato passa da qui: il client lo mette in
     * cache come utente corrente, quindi l'oggetto restituito da un PATCH di
     * profilo/avatar deve avere le stesse chiavi di quello di `me()`.
     *
     * `employment` serve al form di creazione richieste, che precompila la
     * Sede operativa con `employment.primary_operational_site_id` (direttiva
     * utente 2026-08-04). Fetta stretta invariata rispetto a prima della spec
     * 0103 — l'elenco delle appartenenze e' stato tolto (decisione utente
     * 2026-09-07: la riga di chip del picker Sede e' stata rimossa, nessun
     * consumer resta per `operational_sites`) — solo servita dalla pivot
     * (`EmploymentProfile::primaryOperationalSiteId()`) invece che dalla
     * colonna. Diversa dalla shape di EmploymentResource (usata da
     * GET /api/users/{user}, che espone anche le remote e i reference): per
     * questo si risolve UserResource e si sovrascrive `employment` invece di
     * riusarlo cosi' com'e' — resta comunque un Resource a monte, mai il
     * model raw.
     */
    private function authenticatedUserPayload(User $user): array
    {
        $resource = new UserResource($user->loadMissing('employment.operationalSites'));

        $payload = $this->withCustomFields($resource);
        $payload = $payload instanceof JsonResource ? $payload->resolve() : $payload;

        if ($user->employment !== null) {
            $payload['employment'] = ['primary_operational_site_id' => $user->employment->primary_operational_site_id];
        }

        return $payload;
    }

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
            'user' => $this->authenticatedUserPayload($result->user),
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
        $user = $request->user()
            ->loadMissing(['personalData.contacts', 'personalData.addresses']);

        return $this->ok($this->authenticatedUserPayload($user));
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

        return $this->ok($this->authenticatedUserPayload($user), __('auth.profile_updated'));
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

        return $this->ok($this->authenticatedUserPayload($user->refresh()), __('auth.avatar_updated'));
    }

    /**
     * Rimuove l'avatar dell'utente autenticato.
     */
    public function deleteAvatar(Request $request, AvatarService $avatars): JsonResponse
    {
        $user = $request->user();

        $avatars->remove($user);

        return $this->ok($this->authenticatedUserPayload($user->refresh()), __('auth.avatar_removed'));
    }
}
