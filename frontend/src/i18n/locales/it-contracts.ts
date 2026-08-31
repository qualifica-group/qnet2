/**
 * Dominio Contratti (spec 0072). File affiancato per mantenere `it.ts` entro
 * i limiti dimensionali (vedi `.claude/rules/engineering.md` §6). Un
 * contratto e' un'estensione 1-1 di un preventivo "Chiuso positivo": niente
 * viene duplicato (D-1/BR-7), quindi molte etichette qui sotto descrivono
 * dati proiettati dal preventivo, non colonne proprie di `contracts`.
 */

export const contracts = {
  forbidden: 'Non hai i permessi per visualizzare i contratti.',
  columns: {
    code: 'Codice',
    title: 'Titolo',
    registry: 'Cliente',
    opportunity: 'Opportunità',
    commercial: 'Commerciale',
    reporter: 'Segnalatore',
    supervisor: 'Supervisore',
    managers: 'Gestori account',
    contractStatus: 'Stato contratto',
    quoteDate: 'Data offerta',
    acceptedAt: 'Accettazione',
    validatedAt: 'Validazione',
    renewalDate: 'Rinnovo',
    expiryDate: 'Scadenza',
    terminatedAt: 'Disdetta',
    revenueNet: 'Imponibile',
    revenueVat: 'IVA',
    revenueGross: 'Totale',
    alert: 'Avviso',
  },
  alerts: {
    expiring: 'In scadenza',
    renewalDue: 'Da rinnovare',
  },
  advancedFilters: {
    registry: 'Cliente',
    opportunity: 'Opportunità',
    commercial: 'Commerciale',
    supervisor: 'Supervisore',
    contractStatus: 'Stato contratto',
  },
  detail: {
    loadError: 'Impossibile caricare il contratto. Riprova.',
    createdAt: 'Creato il',
    updatedAt: 'Aggiornato il',
    suspended: 'Sospeso',
    suspendedReason: 'L\'offerta è uscita da "Chiuso positivo": stato precedente {{status}}, sospesa il {{date}}.',
    suspendedReasonUnknownStatus: 'stato precedente non disponibile',
    suspendedReasonUnknownDate: 'data non disponibile',
    sections: {
      identity: 'Cliente e opportunità',
      company: 'Società e sedi',
      lifecycle: 'Ciclo di vita',
      payment: 'Documento e pagamento',
    },
    registry: 'Cliente',
    opportunity: 'Opportunità',
    quote: 'Offerta',
    company: 'Società',
    companySite: 'Sede',
    operationalSite: 'Sede operativa',
    paymentMethod: 'Modalità di pagamento',
    quoteDate: 'Data offerta',
    acceptedAt: 'Accettazione',
    validatedAt: 'Validazione',
    renewalDate: 'Rinnovo',
    expiryDate: 'Scadenza',
    terminatedAt: 'Disdetta',
    terminationReason: 'Motivazione disdetta',
    paymentNotes: 'Note di pagamento',
    comments: 'Commenti',
    noOpportunity: "Nessuna opportunità collegata.",
    tabs: {
      contractDocuments: 'Documenti contratto',
      opportunityDocuments: 'Documenti opportunità',
    },
  },
  actions: {
    // Path PIATTI (stringa), consumati da `features/table/row-actions.tsx`
    // via `t(action.label)` — `ContractColumnCatalog::actions()` (backend)
    // usa esattamente questi path come `label` delle azioni di riga
    // (tooltip/aria-label). Devono restare stringhe, mai oggetti: i
    // contenuti dei dialog vivono nei sibling `*Dialog` sotto.
    validate: 'Valida contratto',
    schedule: 'Programma contratto',
    terminate: 'Disdici contratto',
    reactivate: 'Riattiva contratto',
    changeStatus: 'Cambia stato',
    // "Programma" resta a schermo ma disabilitato: l'azione sara' ripensata
    // (direttiva utente 2026-08-31).
    scheduleUnavailable: 'Non ancora disponibile',

    statusSearch: 'Cerca stato…',
    statusPlaceholder: 'Seleziona uno stato',
    statusEmpty: 'Nessuno stato trovato.',
    statusError: 'Impossibile caricare gli stati. Riprova.',
    changeStatusDialog: {
      description: 'Sposta il contratto su un altro stato di lavorazione (Aperto o Pending).',
      status: 'Nuovo stato',
      statusRequired: 'Il nuovo stato è obbligatorio.',
      confirm: 'Salva',
      saving: 'Salvataggio…',
      success: 'Stato del contratto aggiornato.',
      genericError: 'Impossibile aggiornare lo stato del contratto. Riprova.',
    },
    validateDialog: {
      description: 'Registra la data di validazione: il contratto passa allo stato «Validato», salvo scelta diversa.',
      date: 'Data di validazione',
      status: 'Stato di destinazione (chiusura positiva)',
      confirm: 'Valida',
      saving: 'Validazione…',
      success: 'Contratto validato con successo.',
      genericError: 'Impossibile validare il contratto. Riprova.',
      dateRequired: 'La data di validazione è obbligatoria.',
      dateFuture: 'La data di validazione non può essere futura.',
    },
    scheduleDialog: {
      description: 'Imposta la data di scadenza, il rinnovo e lo stato di destinazione.',
      expiryDate: 'Data di scadenza',
      renewalDate: 'Data di rinnovo',
      status: 'Stato di destinazione',
      confirm: 'Programma',
      saving: 'Salvataggio…',
      success: 'Contratto programmato con successo.',
      genericError: 'Impossibile programmare il contratto. Riprova.',
      expiryRequired: 'La data di scadenza è obbligatoria.',
      statusRequired: 'Lo stato di destinazione è obbligatorio.',
      renewalAfterExpiry: 'La data di rinnovo non può essere successiva alla scadenza.',
    },
    terminateDialog: {
      description: 'Registra la data e la motivazione della disdetta.',
      date: 'Data di disdetta',
      reason: 'Motivazione',
      status: 'Stato di destinazione',
      confirm: 'Disdici',
      saving: 'Salvataggio…',
      success: 'Contratto disdetto con successo.',
      genericError: 'Impossibile disdire il contratto. Riprova.',
      dateRequired: 'La data di disdetta è obbligatoria.',
      dateFuture: 'La data di disdetta non può essere futura.',
      reasonRequired: 'La motivazione è obbligatoria.',
      reasonMax: 'La motivazione può contenere al massimo 2000 caratteri.',
    },
    // Nessuna collisione col backend: la riga di tabella "edit" usa la
    // chiave condivisa `actions.edit`, non `contracts.actions.edit`.
    edit: {
      title: 'Modifica dati',
      description: 'Aggiorna le date e le note del contratto.',
      expiryDate: 'Data di scadenza',
      renewalDate: 'Data di rinnovo',
      paymentNotes: 'Note di pagamento',
      comments: 'Commenti',
      confirm: 'Salva',
      saving: 'Salvataggio…',
      success: 'Contratto aggiornato con successo.',
      genericError: 'Impossibile aggiornare il contratto. Riprova.',
    },
    reactivateDialog: {
      // Percorso SOSPESO: conferma inline, nessuna scelta da fare.
      description: 'Il contratto tornerà allo stato precedente alla sospensione.',
      // Percorso DISDETTO: dialog con scelta dello stato di destinazione.
      terminatedDescription:
        'La disdetta verrà annullata (data, motivazione e autore) e il contratto ripartirà dallo stato scelto.',
      status: 'Stato di ripartenza',
      statusRequired: 'Lo stato di ripartenza è obbligatorio.',
      confirm: 'Riattiva',
      saving: 'Riattivazione…',
      success: 'Contratto riattivato con successo.',
      genericError: 'Impossibile riattivare il contratto. Riprova.',
    },
  },
}
