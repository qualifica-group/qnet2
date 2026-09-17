<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's DIL ("Dote Inserimento Lavorativo") training-course catalogue,
 * transcribed from the three "Catalogo DIL - Lombardia" brochures (Bergamo,
 * Grumello del Monte, Milano). Pure data, like TrainingCourseCatalogue — same
 * shape, same consumer (CatalogProducts), a file of its own because it is a
 * different measure, not a GOL region.
 *
 * The three brochures list the very same courses in the same order and differ
 * only in the Sede's contacts, so the union is one list (user directive
 * 2026-09-17). Keyed by the FULL product-category name, bound by identity to
 * the "DIL - Lombardia" node of QualificaCatalogSeeder::CATALOG.
 *
 * `hours` is written nowhere, exactly as for the GOL courses: it only
 * discriminates a name repeated in the list — "Make-up Artist Professionale"
 * runs at 30 and at 40 hours, two distinct courses (see
 * CatalogProducts::disambiguate()).
 */
final class DilCourseCatalogue
{
    /**
     * Product category name => list of courses.
     *
     * @var array<string, list<array{name: string, hours: int}>>
     */
    public const array COURSES = [
        'DIL - Lombardia' => [
            ['name' => 'Google Workspace', 'hours' => 30],
            ['name' => 'Introduzione all\'Intelligenza Artificiale', 'hours' => 16],
            ['name' => 'ChatGPT per il lavoro d\'ufficio', 'hours' => 16],
            ['name' => 'Copilot per Microsoft 365', 'hours' => 16],
            ['name' => 'Canva professionale', 'hours' => 24],
            ['name' => 'Elementi base di Social Communication', 'hours' => 24],
            ['name' => 'Elementi di Social Communication per aziende', 'hours' => 40],
            ['name' => 'E-commerce Base', 'hours' => 32],
            ['name' => 'Front Office professionale', 'hours' => 24],
            ['name' => 'Contabilità Base', 'hours' => 32],
            ['name' => 'Fatturazione elettronica', 'hours' => 16],
            ['name' => 'Paghe e Contributi Base', 'hours' => 40],
            ['name' => 'Gestione Magazzino', 'hours' => 32],
            ['name' => 'Tecniche di vendita', 'hours' => 24],
            ['name' => 'Customer Experience', 'hours' => 30],
            ['name' => 'AutoCAD Base', 'hours' => 40],
            ['name' => 'Segreteria Studio Medico', 'hours' => 32],
            ['name' => 'Accoglienza pazienti', 'hours' => 16],
            ['name' => 'Make-up Artist Professionale', 'hours' => 30],
            ['name' => 'Make-up Artist Professionale', 'hours' => 40],
            ['name' => 'Skincare e consulenza cosmetica', 'hours' => 30],
            ['name' => 'Beauty Consultant', 'hours' => 32],
            ['name' => 'Lash & Brow Styling', 'hours' => 30],
            ['name' => 'Massaggio rilassante base', 'hours' => 30],
            ['name' => 'Tecniche di massaggio decontratturante', 'hours' => 40],
            ['name' => 'Riflessologia plantare introduttiva', 'hours' => 32],
            ['name' => 'Mindfulness per il benessere', 'hours' => 30],
            ['name' => 'Tecniche di rilassamento e gestione dello stress', 'hours' => 30],
            ['name' => 'Consulenza olistica di base', 'hours' => 30],
        ],
    ];
}
