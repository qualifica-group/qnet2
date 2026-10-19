<?php

namespace App\Services;

use App\Models\AbsenceType;
use Illuminate\Support\Collection;

/**
 * Business logic for the absence types catalogue (spec 0278 D-5).
 */
class AbsenceTypeService
{
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
        if ($absenceType->absenceRequests()->exists()) {
            abort(409, __('absence_types.in_use'));
        }

        $absenceType->delete();
    }

    /**
     * Active types in catalogue order, as the options of the request form.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forSelect(): Collection
    {
        return AbsenceType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'deducts_balance', 'requires_approval'])
            ->map(static fn (AbsenceType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'deducts_balance' => $type->deducts_balance,
                'requires_approval' => $type->requires_approval,
            ]);
    }
}
