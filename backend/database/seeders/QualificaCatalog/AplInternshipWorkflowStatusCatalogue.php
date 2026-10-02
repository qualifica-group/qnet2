<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "stati pratica" of the APL extracurricular internships, transcribed
 * from the "Campi Misure APL" sheet (user directive 2026-10-02). Pure data: a
 * SECTION of WorkflowStatusCatalogue, kept in a file of its own because that
 * catalogue sits at its size limit (engineering.md §6).
 *
 * Matched on the EXACT category: the offers sit on the node itself, and a
 * direct-category criterion outranks the "APL" branch workflow (spec 0092
 * D-3), so the rest of the APL branch keeps its own list.
 *
 * The sheet carries no legend, so the classification is the user's decision
 * of 2026-10-02: "Produzione documentale" is the open entry point, every step
 * up to "Concluso" is pending (a concluded internship still awaits its
 * attestation), "Attestazione finale" the positive outcome, "Interrotto" and
 * "Perso" the losses. Declaration order is the sheet's: each pinned group
 * takes its first state, so "Interrotto" labels the closed_lost row. No "Non
 * risponde", despite the 2026-09-28 "in every list" directive: the user kept
 * the sheet's practice states.
 */
final class AplInternshipWorkflowStatusCatalogue
{
    /**
     * The key of this section in WorkflowStatusCatalogue::SECTIONS.
     */
    public const string SECTION = 'apl_internships';

    /**
     * @var array<string, array{legend: string, description: string}>
     */
    public const array STATUSES = [
        'Produzione documentale' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Raccolta e produzione della documentazione per l\'avvio del tirocinio.'],
        'Vacancy' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Posizione di tirocinio pubblicata dal soggetto ospitante.'],
        'Candidatura' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Candidatura del destinatario inviata sulla posizione.'],
        'Assenso' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Assenso al tirocinio ricevuto, in attesa dell\'attivazione.'],
        'Attivazione politica attiva' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Politica attiva in fase di attivazione.'],
        'Attivo' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Tirocinio in corso.'],
        'Prorogato' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Tirocinio prorogato oltre la data di fine prevista.'],
        'Interrotto' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Tirocinio interrotto prima della data di fine.'],
        'Concluso' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Tirocinio concluso, in attesa dell\'attestazione finale.'],
        'Attestazione finale' => ['legend' => WorkflowStatusCatalogue::POSITIVE, 'description' => 'Attestazione finale rilasciata: pratica chiusa.'],
        'Perso' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Pratica persa: tirocinio non avviato.'],
    ];
}
