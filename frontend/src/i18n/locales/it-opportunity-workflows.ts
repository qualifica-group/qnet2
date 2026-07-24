/**
 * Dominio Configuratore workflow opportunità (spec 0047, Lane C). File
 * affiancato per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Un workflow è una dimensione NUOVA e
 * distinta da `opportunityStatuses` (la pipeline di vendita): risolto per
 * criteri su ogni opportunità, ognuno con un proprio set di "stati di
 * lavorazione" (una riga "aperta" fissa in testa, una riga "chiusa" fissa in
 * coda, e righe personalizzate riordinabili in mezzo).
 */

export const opportunityWorkflows = {
  title: 'Configuratore workflow',
  subtitle: 'Sfoglia, filtra e gestisci i workflow di stati di lavorazione applicati alle opportunità.',
  forbidden: 'Non hai i permessi per visualizzare i workflow opportunità.',
  columns: {
    name: 'Nome',
    criteriaFields: 'Campi criterio',
    criteriaValues: 'Valori criterio',
    statusesCount: 'Stati',
    isActive: 'Attivo',
    updatedAt: 'Aggiornato il',
  },
  criterionFields: {
    state_id: 'Regione',
    source_id: 'Fonte',
    business_function_id: 'Funzione aziendale',
    product_category_id: 'Categoria prodotto',
  },
  detail: {
    title: 'Dettaglio workflow opportunità',
    subtitle: 'Visualizzazione in sola lettura del workflow selezionato.',
    loadError: 'Impossibile caricare il workflow opportunità. Riprova.',
    active: 'Attivo',
    inactive: 'Non attivo',
    criteriaTitle: 'Criteri',
    statusesTitle: 'Stati',
    createdAt: 'Creato il',
  },
  form: {
    newOpportunityWorkflow: 'Nuovo workflow',
    createTitle: 'Crea workflow opportunità',
    createSubtitle: 'Definisci un nuovo workflow di stati di lavorazione per le opportunità.',
    editTitle: 'Modifica workflow opportunità',
    editSubtitle: 'Aggiorna il workflow opportunità selezionato.',
    name: 'Nome',
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Workflow opportunità creato con successo.',
    updated: 'Workflow opportunità aggiornato con successo.',
    deleted: 'Workflow opportunità eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il workflow opportunità. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo workflow opportunità.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome e stato attivo del workflow.',
      },
      criteria: {
        title: 'Criteri',
        description: 'Il workflow si applica alle opportunità che soddisfano TUTTI questi criteri.',
      },
      statuses: {
        title: 'Stati di lavorazione',
        description: 'Gli stati di lavorazione di questo workflow. "Aperto" resta primo e "Chiuso" resta ultimo.',
      },
    },
    criteria: {
      field: 'Campo',
      fieldPlaceholder: 'Seleziona un campo…',
      value: 'Valore',
      valuePlaceholder: 'Seleziona un valore…',
      valueSearchPlaceholder: 'Cerca…',
      valueEmpty: 'Nessuna opzione trovata.',
      valueError: 'Impossibile caricare le opzioni.',
      add: 'Aggiungi criterio',
      remove: 'Rimuovi criterio',
      required: 'È richiesto almeno un criterio.',
      fieldRequired: 'Seleziona un campo.',
      valueRequired: 'Seleziona un valore.',
      duplicateField: 'Questo campo è già usato da un altro criterio.',
    },
    statuses: {
      name: 'Nome stato',
      description: 'Descrizione dello stato',
      descriptionPlaceholder: 'Spiega quando si applica questo stato…',
      descriptionHint: 'Mostra la descrizione dello stato',
      requiresNote: 'Richiede una nota esplicativa',
      requiresNoteBadge: 'Nota richiesta',
      dragHandleLabel: 'Trascina per riordinare',
      add: 'Aggiungi stato',
      remove: 'Rimuovi stato',
      nameRequired: 'Ogni stato richiede un nome.',
      defaultOpenName: 'Aperto',
      defaultValidatedName: 'Validato',
      defaultClosedWonName: 'Chiuso con esito positivo',
      defaultClosedLostName: 'Chiuso con esito negativo',
      group: {
        label: 'Gruppo',
        open: 'Aperto',
        pending: 'In pending',
        validated: 'Validato',
        closed_won: 'Chiuso con esito positivo',
        closed_lost: 'Chiuso con esito negativo',
      },
    },
  },
  defaultStatuses: {
    openButton: 'Stati di default',
    title: 'Stati di default globali',
    subtitle: 'Stati applicati alle opportunità che non corrispondono a nessun workflow attivo.',
    loadError: 'Impossibile caricare gli stati di default. Riprova.',
    saved: 'Stati di default aggiornati con successo.',
    forbidden: 'Non puoi aggiornare gli stati di default.',
    genericError: 'Impossibile aggiornare gli stati di default. Riprova.',
  },
}
