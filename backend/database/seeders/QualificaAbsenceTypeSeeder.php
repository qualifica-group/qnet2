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
     * @var array<int, array{name: string, deducts_balance: bool, requires_approval: bool, sort_order: int}>
     */
    private const TYPES = [
        ['name' => 'Ferie', 'deducts_balance' => true, 'requires_approval' => true, 'sort_order' => 1],
        ['name' => 'Permesso', 'deducts_balance' => true, 'requires_approval' => true, 'sort_order' => 2],
        ['name' => 'Malattia', 'deducts_balance' => false, 'requires_approval' => false, 'sort_order' => 3],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            AbsenceType::query()->updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
