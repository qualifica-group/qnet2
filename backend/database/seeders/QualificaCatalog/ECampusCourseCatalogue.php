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
 * before the colon. The course code leads the name ("[LM-56] Scienze
 * dell'Economia", user directive 2026-10-08): an installation still holding
 * the trailing-code names is renamed on re-seed (ECampusProducts). No fee is
 * quoted "+ iva", so no product carries a VAT rate.
 *
 * The sheet's "Corsi di laurea Magistrali a ciclo unico" (Giurisprudenza
 * [LMG/01]) quotes no fee, so it is deliberately not transcribed.
 *
 * "FORM" is a fee of every course again (user directive 2026-10-08), beside
 * the offer's "Corso Form" flag (ECampusAttributeCatalogue). The thesis and
 * the tutoring are no longer a fee of every course but one product each
 * (SINGLE_PRODUCTS, same directive).
 *
 * Names are user-facing domain values, kept as the sheet spells them.
 */
final class ECampusCourseCatalogue
{
    public const string CATEGORY = 'Corsi E-Campus';

    /**
     * Fees an earlier revision sold as a product of every course: their
     * products are withdrawn on re-seed (ECampusProducts).
     *
     * @var list<string>
     */
    public const array RETIRED_FEES = ['PROGETTO FORM', 'ASSISTENZA E TUTORAGGIO', 'TESI'];

    /**
     * The services sold once for the whole branch, not per course: product
     * name => price.
     *
     * @var array<string, float>
     */
    public const array SINGLE_PRODUCTS = [
        'TESI' => 300.0,
        'ASSISTENZA E TUTORAGGIO' => 500.0,
    ];

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
                '1°ANNO' => 2856.0,
                '2°ANNO' => 2856.0,
                '3°ANNO' => 2856.0,
                'FORM' => 1500.0,
            ],
            'courses' => [
                '[L-7] Ingegneria Civile e Ambientale' => 'Ingegneria',
                '[L-8] Ingegneria Informatica e dell\'Automazione' => 'Ingegneria',
                '[L-9] Ingegneria Industriale' => 'Ingegneria',
                '[L-10] Letteratura, Arte, Musica e Spettacolo' => 'Letteratura',
                '[L-11] Lingue e Culture Europee e del Resto del Mondo' => 'Letteratura',
                '[L-3] Design e Discipline della Moda' => 'Letteratura',
                '[L-13] Scienze Biologiche' => 'Psicologia',
                '[L-19] Scienze dell\'Educazione e della Formazione' => 'Psicologia',
                '[L-22] Scienze delle Attività Motorie e Sportive' => 'Psicologia',
                '[L-24] Scienze e Tecniche Psicologiche' => 'Psicologia',
                '[L-15] Scienze del Turismo per il Management e i Beni Culturali' => 'Economia',
                '[L-33] Economia' => 'Economia',
                '[L-14] Servizi Giuridici' => 'Giurisprudenza',
                '[L-20] Scienze della Comunicazione' => 'Giurisprudenza',
                '[L-36] Scienze Politiche e Sociali' => 'Giurisprudenza',
            ],
        ],
        'Corsi di Laurea Magistrali' => [
            'fees' => [
                '1°ANNO' => 3056.0,
                '2°ANNO' => 3056.0,
                'FORM' => 1500.0,
            ],
            'courses' => [
                '[LM-14] Letteratura, Lingua e Cultura Italiana' => 'Letteratura',
                '[LM-37] Lingue e Letterature Moderne e Traduzione Interculturale' => 'Letteratura',
                '[LM-23] Ingegneria Civile' => 'Ingegneria',
                '[LM-32] Ingegneria Informatica e dell\'Automazione' => 'Ingegneria',
                '[LM-33] Ingegneria Industriale' => 'Ingegneria',
                '[LM-51] Psicologia' => 'Psicologia',
                '[LM-67] Scienze dell\'Esercizio Fisico per il Benessere e la Salute' => 'Psicologia',
                '[LM-85] Scienze Pedagogiche' => 'Psicologia',
                '[LM-61] Scienze della Nutrizione Umana' => 'Psicologia',
                '[LM-56] Scienze dell\'Economia' => 'Economia',
            ],
        ],
    ];
}
