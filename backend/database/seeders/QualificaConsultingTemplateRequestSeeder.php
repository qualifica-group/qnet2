<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\ConsultingTemplateRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the Consulenza "trattative" of the user's structured sheet (Sicurezza, ISO,
 * SOA, Finanza Agevolata, GDPR, Istruzione, Gare, Segnalazione Commerciale, APL)
 * into Gestione Richieste (user directive 2026-11-28, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaConsultingTemplateRequestSeeder`.
 */
class QualificaConsultingTemplateRequestSeeder extends RequestSheetSeeder
{
    protected function label(): string
    {
        return 'Consulenza (modello strutturato)';
    }

    protected function categoryName(): string
    {
        return ConsultingTemplateRequestCatalogue::CATEGORY;
    }

    /**
     * Every row adds its new phones/emails to a client already on file (user
     * decision 2026-11-28: same company, other contacts -> on its anagrafica).
     */
    protected function rows(): array
    {
        return array_map(
            static fn (array $row): array => [...$row, 'add_contacts' => true],
            ConsultingTemplateRequestCatalogue::ROWS,
        );
    }

    protected function newReporters(): array
    {
        return ConsultingTemplateRequestCatalogue::NEW_REPORTERS;
    }

    protected function operatorSites(): array
    {
        return ConsultingTemplateRequestCatalogue::OPERATOR_SITES;
    }

    protected function missingCities(): array
    {
        return ConsultingTemplateRequestCatalogue::MISSING_CITIES;
    }
}
