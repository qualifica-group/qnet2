<?php

namespace Database\Seeders\DemoCatalog;

/**
 * The DEMO product catalogue (spec 0017): a handful of sellable rows per LEAF
 * category of DemoCategoryCatalogue, each carrying values for the product
 * attributes its branch assigns. Pure data — DemoProductSeeder holds the logic.
 *
 * They are not decoration: "prodotti di interesse" is a mandatory field of the
 * opportunity form (user directive 2026-07-23), so without products the demo
 * dataset cannot produce a single submittable opportunity.
 *
 * `attribute_values` is keyed by Attribute `code`, exactly like the payload
 * the product form submits. The `demo_consultant` relation is left unset on
 * purpose: its value is a referent id, which only exists at seed time.
 */
final class DemoProductCatalogue
{
    /**
     * Leaf category name => its products. `cost`/`price` are plain demo
     * figures; `type` is a ProductType value.
     *
     * @var array<string, list<array{name: string, cost: float, price: float, type: string, attribute_values: array<string, mixed>}>>
     */
    public const array PRODUCTS = [
        'Corsi in Aula' => [
            [
                'name' => 'Corso Sicurezza sul Lavoro',
                'cost' => 320.0,
                'price' => 590.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_course_hours' => 16,
                    'demo_course_level' => 'basic',
                    'demo_course_start_date' => '2026-09-14',
                    'demo_certification' => true,
                ],
            ],
            [
                'name' => 'Corso Excel Avanzato',
                'cost' => 400.0,
                'price' => 790.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_course_hours' => 24,
                    'demo_course_level' => 'advanced',
                    'demo_course_start_date' => '2026-10-05',
                    'demo_certification' => true,
                ],
            ],
            [
                'name' => 'Corso Project Management',
                'cost' => 560.0,
                'price' => 1180.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_course_hours' => 32,
                    'demo_course_level' => 'intermediate',
                    'demo_course_start_date' => '2026-11-09',
                    'demo_certification' => false,
                ],
            ],
        ],
        'Corsi Online' => [
            [
                'name' => 'Corso Digital Marketing Online',
                'cost' => 240.0,
                'price' => 490.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_course_hours' => 20,
                    'demo_course_level' => 'intermediate',
                    'demo_course_start_date' => '2026-09-21',
                    'demo_certification' => true,
                    'demo_platform' => 'teams',
                ],
            ],
            [
                'name' => 'Corso Inglese Business Online',
                'cost' => 380.0,
                'price' => 690.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_course_hours' => 40,
                    'demo_course_level' => 'basic',
                    'demo_course_start_date' => '2026-10-12',
                    'demo_certification' => false,
                    'demo_platform' => 'zoom',
                ],
            ],
        ],
        'Consulenza HR' => [
            [
                'name' => 'Assessment delle Competenze',
                'cost' => 1200.0,
                'price' => 2500.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_consulting_days' => 5,
                    'demo_seniority' => 'senior',
                ],
            ],
            [
                'name' => 'Piano Welfare Aziendale',
                'cost' => 2600.0,
                'price' => 5400.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_consulting_days' => 10,
                    'demo_seniority' => 'partner',
                ],
            ],
        ],
        'Consulenza IT' => [
            [
                'name' => 'Audit Sicurezza Informatica',
                'cost' => 2100.0,
                'price' => 4200.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_consulting_days' => 8,
                    'demo_seniority' => 'senior',
                ],
            ],
            [
                'name' => 'Migrazione Cloud',
                'cost' => 4300.0,
                'price' => 8900.0,
                'type' => 'SERVICE',
                'attribute_values' => [
                    'demo_consulting_days' => 15,
                    'demo_seniority' => 'partner',
                ],
            ],
        ],
    ];
}
