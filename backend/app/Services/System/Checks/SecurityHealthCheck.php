<?php

declare(strict_types=1);

namespace App\Services\System\Checks;

use App\Enums\HealthStatusEnum;

/**
 * Security hardening health check (spec 0187): app/env, Sanctum, CORS,
 * secrets presence and audit config. Config-only — no live calls, no
 * secret values ever leave this class (presence booleans only).
 */
final class SecurityHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'security';
    }

    public function run(): array
    {
        $details = [
            $this->appDebugDetail(),
            $this->detail('app_env', HealthStatusEnum::Ok, (string) config('app.env')),
            $this->httpsDetail(),
            $this->sanctumExpirationDetail(),
            $this->corsDetail(),
            $this->appKeyDetail(),
            $this->mailSecretsDetail(),
            $this->activitylogDetail(),
        ];

        return [
            'status' => $this->statusFromDetails($details),
            'latency_ms' => null,
            'message' => null,
            'details' => $details,
        ];
    }

    private function appDebugDetail(): array
    {
        $debug = (bool) config('app.debug');

        $status = match (true) {
            ! $debug => HealthStatusEnum::Ok,
            config('app.env') === 'production' => HealthStatusEnum::Down,
            default => HealthStatusEnum::Degraded,
        };

        return $this->detail('app_debug', $status, $debug ? 'true' : 'false');
    }

    private function httpsDetail(): array
    {
        $isHttps = str_starts_with((string) config('app.url'), 'https');

        return $this->detail('https', $isHttps ? HealthStatusEnum::Ok : HealthStatusEnum::Degraded);
    }

    private function sanctumExpirationDetail(): array
    {
        $ok = config('sanctum.expiration') !== null;

        return $this->detail('sanctum_expiration', $ok ? HealthStatusEnum::Ok : HealthStatusEnum::Degraded);
    }

    private function corsDetail(): array
    {
        $origins = (array) config('cors.allowed_origins');
        $hasWildcard = in_array('*', $origins, true);
        $credentials = (bool) config('cors.supports_credentials');

        $status = match (true) {
            $hasWildcard && $credentials => HealthStatusEnum::Down,
            ! $hasWildcard && $origins !== [] => HealthStatusEnum::Ok,
            default => HealthStatusEnum::Degraded,
        };

        return $this->detail('cors', $status);
    }

    private function appKeyDetail(): array
    {
        return $this->detail('app_key', filled(config('app.key')) ? HealthStatusEnum::Ok : HealthStatusEnum::Down);
    }

    private function mailSecretsDetail(): array
    {
        $present = GraphCredentials::present();

        return $this->detail(
            'mail_secrets',
            $present ? HealthStatusEnum::Ok : HealthStatusEnum::Degraded,
            $present ? 'complete' : 'partial',
        );
    }

    private function activitylogDetail(): array
    {
        return $this->detail('activitylog', config('activitylog.enabled') ? HealthStatusEnum::Ok : HealthStatusEnum::Degraded);
    }
}
