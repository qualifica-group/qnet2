<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Loads the four structured "Modello import trattative" sheets of 2026-11-28 into
 * Gestione Richieste in one run (user directive 2026-11-28: a single seeder, as for
 * the sheets of 2026-11-07). Each sheet keeps its own seeder; this one only runs
 * them in order. Idempotent like each of them: a second run writes nothing.
 * Run with `php artisan db:seed --class=QualificaRequestTemplatesSeeder`.
 */
class QualificaRequestTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            QualificaAplTemplateRequestSeeder::class,
            QualificaSelfFundedTemplateRequestSeeder::class,
            QualificaConsultingTemplateRequestSeeder::class,
            QualificaFundedTemplateRequestSeeder::class,
        ]);
    }
}
