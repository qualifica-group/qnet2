<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's e-Campus degree catalogue, transcribed from the "CORSI
 * E-CAMPUS" sheet (user directive 2026-10-01). Pure data, like
 * SelfFundedCourseCatalogue — CatalogProducts files the products on the node.
 *
 * Every product sits directly on "Corsi E-Campus", a selectable subcategory
 * of the "Formazione" root (user directive 2026-10-02). The degree-level and
 * subject-area nodes it used to split into are gone — ECampusTreeFlattening
 * folds them on an installation that still has them.
 *
 * A course is sold as one SERVICE product per fee of its degree level, named
 * "<course> <fee>" and priced at the fee: the sheet lists each course as
 * "<course>: Online, N anni, Accesso Libero", and the product name stops
 * before the colon. No fee is quoted "+ iva", so no product carries a VAT rate.
 *
 * The sheet's "Corsi di laurea Magistrali a ciclo unico" (Giurisprudenza
 * [LMG/01]) quotes no fee, so it is deliberately not transcribed.
 *
 * The sheet's "PROGETTO FORM" fee is not a product either (user directive
 * 2026-10-02): the offer's "Corso Form" flag records it
 * (ECampusAttributeCatalogue).
 *
 * Names are user-facing domain values, kept as the sheet spells them.
 */
final class ECampusCourseCatalogue
{
    public const string CATEGORY = 'Corsi E-Campus';

    /**
     * Fees an earlier revision sold as a product of every course: their
     * products are withdrawn on re-seed (CatalogProducts).
     *
     * @var list<string>
     */
    public const array RETIRED_FEES = ['PROGETTO FORM'];

    /**
     * Degree level => its fees (product suffix => price) and its courses
     * (course name => subject area). The area, as the "CORSI E-CAMPUS" sheet
     * groups the courses, is every product's description (user directive
     * 2026-10-02): the pickers show and search it beside the category.
     *
     * @var array<string, array{fees: array<string, float>, courses: array<string, string>}>
     */
    public const array DEGREES = [
        'Corsi di Laurea Triennali' => [
            'fees' => [
                'ASSISTENZA E TUTORAGGIO' => 500.0,
                '1°ANNO' => 2856.0,
                '2°ANNO' => 2856.0,
                '3°ANNO' => 2856.0,
                'TESI' => 300.0,
            ],
            'courses' => [
                'Ingegneria Civile e Ambientale [L-7]' => 'Ingegneria',
                'Ingegneria Informatica e dell\'Automazione [L-8]' => 'Ingegneria',
                'Ingegneria Industriale [L-9]' => 'Ingegneria',
                'Letteratura, Arte, Musica e Spettacolo [L-10]' => 'Letteratura',
                'Lingue e Culture Europee e del Resto del Mondo [L-11]' => 'Letteratura',
                'Design e Discipline della Moda [L-3]' => 'Letteratura',
                'Scienze Biologiche [L-13]' => 'Psicologia',
                'Scienze dell\'Educazione e della Formazione [L-19]' => 'Psicologia',
                'Scienze delle Attività Motorie e Sportive [L-22]' => 'Psicologia',
                'Scienze e Tecniche Psicologiche [L-24]' => 'Psicologia',
                'Scienze del Turismo per il Management e i Beni Culturali [L-15]' => 'Economia',
                'Economia [L-33]' => 'Economia',
                'Servizi Giuridici [L-14]' => 'Giurisprudenza',
                'Scienze della Comunicazione [L-20]' => 'Giurisprudenza',
                'Scienze Politiche e Sociali [L-36]' => 'Giurisprudenza',
            ],
        ],
        'Corsi di Laurea Magistrali' => [
            'fees' => [
                'ASSISTENZA E TUTORAGGIO' => 500.0,
                '1°ANNO' => 3056.0,
                '2°ANNO' => 3056.0,
                'TESI' => 300.0,
            ],
            'courses' => [
                'Letteratura, Lingua e Cultura Italiana [LM-14]' => 'Letteratura',
                'Lingue e Letterature Moderne e Traduzione Interculturale [LM-37]' => 'Letteratura',
                'Ingegneria Civile [LM-23]' => 'Ingegneria',
                'Ingegneria Informatica e dell\'Automazione [LM-32]' => 'Ingegneria',
                'Ingegneria Industriale [LM-33]' => 'Ingegneria',
                'Psicologia [LM-51]' => 'Psicologia',
                'Scienze dell\'Esercizio Fisico per il Benessere e la Salute [LM-67]' => 'Psicologia',
                'Scienze Pedagogiche [LM-85]' => 'Psicologia',
                'Scienze della Nutrizione Umana [LM-61]' => 'Psicologia',
                'Scienze dell\'Economia [LM-56]' => 'Economia',
            ],
        ],
    ];
}
