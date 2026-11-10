<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\SelfFundedCampaniaRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the Autofinanziato - Campania "trattative" of the user's sheet into
 * Gestione Richieste (user directive 2026-11-07, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaSelfFundedCampaniaRequestSeeder`.
 */
class QualificaSelfFundedCampaniaRequestSeeder extends RequestSheetSeeder
{
    protected function label(): string
    {
        return 'Autofinanziato Campania';
    }

    protected function categoryName(): string
    {
        return SelfFundedCampaniaRequestCatalogue::CATEGORY;
    }

    protected function rows(): array
    {
        return SelfFundedCampaniaRequestCatalogue::ROWS;
    }

    protected function newReporters(): array
    {
        return SelfFundedCampaniaRequestCatalogue::NEW_REPORTERS;
    }

    protected function attributeColumns(): array
    {
        return ['course_time_preference' => 'time_preference', 'price' => 'price'];
    }
}
