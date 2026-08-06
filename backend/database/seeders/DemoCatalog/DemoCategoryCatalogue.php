<?php

namespace Database\Seeders\DemoCatalog;

/**
 * The DEMO product-category catalogue (spec 0017/0061/0062/0084): a small
 * two-branch tree with its own attributes in the Product AND Quote usage
 * contexts and the form sections that group them. Pure data —
 * DemoProductCategorySeeder holds the logic.
 *
 * It exists because the demo dataset had no category at all: the client's real
 * catalogue lives in the Qualifica* seeders (production data, never part of
 * `DemoDataSeeder`), so a demo database could not produce a single valid
 * opportunity — `product_lines` and `products_of_interest` are both mandatory
 * and both resolve from categories.
 *
 * Names are deliberately distinct from the Qualifica ones ("Formazione",
 * "Consulenza"): the two seeds use `name` as the natural key, so a database
 * carrying both must not collapse them onto the same rows.
 *
 * `code` is the English identifier (natural key, `^[a-z0-9_]+$`), prefixed
 * `demo_` so a demo attribute is never mistaken for a client one in the
 * attributes module; `name` is the user-facing label, kept in Italian like
 * every other demo dataset.
 */
final class DemoCategoryCatalogue
{
    /**
     * Root category => its own business function (by name, from
     * DemoBusinessFunctionSeeder) plus its leaf children. The function is
     * assigned to the ROOT only: CategoryHierarchy resolves the EFFECTIVE one
     * by walking up, so every leaf inherits it and can pair into a product
     * line without an assignment of its own.
     *
     * @var array<string, array{business_function: string, children: list<string>}>
     */
    public const array TREE = [
        'Servizi Formativi' => [
            'business_function' => 'Commerciale e Vendite',
            'children' => ['Corsi in Aula', 'Corsi Online'],
        ],
        'Consulenza Aziendale' => [
            'business_function' => 'Assistenza Clienti',
            'children' => ['Consulenza HR', 'Consulenza IT'],
        ],
    ];

    /**
     * PRODUCT-context attributes (what a product of the category carries):
     * category name => attribute specs. Assigned at the HIGHEST node that
     * needs the field — the branch inherits it — except `demo_platform`,
     * which only makes sense for the online leaf.
     *
     * @var array<string, list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>}>>
     */
    public const array PRODUCT_ATTRIBUTES = [
        'Servizi Formativi' => [
            ['code' => 'demo_course_hours', 'name' => 'Ore corso', 'type' => 'integer'],
            ['code' => 'demo_course_level', 'name' => 'Livello', 'type' => 'enum', 'options' => [
                ['value' => 'basic', 'label' => 'Base'],
                ['value' => 'intermediate', 'label' => 'Intermedio'],
                ['value' => 'advanced', 'label' => 'Avanzato'],
            ]],
            ['code' => 'demo_course_start_date', 'name' => 'Data inizio', 'type' => 'date'],
            ['code' => 'demo_certification', 'name' => 'Rilascia attestato', 'type' => 'boolean'],
        ],
        'Corsi Online' => [
            ['code' => 'demo_platform', 'name' => 'Piattaforma', 'type' => 'enum', 'options' => [
                ['value' => 'zoom', 'label' => 'Zoom'],
                ['value' => 'teams', 'label' => 'Microsoft Teams'],
                ['value' => 'meet', 'label' => 'Google Meet'],
            ]],
        ],
        'Consulenza Aziendale' => [
            ['code' => 'demo_consulting_days', 'name' => 'Giornate previste', 'type' => 'integer'],
            ['code' => 'demo_seniority', 'name' => 'Seniority richiesta', 'type' => 'enum', 'options' => [
                ['value' => 'junior', 'label' => 'Junior'],
                ['value' => 'senior', 'label' => 'Senior'],
                ['value' => 'partner', 'label' => 'Partner'],
            ]],
            ['code' => 'demo_consultant', 'name' => 'Consulente di riferimento', 'type' => 'relation', 'relation_target' => [
                'entity_type' => 'referents',
                'cardinality' => 'one',
                'for_select_resource' => 'referents',
            ]],
        ],
    ];

    /**
     * QUOTE-context attributes (what the operator records on the Offerta,
     * spec 0084 — moved here from the Opportunity work panel, spec 0049):
     * same scoping rule as above. `demo_processing_notes` is assigned to BOTH
     * roots on purpose — one catalogue row (the code is the natural key), two
     * assignments.
     *
     * @var array<string, list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>}>>
     */
    public const array QUOTE_ATTRIBUTES = [
        'Servizi Formativi' => [
            ['code' => 'demo_enrollment_status', 'name' => 'Stato iscrizione', 'type' => 'enum', 'options' => [
                ['value' => 'to_contact', 'label' => 'Da contattare'],
                ['value' => 'contacted', 'label' => 'Contattato'],
                ['value' => 'enrolled', 'label' => 'Iscritto'],
                ['value' => 'withdrawn', 'label' => 'Rinuncia'],
            ]],
            ['code' => 'demo_contact_date', 'name' => 'Data primo contatto', 'type' => 'date'],
            ['code' => 'demo_preferred_time', 'name' => 'Preferenza orario', 'type' => 'enum', 'options' => [
                ['value' => 'morning', 'label' => 'Mattina'],
                ['value' => 'afternoon', 'label' => 'Pomeriggio'],
                ['value' => 'evening', 'label' => 'Sera'],
            ]],
            ['code' => 'demo_documents_received', 'name' => 'Documenti ricevuti', 'type' => 'boolean'],
            ['code' => 'demo_processing_notes', 'name' => 'Note di lavorazione', 'type' => 'textarea'],
        ],
        'Consulenza Aziendale' => [
            ['code' => 'demo_appointment_date', 'name' => 'Data appuntamento', 'type' => 'date'],
            ['code' => 'demo_appointment_outcome', 'name' => 'Esito appuntamento', 'type' => 'enum', 'options' => [
                ['value' => 'positive', 'label' => 'Positivo'],
                ['value' => 'to_recall', 'label' => 'Da richiamare'],
                ['value' => 'negative', 'label' => 'Negativo'],
            ]],
            ['code' => 'demo_budget_confirmed', 'name' => 'Budget confermato', 'type' => 'boolean'],
            ['code' => 'demo_processing_notes', 'name' => 'Note di lavorazione', 'type' => 'textarea'],
        ],
    ];

    /**
     * PRODUCT-context form sections (spec 0062), per BRANCH ROOT: the seeder
     * writes one layout row per category of the branch — a layout is never
     * inherited (AttributeLayoutService reads the exact category) — filtering
     * each section's codes down to that category's own effective attributes.
     *
     * @var array<string, list<array{id: string, title: string, rows: list<list<string>>}>>
     */
    public const array PRODUCT_SECTIONS = [
        'Servizi Formativi' => [
            [
                'id' => 'demo-course-data',
                'title' => 'Dati corso',
                'rows' => [
                    ['demo_course_hours', 'demo_course_level'],
                    ['demo_course_start_date', 'demo_certification'],
                ],
            ],
            [
                'id' => 'demo-course-delivery',
                'title' => 'Erogazione',
                'rows' => [['demo_platform']],
            ],
        ],
        'Consulenza Aziendale' => [
            [
                'id' => 'demo-consulting-data',
                'title' => 'Dati incarico',
                'rows' => [
                    ['demo_consulting_days', 'demo_seniority'],
                    ['demo_consultant'],
                ],
            ],
        ],
    ];

    /**
     * QUOTE-context form sections: the "Dati lavorazione" blocks the Offerta
     * form renders (spec 0084) for a quote whose offer lines point at a
     * category of the branch.
     *
     * @var array<string, list<array{id: string, title: string, rows: list<list<string>>}>>
     */
    public const array QUOTE_SECTIONS = [
        'Servizi Formativi' => [
            [
                'id' => 'demo-enrollment',
                'title' => 'Dati iscrizione',
                'rows' => [
                    ['demo_enrollment_status', 'demo_contact_date'],
                    ['demo_preferred_time', 'demo_documents_received'],
                ],
            ],
            [
                'id' => 'demo-training-outcome',
                'title' => 'Esito lavorazione',
                'rows' => [['demo_processing_notes']],
            ],
        ],
        'Consulenza Aziendale' => [
            [
                'id' => 'demo-appointment',
                'title' => 'Appuntamento',
                'rows' => [
                    ['demo_appointment_date', 'demo_appointment_outcome'],
                    ['demo_budget_confirmed'],
                ],
            ],
            [
                'id' => 'demo-consulting-outcome',
                'title' => 'Esito lavorazione',
                'rows' => [['demo_processing_notes']],
            ],
        ],
    ];

    /**
     * Every category name of the demo tree, roots first then their children —
     * the iteration order the seeders share.
     *
     * @return list<string>
     */
    public static function categoryNames(): array
    {
        $names = [];

        foreach (self::TREE as $rootName => $branch) {
            $names[] = $rootName;

            foreach ($branch['children'] as $childName) {
                $names[] = $childName;
            }
        }

        return $names;
    }

    /**
     * The branch root a category belongs to (a root maps to itself) — how the
     * seeders resolve which section/status list a category takes.
     */
    public static function branchOf(string $categoryName): string
    {
        foreach (self::TREE as $rootName => $branch) {
            if ($rootName === $categoryName || in_array($categoryName, $branch['children'], true)) {
                return $rootName;
            }
        }

        throw new \InvalidArgumentException("Unknown demo category: {$categoryName}.");
    }
}
