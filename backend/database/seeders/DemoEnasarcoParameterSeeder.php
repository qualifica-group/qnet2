<?php

namespace Database\Seeders;

use App\Models\EnasarcoParameter;
use Illuminate\Database\Seeder;

/**
 * Demo ENASARCO parameters (spec 0213) for the current and the previous year.
 * The values are illustrative, not the ones set by law. Re-runnable: upserted by year.
 */
class DemoEnasarcoParameterSeeder extends Seeder
{
    private const array VALUES = [
        'total_rate' => '17.00',
        'agent_share_percent' => '50.00',
        'single_firm_ceiling' => '45000.00',
        'multi_firm_ceiling' => '26000.00',
        'notes' => 'Valori dimostrativi: non sono i parametri di legge.',
    ];

    public function run(): void
    {
        $year = now()->year;

        foreach ([$year, $year - 1] as $value) {
            EnasarcoParameter::query()->updateOrCreate(['year' => $value], self::VALUES);
        }
    }
}
