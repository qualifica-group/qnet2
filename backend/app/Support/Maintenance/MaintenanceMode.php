<?php

declare(strict_types=1);

namespace App\Support\Maintenance;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Env-driven maintenance (spec 0376): APP_MAINTENANCE_ENABLED and the user ids
 * in APP_MAINTENANCE_EXCLUDED_USER_IDS, read through config/app.php.
 */
final class MaintenanceMode
{
    public function isEnabled(): bool
    {
        return (bool) config('app.maintenance.enabled');
    }

    public function hasExclusions(): bool
    {
        return $this->excludedUserIds() !== [];
    }

    /** An impersonation token counts as the excluded user who started it (spec 0376, D-5). */
    public function isExcluded(User $user): bool
    {
        $actor_ids = array_filter([$user->id, $user->currentAccessToken()?->impersonated_by]);

        return array_intersect($actor_ids, $this->excludedUserIds()) !== [];
    }

    /**
     * Login stays reachable during maintenance so an excluded user can sign in:
     * everybody else is refused here, after the credentials check.
     *
     * @throws HttpResponseException
     */
    public function assertCanSignIn(User $user): void
    {
        if ($this->isEnabled() && ! $this->isExcluded($user)) {
            throw new HttpResponseException($this->unavailableResponse());
        }
    }

    public function unavailableResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('The site is under maintenance. Please try again later.'),
            'maintenance' => true,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /** @return list<int> */
    private function excludedUserIds(): array
    {
        return config('app.maintenance.excluded_user_ids', []);
    }
}
