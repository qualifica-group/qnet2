<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\AppointmentSettingRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the Consulenza - Presa Appuntamenti "trattative" of the user's sheet into
 * Gestione Richieste (user directive 2026-11-07, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaAppointmentSettingRequestSeeder`.
 */
class QualificaAppointmentSettingRequestSeeder extends RequestSheetSeeder
{
    protected function label(): string
    {
        return 'Consulenza Presa Appuntamenti';
    }

    protected function categoryName(): string
    {
        return AppointmentSettingRequestCatalogue::CATEGORY;
    }

    protected function rows(): array
    {
        return AppointmentSettingRequestCatalogue::ROWS;
    }
}
