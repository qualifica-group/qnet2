<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Loads every "trattative" sheet of 2026-11-06 into Gestione Richieste in one run
 * (user directive 2026-11-07: a single seeder). Each sheet keeps its own seeder,
 * the per-file configuration of the shared RequestSheetSeeder; this one only runs
 * them in order. Idempotent like each of them: a second run writes nothing.
 * Run with `php artisan db:seed --class=QualificaRequestSheetsSeeder`.
 */
class QualificaRequestSheetsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            QualificaAplOrientationRequestSeeder::class,
            QualificaSelfFundedCampaniaRequestSeeder::class,
            QualificaAppointmentSettingRequestSeeder::class,
            QualificaECampusRequestSeeder::class,
            QualificaDilLombardyRequestSeeder::class,
            QualificaConsultingServicesRequestSeeder::class,
        ]);
    }
}
