<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\ECampusRequestCatalogue;
use Database\Seeders\Support\RequestSheetSeeder;

/**
 * Loads the Corsi E-Campus "trattative" of the user's sheet into Gestione
 * Richieste (user directive 2026-11-07, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaECampusRequestSeeder`.
 */
class QualificaECampusRequestSeeder extends RequestSheetSeeder
{
    protected function label(): string
    {
        return 'Corsi E-Campus';
    }

    protected function categoryName(): string
    {
        return ECampusRequestCatalogue::CATEGORY;
    }

    protected function rows(): array
    {
        return ECampusRequestCatalogue::ROWS;
    }

    protected function attributeColumns(): array
    {
        return ['degree_level' => 'degree_level', 'faculty' => 'faculty'];
    }
}
