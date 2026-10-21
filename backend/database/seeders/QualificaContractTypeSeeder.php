<?php

namespace Database\Seeders;

use App\Models\ContractType;
use Illuminate\Database\Seeder;

/**
 * The client's contract type catalogue (spec 0290 D-2). Idempotent on `name`;
 * any other type is left untouched.
 */
class QualificaContractTypeSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, sort_order: int}>
     */
    private const TYPES = [
        ['name' => 'Tempo determinato', 'sort_order' => 1],
        ['name' => 'Tempo indeterminato', 'sort_order' => 2],
        ['name' => 'Apprendistato', 'sort_order' => 3],
        ['name' => 'Tirocinio', 'sort_order' => 4],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            ContractType::query()->updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
