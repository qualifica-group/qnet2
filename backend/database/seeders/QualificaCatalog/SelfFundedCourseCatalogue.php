<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's self-funded ("Autofinanziato") course catalogue: the courses
 * sold directly to the learner, each with its total duration, its delivery
 * mode and its list price. Pure data, like TrainingCourseCatalogue — the
 * seeder holds the logic, this file holds the rows.
 *
 * Unlike the GOL courses (funded, hence priced 0 and split per region), these
 * all live under the single `Autofinanziato` subcategory of Formazione and
 * carry a real `price`. `hours` feeds the `total_hours` ("Ore complessive")
 * attribute inherited from the Formazione root; `delivery_mode` feeds the
 * enum attribute assigned to `Autofinanziato` only (spec 0061).
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
     * The `delivery_mode` option values (stored keys, English; their
     * user-facing labels live on the attribute options).
     */
    public const string IN_PERSON = 'in_person';

    public const string ONLINE = 'online';

    /**
     * @var list<array{name: string, hours: int, delivery_mode: string, price: float}>
     */
    public const array COURSES = [
        ['name' => 'OSS - Operatore Socio Sanitario', 'hours' => 1000, 'delivery_mode' => self::IN_PERSON, 'price' => 1900.0],
        ['name' => 'ASO - Assistente Studio Odontoiatrico', 'hours' => 700, 'delivery_mode' => self::IN_PERSON, 'price' => 1500.0],
        ['name' => 'ASACOM - Assistente all\'Autonomia e alla Comunicazione', 'hours' => 500, 'delivery_mode' => self::ONLINE, 'price' => 1000.0],
        ['name' => 'OPI - Operatore per l\'Infanzia', 'hours' => 300, 'delivery_mode' => self::ONLINE, 'price' => 700.0],
        ['name' => 'OAC - Operatore Contabile Amministrativo', 'hours' => 300, 'delivery_mode' => self::ONLINE, 'price' => 700.0],
        ['name' => 'OSA - Operatore Socio Assistenziale', 'hours' => 300, 'delivery_mode' => self::ONLINE, 'price' => 700.0],
        ['name' => 'Tecnico del Comportamento ABA - Online', 'hours' => 40, 'delivery_mode' => self::ONLINE, 'price' => 250.0],
        ['name' => 'EIPASS', 'hours' => 200, 'delivery_mode' => self::ONLINE, 'price' => 230.0],
        ['name' => 'PEKIT Expert', 'hours' => 200, 'delivery_mode' => self::ONLINE, 'price' => 150.0],
        ['name' => 'Aggiornamento ASO', 'hours' => 10, 'delivery_mode' => self::ONLINE, 'price' => 130.0],
    ];
}
