<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Subtitle of a document for-select item: "counterparty · Y-m-d", dropping
 * whichever part is missing (null when both are).
 */
final class ForSelectSubtitle
{
    private const string SEPARATOR = ' · ';

    public static function compose(?string $counterparty, ?CarbonInterface $date): ?string
    {
        $parts = array_filter([$counterparty, $date?->toDateString()], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(self::SEPARATOR, $parts);
    }
}
