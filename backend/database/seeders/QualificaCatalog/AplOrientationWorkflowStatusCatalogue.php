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
 * The client prepended the APL sector's call-centre states, classified by the
 * sheet's fill (user directive 2026-10-09): "Da Richiamare" is now the open
 * entry point (pinned open row) and "Associato NO _ Altro Ente" the first loss
 * (pinned closed_lost row), every pesca cell a NEGATIVE outcome. "Da
 * convocare" follows "Doppione già associato" and is VALIDATED: it hands the
 * contact over to an APL operator. "Fine pratica" stays the positive outcome.
 * No "Non risponde": a practice list, like the internships' and the
 * apprenticeships'. A set already seeded is left as it stands: the change
 * reaches a fresh seed only (same directive).
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
        'Da Richiamare' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Contatto da ricontattare per completare la lavorazione o fornire ulteriori informazioni.'],
        'Attesa esito SFL/ADI' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'In attesa dell\'esito relativo alla pratica SFL/ADI del candidato.'],
        'Attesa _ App. CPI' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'In attesa della definizione dell\'appuntamento presso il CPI.'],
        'OK App. Fissato CPI' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Appuntamento presso CPI fissato e confermato.'],
        'Attesa Documenti' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'In attesa della ricezione della documentazione necessaria per procedere con la gestione della pratica.'],
        'Associato NO _ Altro Ente' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato associato a un altro ente diverso da NOI.'],
        'NO _ Non ha Requisiti' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato non idoneo per mancanza dei requisiti previsti.'],
        'Non interessato/a' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato che ha comunicato di non essere interessato al percorso.'],
        'Irreperibile' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Impossibile contattare il candidato dopo i tentativi effettuati.'],
        'Non pertinente - Altra regione' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato non pertinente perché appartenente a un\'altra regione in cui non siamo accreditati.'],
        'Numero Inesistente/Errato' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Recapito telefonico non valido o inesistente.'],
        'Doppione' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Record duplicato presente nel sistema.'],
        'Doppione già associato' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Record duplicato già collegato a un\'associazione esistente.'],
        'Da convocare' => ['legend' => WorkflowStatusCatalogue::VALIDATED, 'description' => 'Contatto validato, da assegnare a un operatore APL per la convocazione.'],
        'Convocato' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Utente convocato, in attesa della presa in carico.'],
        'Presa in carico' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Utente preso in carico: politiche attive da erogare.'],
        'Monitoraggio SFL' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Politiche attive in corso: rinnovo SFL da monitorare mese per mese.'],
        'Fine pratica' => ['legend' => WorkflowStatusCatalogue::POSITIVE, 'description' => 'Politiche erogabili esaurite: pratica conclusa.'],
        'Perso' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Pratica persa.'],
    ];
}
