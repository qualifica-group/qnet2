/**
 * Dominio Configuratore Stati Commessa (spec 0322): gemello del configuratore
 * stati offerta con gruppi propri (aperta, in lavorazione, completata, chiusa)
 * e la percentuale di avanzamento per stato. File affiancato per mantenere
 * `it.ts` entro i limiti dimensionali (vedi `.claude/rules/engineering.md` §6).
 */

export const workOrderWorkflows = {
  title: 'Configuratore Stati Commessa',
  subtitle: 'Sfoglia, filtra e gestisci i set di stati di lavorazione applicati alle commesse.',
  forbidden: 'Non hai i permessi per visualizzare i set di stati commessa.',
  columns: {
    name: 'Nome',
    criteriaFields: 'Campi criterio',
    criteriaValues: 'Valori criterio',
    statusesCount: 'Stati',
    isActive: 'Attivo',
    updatedAt: 'Aggiornato il',
  },
  criterionFields: {
    business_function_id: 'Funzione aziendale',
    product_category_id: 'Categoria prodotto',
    product_category_branch_id: 'Categoria prodotto (ramo)',
  },
  detail: {
    title: 'Dettaglio set di stati commessa',
    subtitle: 'Visualizzazione del set di stati selezionato.',
    loadError: 'Impossibile caricare il set di stati commessa. Riprova.',
    active: 'Attivo',
    inactive: 'Non attivo',
    criteriaTitle: 'Criteri',
    statusesTitle: 'Stati',
    createdAt: 'Creato il',
  },
  form: {
    newWorkOrderWorkflow: 'Nuovo set di stati',
    createTitle: 'Crea set di stati commessa',
    createSubtitle: 'Definisci un nuovo set di stati di lavorazione per le commesse.',
    name: 'Nome',
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Set di stati commessa creato con successo.',
    updated: 'Set di stati commessa aggiornato con successo.',
    deleted: 'Set di stati commessa eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 255 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il set di stati commessa. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo set di stati commessa.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'Il set di stati commessa non è ancora stato creato: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dettagli',
      },
      criteria: {
        title: 'Criteri',
        description: 'Il set si applica alle commesse che soddisfano TUTTI questi criteri.',
      },
      statuses: {
        title: 'Stati di lavorazione',
        description: 'Gli stati di questo set. "Aperta" resta primo; "Completata" e "Chiusa" restano ultimi.',
      },
    },
    criteria: {
      required: 'È richiesto almeno un criterio.',
      fieldRequired: 'Seleziona un campo.',
      valueRequired: 'Seleziona un valore.',
      duplicateField: 'Questo campo è già usato da un altro criterio.',
    },
    statuses: {
      nameRequired: 'Ogni stato richiede un nome.',
      defaultOpenName: 'Aperta',
      defaultCompletedName: 'Completata',
      defaultClosedName: 'Chiusa',
      group: {
        open: 'Aperta',
        in_progress: 'In lavorazione',
        completed: 'Completata',
        closed: 'Chiusa',
      },
    },
  },
  defaultStatuses: {
    openButton: 'Stati di default',
    title: 'Stati di default globali',
    subtitle: 'Stati applicati alle commesse che non corrispondono a nessun set attivo.',
    loadError: 'Impossibile caricare gli stati di default. Riprova.',
    saved: 'Stati di default aggiornati con successo.',
    forbidden: 'Non puoi aggiornare gli stati di default.',
    genericError: 'Impossibile aggiornare gli stati di default. Riprova.',
  },
}
