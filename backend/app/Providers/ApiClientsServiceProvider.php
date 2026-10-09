<?php

namespace App\Providers;

use App\Http\Middleware\ThrottleApiClientRequests;
use App\Models\ApiClient;
use App\Services\ApiClients\ApiClientTokenValidity;
use App\Support\ApiClients\ApiClientRequestContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/**
 * Wiring of the API clients (specs 0209, 0210): token validity, per-client
 * rate limit and activity log tagging. Kept out of AppServiceProvider.
 */
class ApiClientsServiceProvider extends ServiceProvider
{
    private const string ACTIVITY_PROPERTY = 'api_client_id';

    public function boot(): void
    {
        // ApiClient is an audited subject: enforceMorphMap (AppServiceProvider)
        // rejects any class missing from the map.
        Relation::morphMap(['api_client' => ApiClient::class]);

        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $this->app
                ->make(ApiClientTokenValidity::class)
                ->isValid($token, $isValid),
        );

        RateLimiter::for(ThrottleApiClientRequests::LIMITER, $this->limitFor(...));

        // The causer stays the acting user; the client the request went
        // through is recorded next to it.
        Activity::creating(function (Activity $activity): void {
            $client = ApiClientRequestContext::current(request());

            if ($client !== null) {
                $activity->properties = $activity->properties->put(self::ACTIVITY_PROPERTY, $client->id);
            }
        });
    }

    private function limitFor(Request $request): Limit
    {
        $client = ApiClientRequestContext::resolve($request);

        return Limit::perMinute($client?->effectiveRateLimitPerMinute() ?? (int) config('external-api.rate_limit.default'))
            ->by(ThrottleApiClientRequests::LIMITER.':'.$client?->getKey())
            ->response(fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => __('Too many requests.'),
            ], 429, $headers));
    }
}
