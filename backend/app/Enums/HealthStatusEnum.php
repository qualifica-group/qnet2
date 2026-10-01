<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Health status of a system-health check/detail (spec 0187). Severity order
 * (worst to best): Down > Degraded > Ok.
 */
enum HealthStatusEnum: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Down = 'down';

    /**
     * Aggregate many statuses into the single worst one (down > degraded > ok).
     * Used both for a check's status (worst of its details) and for the
     * overall status (worst of the 4 checks).
     */
    public static function worst(self ...$statuses): self
    {
        if ($statuses === []) {
            return self::Ok;
        }

        return array_reduce(
            $statuses,
            static fn (self $carry, self $status): self => $status->priority() > $carry->priority() ? $status : $carry,
            self::Ok,
        );
    }

    private function priority(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Degraded => 1,
            self::Down => 2,
        };
    }
}
