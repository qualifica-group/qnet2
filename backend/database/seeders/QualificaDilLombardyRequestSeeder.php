<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\DilLombardyRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the DIL - Lombardia (sede di Milano) "trattative" of the user's sheet into
 * Gestione Richieste (user directive 2026-11-07, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaDilLombardyRequestSeeder`.
 */
class QualificaDilLombardyRequestSeeder extends RequestSheetSeeder
{
    protected function label(): string
    {
        return 'DIL Lombardia Milano';
    }

    protected function categoryName(): string
    {
        return DilLombardyRequestCatalogue::CATEGORY;
    }

    /**
     * The course site is a relation to an operational Sede: the catalogue names its
     * city, resolved here to the Sede id the attribute stores.
     */
    protected function rows(): array
    {
        return array_map(
            fn (array $row): array => [...$row, 'course_site' => $this->lookups->siteIdInCity($row['course_site'])],
            DilLombardyRequestCatalogue::ROWS,
        );
    }

    protected function newReporters(): array
    {
        return DilLombardyRequestCatalogue::NEW_REPORTERS;
    }

    protected function newCommercials(): array
    {
        return DilLombardyRequestCatalogue::NEW_COMMERCIALS;
    }

    protected function attributeColumns(): array
    {
        return [
            'data_scelta_cpi' => 'cpi_date',
            'data_app_apl' => 'apl_date',
            'dote_activation_date' => 'dote_start',
            'dote_expiry_date' => 'dote_end',
            'remaining_hours' => 'remaining_hours',
            'subsidy_type' => 'subsidy',
            'course_site' => 'course_site',
        ];
    }
}
