<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\Migrations\Realign\AbstractLegacyRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What the accounting targets share: child rows in the report, and the single
 * quiet save that carries the legacy `updated_at`.
 */
abstract class PurchaseDocumentTarget extends AbstractLegacyRealignTarget
{
    public function __construct(protected readonly PurchaseLegacyReferences $references) {}

    /**
     * One report field per column of each row: `{prefix}.{key}.{column}`.
     *
     * @param  iterable<Model>  $rows
     * @param  list<string>  $columns
     * @return array<string, string|int|float|bool|null>
     */
    protected function rowFields(iterable $rows, string $prefix, string $key, array $columns): array
    {
        $fields = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $fields["{$prefix}.{$row->getRawOriginal($key)}.{$column}"] = $row->getRawOriginal($column);
            }
        }

        return $fields;
    }

    protected function userName(mixed $userId): ?string
    {
        return $userId === null ? null : User::query()->whereKey($userId)->value('name');
    }

    /**
     * Saves quietly with the legacy `updated_at` when something differs; the mapping
     * warnings reach the report only together with a write.
     *
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     */
    protected function finish(Model $record, bool $childrenChanged, array $legacy, array $warnings, LegacyRealignNotes $notes): void
    {
        if (! $record->isDirty() && ! $childrenChanged) {
            return;
        }

        $updatedAt = $this->references->timestamp($legacy['updated_at'] ?? null);
        $record->forceFill($updatedAt !== null ? ['updated_at' => $updatedAt] : [])->saveQuietly();

        foreach ($warnings as $warning) {
            $notes->warn($warning);
        }
    }
}
