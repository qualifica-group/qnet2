/**
 * Dominio Stati di sistema offerta (spec 0404, parte A). Gli stati di sistema
 * sono quelli che si vedono in Offerte: configurabili, globali, collegati agli
 * stati di lavorazione dei flussi offerta. Il comportamento (`behavior`) e' la
 * chiave che il codice conosce (contratto su Vinto, Gestione Iscritti su
 * Vinto provvisorio/Vinto).
 */

export const quoteSystemStatuses = {
  title: 'Stati di sistema offerta',
  subtitle: 'Gli stati che si vedono in Offerte, collegati agli stati di lavorazione.',
  forbidden: 'Non hai i permessi per visualizzare gli stati di sistema offerta.',
  columns: {
    name: 'Nome',
    color: 'Colore',
    behavior: 'Comportamento',
    workflowStatusesCount: 'Stati di lavorazione collegati',
    sortOrder: 'Ordine',
    createdAt: 'Creato il',
  },
  advancedFilters: {
    name: 'Nome',
    behavior: 'Comportamento',
    createdRange: 'Creato il',
  },
  detail: {
    title: 'Dettaglio stato di sistema',
    loadError: 'Impossibile caricare lo stato di sistema. Riprova.',
    workflowStatusesCount: 'Stati di lavorazione collegati',
    sortOrder: 'Ordine',
    createdAt: 'Creato il',
  },
  form: {
    newQuoteSystemStatus: 'Nuovo stato',
    createTitle: 'Crea stato di sistema',
    createSubtitle: 'Aggiungi uno stato che si vedra\' in Offerte.',
    name: 'Nome',
    color: 'Colore',
    behavior: {
      label: 'Comportamento',
      hint: 'Decide cosa fa lo stato: su Vinto genera il contratto, su Vinto provvisorio e Vinto compare in Gestione Iscritti. Sugli stati obbligatori (Aperto, Vinto, Perso) e\' fisso.',
      open: 'Aperto',
      pending: 'In attesa',
      validated: 'Vinto provvisorio',
      closed_won: 'Vinto',
      closed_lost: 'Perso',
    },
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato di sistema creato con successo.',
    updated: 'Stato di sistema aggiornato con successo.',
    deleted: 'Stato di sistema eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 100 caratteri.',
    colorRequired: 'Il colore è obbligatorio.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato di sistema. Riprova.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'Il record non è ancora stato creato: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati di sistema',
    subtitle: 'Trascina gli stati per riordinarli. "Aperto" resta primo e "Vinto"/"Perso" restano ultimi.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
