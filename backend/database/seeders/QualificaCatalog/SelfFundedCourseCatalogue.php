<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's self-funded ("Autofinanziato") course catalogue: the courses
 * sold directly to the learner, each with its list price. Pure data, like
 * TrainingCourseCatalogue — the seeder holds the logic, this file holds the
 * rows.
 *
 * Unlike the GOL courses (funded, hence priced 0 and split per region), these
 * all live under the single `Autofinanziato` subcategory of Formazione and
 * carry a real `price`. Duration and delivery mode used to be seeded here too,
 * into the product's `total_hours` and `delivery_mode`; the user directive
 * 2026-09-08 moved both fields to the OFFERTA
 * (CourseDataAttributeCatalogue), so the rows no longer carry a value nothing
 * would read.
 *
 * Course names are user-facing domain values, kept in their original
 * language, and are the natural key for the idempotent per-category upsert.
 */
final class SelfFundedCourseCatalogue
{
    /**
     * The product category these courses are filed under — a node of
     * QualificaCatalogSeeder::CATALOG, bound by identity so a rename there
     * breaks loudly here instead of silently dropping the whole list.
     */
    public const string CATEGORY = 'Autofinanziato';

    /**
     * @var list<array{name: string, price: float}>
     */
    public const array COURSES = [
        ['name' => 'OSS - Operatore Socio Sanitario', 'price' => 1900.0],
        ['name' => 'ASO - Assistente Studio Odontoiatrico', 'price' => 1500.0],
        ['name' => 'ASACOM - Assistente all\'Autonomia e alla Comunicazione', 'price' => 1000.0],
        ['name' => 'OPI - Operatore per l\'Infanzia', 'price' => 700.0],
        ['name' => 'OAC - Operatore Contabile Amministrativo', 'price' => 700.0],
        ['name' => 'OSA - Operatore Socio Assistenziale', 'price' => 700.0],
        ['name' => 'Tecnico del Comportamento ABA - Online', 'price' => 250.0],
        ['name' => 'EIPASS', 'price' => 230.0],
        ['name' => 'PEKIT Expert', 'price' => 150.0],
        ['name' => 'Aggiornamento ASO', 'price' => 130.0],
    ];
}
