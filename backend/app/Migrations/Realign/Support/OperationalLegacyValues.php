<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\Models\User;
use App\Support\ManagerPositions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Reading the legacy values of the operational targets (spec 0375). A person who
 * is not migrated fails the record: nobody is dropped silently (AC-019).
 */
trait OperationalLegacyValues
{
    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /** Whether the legacy really sent a reference (null, '' and 0 mean none). */
    private function isSent(mixed $reference): bool
    {
        return $reference !== null && $reference !== '' && (int) $reference !== 0;
    }

    /**
     * @throws RuntimeException when the person is sent but not migrated
     */
    private function requiredUserId(mixed $legacyUserId, string $role): ?int
    {
        if (! $this->isSent($legacyUserId)) {
            return null;
        }

        $userId = User::query()->where('old_id', $legacyUserId)->value('id');

        return $userId === null
            ? throw new RuntimeException("{$role} (legacy user id {$legacyUserId}) not migrated; the record is not aligned.")
            : (int) $userId;
    }

    /**
     * @return array<int, int>
     */
    private function requiredUserIds(mixed $legacyUserIds, string $role): array
    {
        $ids = [];

        foreach ((array) ($legacyUserIds ?? []) as $legacyUserId) {
            $id = $this->requiredUserId($legacyUserId, $role);

            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Ordered manager slots as the import builds them: a repeated person leaves the slot empty.
     *
     * @param  array<int, string>  $warnings
     * @return array<int, int|null>
     */
    private function requiredManagerSlots(mixed $legacyUserIds, array &$warnings): array
    {
        $legacyUserIds = array_values((array) ($legacyUserIds ?? []));

        if (count($legacyUserIds) > ManagerPositions::MAX) {
            $warnings[] = 'More than '.ManagerPositions::MAX.' managers in the legacy, the extra ones were dropped.';
            $legacyUserIds = array_slice($legacyUserIds, 0, ManagerPositions::MAX);
        }

        $slots = [];

        foreach ($legacyUserIds as $legacyUserId) {
            $userId = $this->requiredUserId($legacyUserId, 'Manager');
            $slots[] = in_array($userId, $slots, true) ? null : $userId;
        }

        return $slots;
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function legacyUpdatedAt(array $legacy): ?CarbonImmutable
    {
        foreach (['updated_at', 'created_at'] as $key) {
            $value = $this->blankToNull($legacy[$key] ?? null);

            if ($value === null) {
                continue;
            }

            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Writes the record quietly with the legacy `updated_at` (D-10).
     *
     * @param  array<string, mixed>  $legacy
     */
    private function saveWithLegacyTimestamp(Model $record, array $legacy): void
    {
        $updatedAt = $this->legacyUpdatedAt($legacy);

        $record->forceFill($updatedAt !== null ? ['updated_at' => $updatedAt] : [])->saveQuietly();
    }

    /**
     * MySQL returns `09:00:00` and the legacy sends `09:00`: an equal time keeps the stored form,
     * otherwise every row would look changed.
     */
    private function legacyTime(Model $record, string $column, mixed $legacyTime): ?string
    {
        $time = $this->blankToNull($legacyTime);
        $current = $record->getRawOriginal($column);

        return $time !== null && is_string($current) && $this->shortTime($current) === $this->shortTime($time) ? $current : $time;
    }

    private function shortTime(mixed $time): ?string
    {
        return is_string($time) ? substr($time, 0, 5) : null;
    }

    private function shortDate(mixed $date): ?string
    {
        return is_string($date) ? substr($date, 0, 10) : null;
    }

    /**
     * @param  array<int, int>  $first
     * @param  array<int, int>  $second
     */
    private function sameIds(array $first, array $second): bool
    {
        sort($first);
        sort($second);

        return $first === $second;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function capped(mixed $value, int $max, string $label, array &$warnings): ?string
    {
        $text = $this->blankToNull($value);

        if ($text !== null && mb_strlen($text) > $max) {
            $warnings[] = "{$label} truncated to {$max} characters.";

            return mb_substr($text, 0, $max);
        }

        return $text;
    }
}
