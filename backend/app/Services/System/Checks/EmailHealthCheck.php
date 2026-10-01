<?php

declare(strict_types=1);

namespace App\Services\System\Checks;

use App\Enums\HealthStatusEnum;
use Throwable;

/**
 * Live email health check (spec 0187): resolves the Microsoft Graph mailer
 * transport without sending anything, and reports the configured default
 * mailer + presence (not value) of the Graph credentials.
 */
final class EmailHealthCheck extends AbstractHealthCheck
{
    /** Mailer drivers that never actually deliver mail. */
    private const array NON_DELIVERING_MAILERS = ['log', 'array'];

    public function key(): string
    {
        return 'email';
    }

    public function run(): array
    {
        $details = [
            $this->mailerDetail(),
            $this->transportDetail(),
            $this->credentialsDetail(),
        ];

        return [
            'status' => $this->statusFromDetails($details),
            'latency_ms' => null,
            'message' => null,
            'details' => $details,
        ];
    }

    private function mailerDetail(): array
    {
        $mailer = (string) config('mail.default');
        $status = in_array($mailer, self::NON_DELIVERING_MAILERS, true) ? HealthStatusEnum::Degraded : HealthStatusEnum::Ok;

        return $this->detail('mailer', $status, $mailer);
    }

    private function transportDetail(): array
    {
        try {
            app('mail.manager')->mailer('microsoft-graph')->getSymfonyTransport();

            return $this->detail('transport', HealthStatusEnum::Ok, 'microsoft-graph');
        } catch (Throwable) {
            return $this->detail('transport', HealthStatusEnum::Down, null, __('system_health.email.transport_unavailable'));
        }
    }

    private function credentialsDetail(): array
    {
        $present = GraphCredentials::present();

        return $this->detail(
            'credentials',
            $present ? HealthStatusEnum::Ok : HealthStatusEnum::Degraded,
            $present ? 'present' : 'missing',
        );
    }
}
