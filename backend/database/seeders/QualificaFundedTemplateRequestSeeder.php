<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\FundedTemplateRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the Finanziato "trattative" (DIL - Lombardia) of the user's structured
 * sheet into Gestione Richieste (user directive 2026-11-28, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaFundedTemplateRequestSeeder`.
 */
class QualificaFundedTemplateRequestSeeder extends RequestSheetSeeder
{
    /** The Informazioni aggiuntive the rows carry, keyed by their own code. */
    private const array ATTRIBUTE_CODES = ['classroom_status', 'course_name', 'course_edition', 'course_start_date', 'course_end_date', 'data_scelta_cpi'];

    protected function label(): string
    {
        return 'Finanziato (modello strutturato)';
    }

    protected function categoryName(): string
    {
        return FundedTemplateRequestCatalogue::CATEGORY;
    }

    protected function rows(): array
    {
        return FundedTemplateRequestCatalogue::ROWS;
    }

    protected function operatorSites(): array
    {
        return FundedTemplateRequestCatalogue::OPERATOR_SITES;
    }

    protected function attributeColumns(): array
    {
        return array_combine(self::ATTRIBUTE_CODES, self::ATTRIBUTE_CODES);
    }

    protected function missingCities(): array
    {
        return FundedTemplateRequestCatalogue::MISSING_CITIES;
    }
}
