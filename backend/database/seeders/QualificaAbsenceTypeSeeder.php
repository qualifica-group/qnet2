<?php

namespace Database\Seeders;

use App\Models\AbsenceType;
use Illuminate\Database\Seeder;

/**
 * The client's absence type catalogue (spec 0278 D-5). Idempotent on `name`;
 * a re-run restores the rules of the three base types and leaves any other
 * type untouched.
 */
class QualificaAbsenceTypeSeeder extends Seeder
{
    /**
     * Notice and default allowance are left out on purpose: the HR office sets them (spec 0304).
     *
     * @var array<int, array<string, bool|int|string>>
     */
    private const TYPES = [
        [
            'name' => 'Ferie', 'deducts_balance' => true, 'requires_approval' => true, 'sort_order' => 1,
            'allows_full_day' => true, 'allows_half_day' => true, 'allows_hourly' => false, 'color' => 'blue',
        ],
        [
            'name' => 'Permesso', 'deducts_balance' => true, 'requires_approval' => true, 'sort_order' => 2,
            'allows_full_day' => false, 'allows_half_day' => true, 'allows_hourly' => true, 'color' => 'violet',
        ],
        [
            'name' => 'Malattia', 'deducts_balance' => false, 'requires_approval' => false, 'sort_order' => 3,
            'allows_full_day' => true, 'allows_half_day' => true, 'allows_hourly' => false, 'color' => 'amber',
        ],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            AbsenceType::query()->updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
