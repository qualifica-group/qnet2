<?php

namespace App\Migrations\Sources\Concerns;

use App\Migrations\AbstractMigrationSource;
use App\Models\User;
use App\Support\ManagerPositions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Helpers shared by the legacy operational-record sources (spec 0189:
 * RegistriesSource, OpportunitiesSource, QuotesSource, WorkOrdersSource): legacy-id remaps that degrade to a
 * warning, the ordered manager slots, the read-only preview cells and the
 * final write of `old_id` + legacy timestamps.
 *
 * @phpstan-require-extends AbstractMigrationSource
 */
trait MapsLegacyOperationalRecord
{
    /**
     * Remap one legacy reference via `old_id`. Absent/blank/0 -> null without
     * a warning (the contract sends 0 and orphans as null, G-1); a reference
     * to a record not migrated -> null with a warning (AC-003).
     *
     * @param  class-string<Model>  $targetClass
     * @param  array<int, string>  $warnings
     */
    private function remapLegacyId(string $targetClass, mixed $externalRef, string $field, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '' || (int) $externalRef === 0) {
            return null;
        }

        $id = $this->resolveOldId($targetClass, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved {$field} (legacy id {$externalRef}).";
        }

        return $id;
    }

    /**
     * Remap a list of legacy references, distinct, unresolved ones dropped
     * with a warning each.
     *
     * @param  class-string<Model>  $targetClass
     * @param  array<int, string>  $warnings
     * @return array<int, int>
     */
    private function remapLegacyIds(string $targetClass, mixed $externalRefs, string $field, array &$warnings): array
    {
        $ids = [];

        foreach ((array) ($externalRefs ?? []) as $externalRef) {
            $id = $this->remapLegacyId($targetClass, $externalRef, $field, $warnings);

            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * The legacy manager users as ordered "G.A. n" slots (contract: order =
     * position). An unresolved or repeated user leaves its slot empty so the
     * following managers keep their legacy position; slots beyond
     * ManagerPositions::MAX are dropped with a warning. `$field` names the
     * legacy list in the warnings (WorkOrdersSource reuses this for its
     * participants).
     *
     * @param  array<int, string>  $warnings
     * @return array<int, int|null>
     */
    private function legacyManagerSlots(mixed $externalUserIds, array &$warnings, string $field = 'manager_user_ids'): array
    {
        $externalUserIds = array_values((array) ($externalUserIds ?? []));

        if (count($externalUserIds) > ManagerPositions::MAX) {
            $warnings[] = 'More than '.ManagerPositions::MAX." {$field}, the extra ones were dropped.";
            $externalUserIds = array_slice($externalUserIds, 0, ManagerPositions::MAX);
        }

        $slots = [];

        foreach ($externalUserIds as $externalUserId) {
            $userId = $this->remapLegacyId(User::class, $externalUserId, $field, $warnings);
            $slots[] = in_array($userId, $slots, true) ? null : $userId;
        }

        return $slots;
    }

    /**
     * Last write of an imported row: `old_id` (never mass-assignable), any
     * post-create attribute, and the legacy `created_at`/`updated_at` (a
     * missing `updated_at` falls back to `created_at`). Quiet save: the record
     * already exists, no model event has anything left to do.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $attributes
     */
    private function finalizeLegacyRecord(Model $model, int|string $externalId, array $record, array $attributes = []): void
    {
        $createdAt = $this->legacyTimestamp($record['created_at'] ?? null);
        $updatedAt = $this->legacyTimestamp($record['updated_at'] ?? null) ?? $createdAt;

        $model->forceFill([
            ...$attributes,
            'old_id' => $externalId,
            ...($createdAt !== null ? ['created_at' => $createdAt] : []),
            ...($updatedAt !== null ? ['updated_at' => $updatedAt] : []),
        ])->saveQuietly();
    }

    /**
     * A legacy datetime, null when blank, zeroed (`0000-00-00`) or unparsable.
     */
    private function legacyTimestamp(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read-only preview cells: one per native column; a list is shown as its
     * comma-separated values, a list of objects as its item count.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    private function legacyPreviewCells(array $record): array
    {
        $row = [];

        foreach ($this->nativeColumns() as $column) {
            $value = $record[$column['id']] ?? null;

            if (is_array($value)) {
                $value = array_filter($value, is_array(...)) !== []
                    ? count($value)
                    : implode(', ', $value);
            }

            $row[$column['id']] = $value;
        }

        return $row;
    }
}
