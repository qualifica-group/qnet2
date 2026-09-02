<?php

namespace App\Http\Requests\Concerns;

use App\Support\ManagerPositions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for an ordered, gap-aware slot payload (spec 0020,
 * "Gestori"): a list whose index+1 is the static position and whose null
 * entries are intentionally empty slots.
 *
 * The base array/element rules live in managerSlotsRules(); the cross-element
 * invariants (at most MAX_MANAGERS filled, no user in two slots) run in
 * validateManagerSlots(), called from each request's own withValidator().
 *
 * Both take the payload key as a parameter, defaulting to `manager_slots`:
 * Registries/Opportunita'/Offerte call them unchanged, while the Commessa's
 * own team submits the same SHAPE under `participant_slots` (spec 0096, D-3).
 * One rule set, two field names — never a second copy of these invariants.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesManagerSlots
{
    /** Business cap on filled manager slots (spec 0080 amendment A1: shared with ProductCategory::MANAGER_LABEL_MAX_POSITION via ManagerPositions). */
    private const int MAX_MANAGERS = ManagerPositions::MAX;

    /**
     * Sanity cap on the slot array length (filled + empty), bounding the
     * payload. Deliberately the SAME value as MAX_MANAGERS (spec 0080
     * amendment A1, D2): a fully-packed, gap-free payload reaching the
     * highest fillable position needs an array exactly MAX_MANAGERS long,
     * so this stays tight with no slack to spare — verified, not assumed.
     */
    private const int MAX_MANAGER_SLOTS = ManagerPositions::MAX;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function managerSlotsRules(string $field = 'manager_slots'): array
    {
        return [
            $field => ['sometimes', 'array', 'max:'.self::MAX_MANAGER_SLOTS],
            $field.'.*' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Cross-element rules: the number of FILLED slots is capped and a single
     * manager may occupy only one slot. Empty (null) slots are unconstrained.
     */
    protected function validateManagerSlots(Validator $validator, string $field = 'manager_slots'): void
    {
        $slots = $this->input($field);

        if (! is_array($slots)) {
            return;
        }

        $filled = array_values(array_filter($slots, static fn ($id): bool => $id !== null));

        if (count($filled) > self::MAX_MANAGERS) {
            $validator->errors()->add($field, 'At most '.self::MAX_MANAGERS.' users can fill these slots.');
        }

        if (count($filled) !== count(array_unique($filled))) {
            $validator->errors()->add($field, 'A user can occupy only one slot.');
        }
    }
}
