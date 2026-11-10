<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
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

    private const string KEPT_STAGE_SUFFIX = ' is no longer in the legacy; kept.';

    /**
     * The "stage kept" conflicts among a row's warnings (spec 0372, D-10).
     *
     * @param  array<int, string>  $warnings
     * @return array<int, string>
     */
    public static function keptStageWarnings(array $warnings): array
    {
        return array_values(array_filter(
            $warnings,
            static fn (string $warning): bool => str_ends_with($warning, self::KEPT_STAGE_SUFFIX),
        ));
    }

    /**
     * @param  array<int, mixed>  $legacyStages  [{name, closed_at, closed_by_user_id}]
     * @param  array<int, string>  $warnings
     */
    public function write(WorkOrder $workOrder, array $legacyStages, array &$warnings): void
    {
        foreach ($this->resolve($legacyStages, $warnings) as $sortOrder => $legacyStage) {
            $this->create($workOrder, $sortOrder, $legacyStage);
        }
    }

    /**
     * Realigns the stages of an already imported commessa with the legacy (spec 0372,
     * D-10): matched by name, a missing stage is created, a present one gets
     * `sort_order`, `closed_at` and `closed_by_id` only when they differ (a null
     * `closed_at` reopens it). Never deletes: `time_entries.work_order_stage_id`
     * references a stage, so one the legacy no longer sends stays, with a warning.
     *
     * @param  array<int, mixed>  $legacyStages  [{name, closed_at, closed_by_user_id}]
     * @param  array<int, string>  $warnings
     * @return bool true only when something was written
     */
    public function sync(WorkOrder $workOrder, array $legacyStages, array &$warnings): bool
    {
        $existing = $workOrder->stages()->get()->keyBy('name');
        $written = false;

        $resolved = $this->resolve($legacyStages, $warnings);

        // Step 1: create or realign every stage the legacy sends.
        foreach ($resolved as $sortOrder => $legacyStage) {
            $stage = $existing->get($legacyStage['name']);

            if ($stage === null) {
                $existing->put($legacyStage['name'], $this->create($workOrder, $sortOrder, $legacyStage));
                $written = true;

                continue;
            }

            $written = $this->realign($stage, $sortOrder, $legacyStage) || $written;
        }

        // Step 2: keep, and report, the stages the legacy no longer sends.
        $sent = array_column($resolved, 'name');

        foreach ($existing->keys()->diff($sent) as $name) {
            $warnings[] = 'Stage '.$name.self::KEPT_STAGE_SUFFIX;
        }

        return $written;
    }

    /**
     * The legacy stages in the order received, blank ones dropped, with the closing
     * resolved once for both write() and sync().
     *
     * @param  array<int, mixed>  $legacyStages
     * @param  array<int, string>  $warnings
     * @return list<array{name: string, closed_at: ?CarbonImmutable, closed_by_id: ?int}>
     */
    private function resolve(array $legacyStages, array &$warnings): array
    {
        $resolved = [];

        foreach ($legacyStages as $legacyStage) {
            $legacyStage = (array) $legacyStage;
            $name = $this->name($legacyStage['name'] ?? null, $warnings);

            if ($name === null) {
                continue;
            }

            $closedAt = $this->closedAt($legacyStage['closed_at'] ?? null);

            $resolved[] = [
                'name' => $name,
                'closed_at' => $closedAt,
                'closed_by_id' => $closedAt === null ? null : $this->closer($name, $legacyStage['closed_by_user_id'] ?? null, $warnings),
            ];
        }

        return $resolved;
    }

    /**
     * @param  array{name: string, closed_at: ?CarbonImmutable, closed_by_id: ?int}  $legacyStage
     */
    private function create(WorkOrder $workOrder, int $sortOrder, array $legacyStage): WorkOrderStage
    {
        $stage = $workOrder->stages()->create(['name' => $legacyStage['name'], 'sort_order' => $sortOrder]);

        if ($legacyStage['closed_at'] !== null) {
            $stage->forceFill([
                'closed_at' => $legacyStage['closed_at'],
                'closed_by_id' => $legacyStage['closed_by_id'],
            ])->saveQuietly();
        }

        return $stage;
    }

    /**
     * @param  array{name: string, closed_at: ?CarbonImmutable, closed_by_id: ?int}  $legacyStage
     */
    private function realign(WorkOrderStage $stage, int $sortOrder, array $legacyStage): bool
    {
        $stage->forceFill([
            'sort_order' => $sortOrder,
            'closed_at' => $legacyStage['closed_at'],
            'closed_by_id' => $legacyStage['closed_by_id'],
        ]);

        if (! $stage->isDirty()) {
            return false;
        }

        $stage->saveQuietly();

        return true;
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
