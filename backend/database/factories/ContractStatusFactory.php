<?php

namespace Database\Factories;

use App\Enums\ContractStatusGroup;
use App\Models\ContractStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractStatus>
 */
class ContractStatusFactory extends Factory
{
    protected $model = ContractStatus::class;

    /**
     * The 14 badge color tokens shared by the color-token picker
     * (frontend/src/features/custom-fields/badge-color-tokens.ts) — `color`
     * is a palette TOKEN, never a hex value.
     *
     * @var array<int, string>
     */
    private const array COLOR_TOKENS = [
        'slate', 'gray', 'red', 'orange', 'amber', 'yellow', 'green',
        'emerald', 'teal', 'blue', 'indigo', 'violet', 'purple', 'pink',
    ];

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 0;

    /**
     * Default: a plain custom row (never system, never default) with a FIXED
     * `group` — `Open`, the neutral value that triggers no special rule.
     * `group` is not a cosmetic attribute: BR-6 suppresses every contract
     * alert outright when the status' group is `ClosedLost`, so randomizing
     * it here would make any test exercising alerts (or anything else keyed
     * off the group) pass or fail non-deterministically depending on which
     * value the factory happened to roll. Tests that need a specific group
     * opt in explicitly via the `group()` state below — the four mandatory
     * system rows are seeded by the migration itself, not built through this
     * factory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'color' => fake()->randomElement(self::COLOR_TOKENS),
            'sort_order' => self::$nextSortOrder++,
            'is_active' => true,
            'is_default' => false,
            'group' => ContractStatusGroup::Open,
        ];
    }

    public function group(ContractStatusGroup $group): static
    {
        return $this->state(fn () => ['group' => $group]);
    }
}
