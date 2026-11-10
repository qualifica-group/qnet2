<?php

namespace App\Services;

use App\Models\AbsenceType;
use App\Services\Deletion\InUseGuard;
use Illuminate\Support\Collection;

/**
 * Business logic for the absence types catalogue (spec 0278 D-5).
 */
class AbsenceTypeService
{
    public function __construct(private readonly InUseGuard $inUseGuard) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AbsenceType
    {
        return AbsenceType::create($attributes)->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AbsenceType $absenceType, array $attributes): AbsenceType
    {
        $absenceType->fill($attributes)->save();

        return $absenceType;
    }

    /**
     * Restrictive delete (spec 0278 D-5): a type used by a request is
     * deactivated, never removed.
     */
    public function delete(AbsenceType $absenceType): void
    {
        $this->inUseGuard->assertUnused($absenceType);

        $absenceType->delete();
    }

    /**
     * Active types in catalogue order, as the options of the request form; the
     * requested `$ids` (edit-mode hydration, ADR 0011) are served even when
     * inactive, after the active ones, so a saved type never blanks out.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function forSelect(array $ids = []): Collection
    {
        return AbsenceType::query()
            ->where(static fn ($query) => $query->where('is_active', true)->orWhereIn('id', $ids))
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'deducts_balance', 'requires_approval', 'allows_full_day', 'allows_half_day', 'allows_hourly', 'min_notice_days', 'color'])
            ->map(static fn (AbsenceType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'deducts_balance' => $type->deducts_balance,
                'requires_approval' => $type->requires_approval,
                'allows_full_day' => $type->allows_full_day,
                'allows_half_day' => $type->allows_half_day,
                'allows_hourly' => $type->allows_hourly,
                'min_notice_days' => $type->min_notice_days,
                'color' => $type->color,
            ]);
    }
}
