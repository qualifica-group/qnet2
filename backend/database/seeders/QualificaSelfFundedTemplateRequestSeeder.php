<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\SelfFundedTemplateRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the Autofinanziato "trattative" of the user's structured sheet (Sicilia,
 * Lazio, Campania, Lombardia) into Gestione Richieste (user directive 2026-11-28,
 * RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaSelfFundedTemplateRequestSeeder`.
 */
class QualificaSelfFundedTemplateRequestSeeder extends RequestSheetSeeder
{
    /** The Informazioni aggiuntive the rows carry, keyed by their own code. */
    private const array ATTRIBUTE_CODES = ['total_hours', 'classroom_status', 'course_name', 'course_edition'];

    protected function label(): string
    {
        return 'Autofinanziato (modello strutturato)';
    }

    protected function categoryName(): string
    {
        return SelfFundedTemplateRequestCatalogue::CATEGORY;
    }

    protected function rows(): array
    {
        return SelfFundedTemplateRequestCatalogue::ROWS;
    }

    protected function newReporters(): array
    {
        return SelfFundedTemplateRequestCatalogue::NEW_REPORTERS;
    }

    protected function newCommercials(): array
    {
        return SelfFundedTemplateRequestCatalogue::NEW_COMMERCIALS;
    }

    protected function operatorSites(): array
    {
        return SelfFundedTemplateRequestCatalogue::OPERATOR_SITES;
    }

    protected function attributeColumns(): array
    {
        return array_combine(self::ATTRIBUTE_CODES, self::ATTRIBUTE_CODES);
    }
}
