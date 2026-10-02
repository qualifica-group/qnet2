<?php

namespace App\Migrations\Sources\Concerns;

use App\Enums\WorkOrderType;
use App\Migrations\Sources\WorkOrdersSource;
use Carbon\CarbonImmutable;

/**
 * Scalar field mapping of a legacy commessa (spec 0189, G-10): the mandatory
 * title, type and start date degrade to a safe value with a warning instead
 * of failing the row; the end date (no column) is appended to the notes; a
 * closed legacy status becomes a forced close with a legacy reason.
 *
 * @phpstan-require-extends WorkOrdersSource
 */
trait MapsLegacyWorkOrderFields
{
    private const int TITLE_MAX = 191;

    private const string FALLBACK_TITLE = 'Commessa %s';

    /** A start date before this year is a legacy placeholder, not a real date. */
    private const int MIN_PLAUSIBLE_YEAR = 2000;

    private const string END_DATE_NOTE = 'Data fine (legacy): %s';

    private const string END_DATE_FORMAT = 'd/m/Y';

    /**
     * Legacy closed status -> force-close reason (G-10); 1 and 2 stay open.
     *
     * @var array<int, string>
     */
    private const array FORCE_CLOSE_REASONS = [
        3 => 'Chiusa (legacy)',
        4 => 'Annullata (legacy)',
        5 => 'Disdetta (legacy)',
    ];

    private const array OPEN_STATUSES = [1, 2];

    /**
     * The title is mandatory: blank -> "Commessa {legacy id}", too long ->
     * truncated, each with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function legacyTitle(int|string $externalId, mixed $externalTitle, array &$warnings): string
    {
        $title = trim((string) ($externalTitle ?? ''));

        if ($title === '') {
            $warnings[] = 'Title missing; a placeholder title was used.';

            return sprintf(self::FALLBACK_TITLE, $externalId);
        }

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

    /**
     * The start date is mandatory: absent or implausible (a legacy
     * placeholder year) -> the legacy creation date, with a warning.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function startDate(array $record, array &$warnings): string
    {
        $startDate = $this->legacyTimestamp($record['start_date'] ?? null);

        if ($startDate !== null && $startDate->year >= self::MIN_PLAUSIBLE_YEAR) {
            return $startDate->toDateString();
        }

        $warnings[] = $startDate === null
            ? 'Start date missing; the creation date was used.'
            : "Start date {$startDate->toDateString()} is not plausible; the creation date was used.";

        return ($this->legacyTimestamp($record['created_at'] ?? null) ?? CarbonImmutable::now())->toDateString();
    }

    /**
     * The legacy notes; the end date has no column, so it is appended there.
     *
     * @param  array<string, mixed>  $record
     */
    private function internalNotes(array $record): ?string
    {
        $endDate = $this->legacyTimestamp($record['end_date'] ?? null);
        $parts = array_filter([
            $this->blankToNull($record['notes'] ?? null),
            $endDate === null ? null : sprintf(self::END_DATE_NOTE, $endDate->format(self::END_DATE_FORMAT)),
        ]);

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function forceCloseReason(mixed $externalStatus, array &$warnings): ?string
    {
        $status = (int) ($externalStatus ?? 0);

        if (isset(self::FORCE_CLOSE_REASONS[$status])) {
            return self::FORCE_CLOSE_REASONS[$status];
        }

        if (! in_array($status, self::OPEN_STATUSES, true)) {
            $warnings[] = "Unknown legacy status {$status}; the work order was left open.";
        }

        return null;
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
