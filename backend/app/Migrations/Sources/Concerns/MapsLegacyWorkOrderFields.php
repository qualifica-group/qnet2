<?php

namespace App\Migrations\Sources\Concerns;

use App\Enums\WorkOrderType;
use App\Migrations\Sources\WorkOrdersSource;
use RuntimeException;

/**
 * Scalar field mapping of a legacy commessa (spec 0189, G-10). The legacy
 * exporter sends the title, start date, internal notes and force-close reason
 * already decided; here the title is cut to the column and the type degrades
 * to a safe value with a warning instead of failing the row.
 *
 * @phpstan-require-extends WorkOrdersSource
 */
trait MapsLegacyWorkOrderFields
{
    private const int TITLE_MAX = 191;

    /**
     * Longer than the column -> truncated with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function legacyTitle(mixed $externalTitle, array &$warnings): string
    {
        $title = trim((string) ($externalTitle ?? ''));

        if (mb_strlen($title) > self::TITLE_MAX) {
            $warnings[] = 'Title truncated to '.self::TITLE_MAX.' characters.';

            return mb_substr($title, 0, self::TITLE_MAX);
        }

        return $title;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function legacyType(mixed $externalType, array &$warnings): WorkOrderType
    {
        $type = WorkOrderType::tryFrom((string) ($externalType ?? ''));

        if ($type === null) {
            $warnings[] = "Unknown type '{$externalType}'; defaulted to '".WorkOrderType::Processing->value."'.";
        }

        return $type ?? WorkOrderType::Processing;
    }

    private function startDate(mixed $externalStartDate): string
    {
        $startDate = $this->legacyTimestamp($externalStartDate)
            ?? throw new RuntimeException('Start date is required.');

        return $startDate->toDateString();
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
