<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\AplTemplateRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the APL "trattative" of the user's structured sheet (Orientamento
 * specialistico, Ricerca & Selezione, Accompagnamento al lavoro) into Gestione
 * Richieste (user directive 2026-11-28, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaAplTemplateRequestSeeder`.
 */
class QualificaAplTemplateRequestSeeder extends RequestSheetSeeder
{
    use SeedsCategoryAttributes;

    /**
     * Misura, Convocazione, Orientamento and CPI of Orientamento specialistico, on
     * its Commessa until now, enabled on the Offerta too (user decision 2026-11-28).
     * The attributes exist already: only the assignment is new.
     */
    private const array ORIENTATION_OFFER_ATTRIBUTES = [
        ['code' => 'orientation_measure', 'name' => 'Misura', 'type' => 'enum'],
        ['code' => 'orientation_convocation_date', 'name' => 'Convocazione', 'type' => 'date'],
        ['code' => 'orientation_session_date', 'name' => 'Orientamento', 'type' => 'date'],
        ['code' => 'cpi', 'name' => 'CPI', 'type' => 'text'],
    ];

    /** The Informazioni aggiuntive the rows carry, keyed by their own code. */
    private const array ATTRIBUTE_CODES = [
        'orientation_measure', 'orientation_convocation_date', 'orientation_session_date', 'cpi',
        'decree_status', 'reporting_id', 'decree_id', 'orientation_intake_date',
    ];

    protected function label(): string
    {
        return 'APL (modello strutturato)';
    }

    protected function categoryName(): string
    {
        return AplTemplateRequestCatalogue::CATEGORY;
    }

    protected function rows(): array
    {
        return AplTemplateRequestCatalogue::ROWS;
    }

    protected function newCommercials(): array
    {
        return AplTemplateRequestCatalogue::NEW_COMMERCIALS;
    }

    protected function attributeColumns(): array
    {
        return array_combine(self::ATTRIBUTE_CODES, self::ATTRIBUTE_CODES);
    }

    protected function operatorSites(): array
    {
        return AplTemplateRequestCatalogue::OPERATOR_SITES;
    }

    protected function prepare(ProductCategory $category): void
    {
        $this->seedCategoryAttributes($category, self::ORIENTATION_OFFER_ATTRIBUTES, AttributeContext::Quote);
    }
}
