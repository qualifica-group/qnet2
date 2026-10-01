<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's e-Campus degree catalogue, transcribed from the "CORSI
 * E-CAMPUS" sheet (user directive 2026-10-01). Pure data, like
 * SelfFundedCourseCatalogue — ECampusCategoryTree builds the nodes, and
 * CatalogProducts files the products on them.
 *
 * The tree hangs under the "Formazione" root: "Corsi E-Campus" (container) >
 * one container per degree level > one selectable leaf per subject area,
 * named "<area> - <degree level>" (ECampusCategoryTree): the areas repeat
 * across the two levels ("Ingegneria" is both a bachelor's and a master's
 * area), and the suffix tells them apart.
 *
 * A course is sold as one SERVICE product per fee of its degree level, named
 * "<course> <fee>" and priced at the fee: the sheet lists each course as
 * "<course>: Online, N anni, Accesso Libero", and the product name stops
 * before the colon. No fee is quoted "+ iva", so no product carries a VAT rate.
 *
 * The sheet's "Corsi di laurea Magistrali a ciclo unico" (Giurisprudenza
 * [LMG/01]) quotes no fee, so it is deliberately not transcribed.
 *
 * Names are user-facing domain values, kept as the sheet spells them.
 */
final class ECampusCourseCatalogue
{
    /**
     * The root the branch hangs under — a node of
     * QualificaCatalogSeeder::CATALOG, bound by identity.
     */
    public const string PARENT = 'Formazione';

    public const string CATEGORY = 'Corsi E-Campus';

    /**
     * Degree level => its fees (product suffix => price) and its subject
     * areas (area => course names).
     *
     * @var array<string, array{fees: array<string, float>, areas: array<string, list<string>>}>
     */
    public const array DEGREES = [
        'Corsi di Laurea Triennali' => [
            'fees' => [
                'PROGETTO FORM' => 1500.0,
                'ASSISTENZA E TUTORAGGIO' => 500.0,
                '1°ANNO' => 2856.0,
                '2°ANNO' => 2856.0,
                '3°ANNO' => 2856.0,
                'TESI' => 300.0,
            ],
            'areas' => [
                'Ingegneria' => [
                    'Ingegneria Civile e Ambientale [L-7]',
                    'Ingegneria Informatica e dell\'Automazione [L-8]',
                    'Ingegneria Industriale [L-9]',
                ],
                'Letteratura' => [
                    'Letteratura, Arte, Musica e Spettacolo [L-10]',
                    'Lingue e Culture Europee e del Resto del Mondo [L-11]',
                    'Design e Discipline della Moda [L-3]',
                ],
                'Psicologia' => [
                    'Scienze Biologiche [L-13]',
                    'Scienze dell\'Educazione e della Formazione [L-19]',
                    'Scienze delle Attività Motorie e Sportive [L-22]',
                    'Scienze e Tecniche Psicologiche [L-24]',
                ],
                'Economia' => [
                    'Scienze del Turismo per il Management e i Beni Culturali [L-15]',
                    'Economia [L-33]',
                ],
                'Giurisprudenza' => [
                    'Servizi Giuridici [L-14]',
                    'Scienze della Comunicazione [L-20]',
                    'Scienze Politiche e Sociali [L-36]',
                ],
            ],
        ],
        'Corsi di Laurea Magistrali' => [
            'fees' => [
                'PROGETTO FORM' => 1500.0,
                'ASSISTENZA E TUTORAGGIO' => 500.0,
                '1°ANNO' => 3056.0,
                '2°ANNO' => 3056.0,
                'TESI' => 300.0,
            ],
            'areas' => [
                'Letteratura' => [
                    'Letteratura, Lingua e Cultura Italiana [LM-14]',
                    'Lingue e Letterature Moderne e Traduzione Interculturale [LM-37]',
                ],
                'Ingegneria' => [
                    'Ingegneria Civile [LM-23]',
                    'Ingegneria Informatica e dell\'Automazione [LM-32]',
                    'Ingegneria Industriale [LM-33]',
                ],
                'Psicologia' => [
                    'Psicologia [LM-51]',
                    'Scienze dell\'Esercizio Fisico per il Benessere e la Salute [LM-67]',
                    'Scienze Pedagogiche [LM-85]',
                    'Scienze della Nutrizione Umana [LM-61]',
                ],
                'Economia' => [
                    'Scienze dell\'Economia [LM-56]',
                ],
            ],
        ],
    ];
}
