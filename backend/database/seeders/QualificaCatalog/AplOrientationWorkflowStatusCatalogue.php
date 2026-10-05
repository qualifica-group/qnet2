<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "stati pratica" of the APL orientation practices (Orientamento / SFL
 * GOL), transcribed from the "Campi Misure APL" sheet (user directive
 * 2026-10-05). Pure data: a SECTION of WorkflowStatusCatalogue, bound through
 * AplPracticeCatalogue like the other APL practices.
 *
 * Matched on the EXACT category: a direct-category criterion outranks the
 * "APL" branch workflow (spec 0092 D-3). Each APL practice keeps its own list
 * because the lists differ (user directive 2026-10-05: "se gli stati sono gli
 * stessi unisci, altrimenti ognuno il proprio").
 *
 * The sheet carries no legend: a first classification, to be refined with
 * the client. "Da convocare" is the open entry point (pinned open row), every
 * step of the path is pending, "Fine pratica" the positive outcome and
 * "Perso" the loss (the pinned closed rows). No "Non risponde": a practice
 * list, like the internships' and the apprenticeships'.
 */
final class AplOrientationWorkflowStatusCatalogue
{
    /**
     * The key of this section in WorkflowStatusCatalogue::SECTIONS.
     */
    public const string SECTION = 'apl_orientation';

    /**
     * @var array<string, array{legend: string, description: string}>
     */
    public const array STATUSES = [
        'Da convocare' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Pratica aperta, utente da convocare.'],
        'Convocato' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Utente convocato, in attesa della presa in carico.'],
        'Presa in carico' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Utente preso in carico: politiche attive da erogare.'],
        'Monitoraggio SFL' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Politiche attive in corso: rinnovo SFL da monitorare mese per mese.'],
        'Fine pratica' => ['legend' => WorkflowStatusCatalogue::POSITIVE, 'description' => 'Politiche erogabili esaurite: pratica conclusa.'],
        'Perso' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Pratica persa.'],
    ];
}
