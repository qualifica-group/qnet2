<?php

namespace Database\Factories;

use App\Enums\QuoteStatusGroup;
use App\Models\QuoteStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteStatus>
 */
class QuoteStatusFactory extends Factory
{
    protected $model = QuoteStatus::class;

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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(self::COLOR_TOKENS),
            'sort_order' => self::$nextSortOrder++,
            'group' => QuoteStatusGroup::Open,
        ];
    }

    /**
     * Marks the row as one of the three mandatory system statuses ('new',
     * 'won' or 'lost' — spec 0065 D-2). Takes the literal system_key string
     * rather than the App\Enums\StatusSystemKey case, for symmetry with
     * RewardStatusFactory::system().
     */
    public function system(string $key): static
    {
        return $this->state(fn () => match ($key) {
            'won' => ['system_key' => 'won', 'name' => 'Accettata', 'color' => 'green', 'sort_order' => 998, 'group' => QuoteStatusGroup::ClosedWon],
            'lost' => ['system_key' => 'lost', 'name' => 'Rifiutata', 'color' => 'red', 'sort_order' => 999, 'group' => QuoteStatusGroup::ClosedLost],
            default => ['system_key' => 'new', 'name' => 'Bozza', 'color' => 'slate', 'sort_order' => 0, 'group' => QuoteStatusGroup::Open],
        });
    }

    public function group(QuoteStatusGroup $group): static
    {
        return $this->state(fn () => ['group' => $group]);
    }
}
