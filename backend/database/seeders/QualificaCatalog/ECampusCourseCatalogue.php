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
     * Degree level => its fees (product suffix => price) and its course names.
     *
     * @var array<string, array{fees: array<string, float>, courses: list<string>}>
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
                'Ingegneria Civile e Ambientale [L-7]',
                'Ingegneria Informatica e dell\'Automazione [L-8]',
                'Ingegneria Industriale [L-9]',
                'Letteratura, Arte, Musica e Spettacolo [L-10]',
                'Lingue e Culture Europee e del Resto del Mondo [L-11]',
                'Design e Discipline della Moda [L-3]',
                'Scienze Biologiche [L-13]',
                'Scienze dell\'Educazione e della Formazione [L-19]',
                'Scienze delle Attività Motorie e Sportive [L-22]',
                'Scienze e Tecniche Psicologiche [L-24]',
                'Scienze del Turismo per il Management e i Beni Culturali [L-15]',
                'Economia [L-33]',
                'Servizi Giuridici [L-14]',
                'Scienze della Comunicazione [L-20]',
                'Scienze Politiche e Sociali [L-36]',
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
                'Letteratura, Lingua e Cultura Italiana [LM-14]',
                'Lingue e Letterature Moderne e Traduzione Interculturale [LM-37]',
                'Ingegneria Civile [LM-23]',
                'Ingegneria Informatica e dell\'Automazione [LM-32]',
                'Ingegneria Industriale [LM-33]',
                'Psicologia [LM-51]',
                'Scienze dell\'Esercizio Fisico per il Benessere e la Salute [LM-67]',
                'Scienze Pedagogiche [LM-85]',
                'Scienze della Nutrizione Umana [LM-61]',
                'Scienze dell\'Economia [LM-56]',
            ],
        ],
    ];
}
