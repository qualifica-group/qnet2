<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\ConsultingServicesRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the consulting services "trattative" of the user's sheet into Gestione
 * Richieste (user directive 2026-11-07, RequestSheetSeeder); each row carries its
 * own category. Run with `php artisan db:seed --class=QualificaConsultingServicesRequestSeeder`.
 */
class QualificaConsultingServicesRequestSeeder extends RequestSheetSeeder
{
    protected function label(): string
    {
        return 'Servizi Consulenza';
    }

    protected function categoryName(): string
    {
        return ConsultingServicesRequestCatalogue::DEFAULT_CATEGORY;
    }

    protected function rows(): array
    {
        return ConsultingServicesRequestCatalogue::ROWS;
    }

    protected function newReporters(): array
    {
        return ConsultingServicesRequestCatalogue::NEW_REPORTERS;
    }
}
