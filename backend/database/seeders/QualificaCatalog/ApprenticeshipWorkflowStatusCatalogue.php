<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "stati pratica" of the apprenticeship practices, transcribed from the
 * "Apprendistato - Campi Operatore" sheet (user directive 2026-10-05). Pure
 * data: a SECTION of WorkflowStatusCatalogue, kept in a file of its own
 * because that catalogue sits at its size limit (engineering.md §6).
 *
 * Matched on the EXACT category: the offers sit on the node itself, and a
 * direct-category criterion outranks the "APL" branch workflow (spec 0092
 * D-3), so the rest of the APL branch keeps its own list.
 *
 * The sheet carries no legend: a first classification, to be refined with
 * the client. "Attesa Abilitazione CPI" is the open entry point (pinned open
 * row), every intermediate step is pending, "Pratica conclusa" the positive
 * outcome (pinned closed_won row). The sheet lists no loss, so the pinned
 * closed_lost row keeps the writer's default label. Each description names
 * the sheet's action button that closes the state. No "Non risponde": a
 * practice list, like the internships'.
 */
final class ApprenticeshipWorkflowStatusCatalogue
{
    /**
     * The key of this section in WorkflowStatusCatalogue::SECTIONS.
     */
    public const string SECTION = 'apprenticeships';

    /**
     * @var array<string, array{legend: string, description: string}>
     */
    public const array STATUSES = [
        'Attesa Abilitazione CPI' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Pratica aperta, in attesa dell\'abilitazione del CPI (si chiude con "CPI abilitato").'],
        'Inserimento Politiche attive' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'CPI abilitato: politiche attive da inserire (si chiude con "Politiche attive inserite").'],
        'Attesa Unilav' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'In attesa della comunicazione Unilav (si chiude con "Unilav ricevuto").'],
        'Lavorazione portale apprendistati' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Pratica in lavorazione sul portale apprendistati (si chiude con "Portale completato").'],
        'Attesa firma documenti' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Documenti inviati, in attesa della firma (si chiude con "Documenti firmati").'],
        'Pratica conclusa' => ['legend' => WorkflowStatusCatalogue::POSITIVE, 'description' => 'Documenti firmati: pratica conclusa.'],
    ];
}
