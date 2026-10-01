<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "stati di lavorazione" of the e-Campus branch, transcribed from the
 * STATO column of the "BOZZA gestionale_Progetto Università" sheet (user
 * directive 2026-10-01). Pure data: a SECTION of WorkflowStatusCatalogue,
 * kept in a file of its own because that catalogue sits at its size limit
 * (engineering.md §6).
 *
 * Classified with the Formazione logic, the sheet carrying no legend of its
 * own: each state takes the bucket its namesake has in the AUTOFINANZIATO
 * section — the other block selling a course straight to the learner — and
 * the states that block lacks take the closest one ("Attesa Prevalutazione"
 * is a pending outcome, "Non ha i Requisiti" a loss as in AUTOIMPIEGO).
 * "ISCRITTO" is the positive outcome.
 *
 * TRANSCRIPTION NOTES, as in WorkflowStatusCatalogue:
 *   - "Nuovo Contatto" leads the list although the sheet omits it: it leads
 *     every Formazione list and labels the pinned open row (user directive
 *     2026-09-18).
 *   - "NR" is folded to "Non risponde", the spelling every list carries and
 *     classifies as open (user directive 2026-09-28).
 *   - "Da richiamare", "Non ha i requisiti" and the en dash of "Esito negativo
 *     – ..." are folded to the canonical spellings of the other sections.
 *
 * Declaration order is the sheet's, and so the seeded order: the first state
 * of each pinned group takes that system row ("Nuovo Contatto", "ISCRITTO",
 * "Non attinente").
 */
final class ECampusWorkflowStatusCatalogue
{
    /**
     * The key of this section in WorkflowStatusCatalogue::SECTIONS.
     */
    public const string SECTION = 'e_campus';

    /**
     * @var array<string, array{legend: string, description: string}>
     */
    public const array STATUSES = [
        'Nuovo Contatto' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Nuovo contatto acquisito, non ancora lavorato.'],
        'Da Richiamare' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Contatto da ricontattare per completare la lavorazione o fornire ulteriori informazioni sul corso di laurea.'],
        'In trattativa' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'Candidato in fase di valutazione della proposta, con contatti e approfondimenti ancora in corso prima della definizione dell\'esito.'],
        'Non attinente' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Contatto o candidato non coerente con il corso di laurea o la proposta prevista.'],
        'Non ha i Requisiti' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato non idoneo in quanto non possiede i requisiti di accesso al corso di laurea.'],
        'Non risponde' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Tentativi di contatto effettuati senza ricevere risposta dal candidato.'],
        'Doppione' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Anagrafica o contatto duplicato già presente nel sistema.'],
        'Numero inesistente' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Recapito telefonico errato, inesistente o non valido.'],
        'Irreperibile' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato non raggiungibile dopo diversi tentativi di contatto tramite i recapiti disponibili.'],
        'Esito negativo - prezzo' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato che non ha aderito per motivazioni legate al costo del corso di laurea.'],
        'Esito negativo - altri motivi' => ['legend' => WorkflowStatusCatalogue::NEGATIVE, 'description' => 'Candidato che non ha aderito per motivazioni diverse dal prezzo.'],
        'Appuntamento' => ['legend' => WorkflowStatusCatalogue::OPEN, 'description' => 'Appuntamento fissato con il candidato per approfondire la proposta o procedere con la fase successiva.'],
        'ISCRITTO' => ['legend' => WorkflowStatusCatalogue::POSITIVE, 'description' => 'Candidato che ha completato l\'iscrizione al corso di laurea.'],
        'Attesa Prevalutazione' => ['legend' => WorkflowStatusCatalogue::PENDING, 'description' => 'In attesa dell\'esito della prevalutazione della carriera del candidato da parte dell\'ateneo.'],
    ];
}
