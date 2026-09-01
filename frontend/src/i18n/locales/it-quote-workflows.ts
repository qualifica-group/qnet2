/**
 * Dominio Configuratore workflow offerta (spec 0047, spostato dall'Opportunità
 * all'Offerta dalla spec 0083 D-6). File affiancato per mantenere `it.ts`
 * entro i limiti dimensionali (vedi `.claude/rules/engineering.md` §6). Un
 * workflow è una dimensione NUOVA e distinta dalla pipeline di vendita:
 * risolto per criteri su ogni offerta, ognuna con un proprio set di "stati di
 * lavorazione" (una riga "aperta" fissa in testa, una riga "chiusa" fissa in
 * coda, e righe personalizzate riordinabili in mezzo).
 */

export const quoteWorkflows = {
  title: 'Configuratore workflow',
  subtitle: 'Sfoglia, filtra e gestisci i workflow di stati di lavorazione applicati alle offerte.',
  forbidden: 'Non hai i permessi per visualizzare i workflow offerta.',
  columns: {
    name: 'Nome',
    criteriaFields: 'Campi criterio',
    criteriaValues: 'Valori criterio',
    statusesCount: 'Stati',
    isActive: 'Attivo',
    updatedAt: 'Aggiornato il',
  },
  criterionFields: {
    source_id: 'Fonte',
    business_function_id: 'Funzione aziendale',
    product_category_id: 'Categoria prodotto',
    /** Spec 0092: match sulla categoria della riga offerta o su un suo antenato. */
    product_category_branch_id: 'Categoria prodotto (ramo)',
    /** Spec 0083 (D-7): hint shown next to a criterion field resolved from the parent Opportunity. */
    inheritedHint: "Ereditato dall'opportunità",
  },
  detail: {
    title: 'Dettaglio workflow offerta',
    subtitle: 'Visualizzazione in sola lettura del workflow selezionato.',
    loadError: 'Impossibile caricare il workflow offerta. Riprova.',
    active: 'Attivo',
    inactive: 'Non attivo',
    criteriaTitle: 'Criteri',
    statusesTitle: 'Stati',
    createdAt: 'Creato il',
  },
  form: {
    newQuoteWorkflow: 'Nuovo workflow',
    createTitle: 'Crea workflow offerta',
    createSubtitle: 'Definisci un nuovo workflow di stati di lavorazione per le offerte.',
    editTitle: 'Modifica workflow offerta',
    editSubtitle: 'Aggiorna il workflow offerta selezionato.',
    name: 'Nome',
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Workflow offerta creato con successo.',
    updated: 'Workflow offerta aggiornato con successo.',
    deleted: 'Workflow offerta eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il workflow offerta. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo workflow offerta.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome e stato attivo del workflow.',
      },
      criteria: {
        title: 'Criteri',
        description: 'Il workflow si applica alle offerte che soddisfano TUTTI questi criteri.',
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
    subtitle: 'Stati applicati alle offerte che non corrispondono a nessun workflow attivo.',
    loadError: 'Impossibile caricare gli stati di default. Riprova.',
    saved: 'Stati di default aggiornati con successo.',
    forbidden: 'Non puoi aggiornare gli stati di default.',
    genericError: 'Impossibile aggiornare gli stati di default. Riprova.',
  },
}
