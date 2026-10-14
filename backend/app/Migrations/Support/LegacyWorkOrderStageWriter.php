<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Writes the legacy phases of a freshly imported commessa (spec 0189, G-22):
 * one WorkOrderStage per legacy stage, in the order received (the legacy
 * already merged them by title and decided whether each is closed). A closed
 * stage keeps its legacy closing date and, when migrated, its closing user.
 * No task is generated and nothing is logged: the caller runs without
 * activity log and the stage is written quietly.
 */
final class LegacyWorkOrderStageWriter
{
    private const int NAME_MAX = 191;

    /**
     * @param  array<int, mixed>  $legacyStages  [{name, closed_at, closed_by_user_id}]
     * @param  array<int, string>  $warnings
     */
    public function write(WorkOrder $workOrder, array $legacyStages, array &$warnings): void
    {
        $sortOrder = 0;

        foreach ($legacyStages as $legacyStage) {
            $legacyStage = (array) $legacyStage;
            $name = $this->name($legacyStage['name'] ?? null, $warnings);

            if ($name === null) {
                continue;
            }

            // Step 1: the stage, in the legacy order.
            $stage = $workOrder->stages()->create(['name' => $name, 'sort_order' => $sortOrder++]);

            // Step 2: its closing, when the legacy closed it.
            $closedAt = $this->closedAt($legacyStage['closed_at'] ?? null);

            if ($closedAt !== null) {
                $stage->forceFill([
                    'closed_at' => $closedAt,
                    'closed_by_id' => $this->closer($name, $legacyStage['closed_by_user_id'] ?? null, $warnings),
                ])->saveQuietly();
            }
        }
    }

    /**
     * Blank -> no stage; too long -> truncated with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function name(mixed $legacyName, array &$warnings): ?string
    {
        $name = trim((string) ($legacyName ?? ''));

        if ($name === '') {
            return null;
        }

        if (mb_strlen($name) > self::NAME_MAX) {
            $warnings[] = 'Stage name truncated to '.self::NAME_MAX." characters: {$name}.";

            return mb_substr($name, 0, self::NAME_MAX);
        }

        return $name;
    }

    private function closedAt(mixed $legacyClosedAt): ?CarbonImmutable
    {
        $value = trim((string) ($legacyClosedAt ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function closer(string $stageName, mixed $legacyUserId, array &$warnings): ?int
    {
        if ($legacyUserId === null || $legacyUserId === '') {
            return null;
        }

        $userId = User::query()->where('old_id', $legacyUserId)->value('id');

        if ($userId === null) {
            $warnings[] = "Stage {$stageName}: closing user (legacy id {$legacyUserId}) not migrated; closed without author.";
        }

        return $userId;
    }
}
