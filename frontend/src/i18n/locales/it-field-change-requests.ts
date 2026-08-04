/**
 * Dominio delle richieste di modifica campo (spec 0078): il sistema generico
 * "proponi una modifica a un campo protetto, approva/rifiuta" — oggi l'unico
 * campo protetto è la "Fonte" di Gestione Richieste, ma il dialog, la sezione
 * richieste del record e questo browse dedicato sono tutti agnostici rispetto
 * a risorsa/campo (AC-054). File separato per rientrare nei limiti
 * dimensionali di `it.ts` (vedi `.claude/rules/engineering.md` §6).
 *
 * `columns.*` rispecchiano le 12 colonne congelate di
 * `FieldChangeRequestColumnCatalog` (backend, spec 0078 `data_contract`) — il
 * backend invia queste chiavi testuali come `label` di colonna, risolte qui.
 */

export const fieldChangeRequests = {
  forbidden: 'Non hai il permesso di visualizzare le richieste di modifica.',
  proposeFromPicker: "Scegli un valore per proporre una modifica; serve l'approvazione prima di avere effetto.",
  columns: {
    resource: 'Modulo',
    subject: 'Record',
    field: 'Campo',
    currentValue: 'Valore attuale',
    requestedValue: 'Valore richiesto',
    reason: 'Motivazione',
    requestedBy: 'Richiedente',
    createdAt: 'Data richiesta',
    status: 'Stato',
    handledBy: 'Gestore',
    handledAt: 'Data gestione',
    handlingNote: 'Nota',
  },
  section: {
    title: 'Richieste di modifica',
    description: 'Proposte in attesa di approvazione su questo record.',
    empty: 'Nessuna richiesta di modifica in attesa su questo record.',
    emptyValue: 'Nessuno',
    loadError: 'Impossibile caricare le richieste di modifica. Riprova.',
  },
  detail: {
    record: 'Record',
    field: 'Campo',
    currentValue: 'Valore attuale',
    requestedValue: 'Valore richiesto',
    reason: 'Motivazione',
    requestedBy: 'Richiedente',
    requestedAt: 'Data richiesta',
    handledBy: 'Gestore',
    handledAt: 'Data gestione',
    handlingNote: 'Nota',
    loadError: 'Impossibile caricare la richiesta. Riprova.',
    actions: {
      genericError: 'Qualcosa è andato storto. Riprova.',
      approved: 'Richiesta approvata.',
      rejected: 'Richiesta rifiutata.',
      approve: 'Approva',
      reject: 'Rifiuta',
      approveTitle: 'Approvare la richiesta?',
      rejectTitle: 'Rifiutare la richiesta?',
      approveDescription: 'La modifica proposta verrà applicata. Puoi aggiungere una nota facoltativa.',
      rejectDescription: 'La modifica proposta non verrà applicata. Puoi aggiungere una nota facoltativa.',
      noteLabel: 'Nota',
      submitting: 'Salvataggio…',
      confirm: 'Conferma',
    },
  },
  dialog: {
    genericError: 'Qualcosa è andato storto. Riprova.',
    success: 'Richiesta di modifica inviata.',
    title: 'Proponi una modifica a {{field}}',
    description: "Scegli un nuovo valore per {{field}}; serve l'approvazione prima di avere effetto.",
    current: 'Attuale',
    requested: 'Proposto',
    emptyValue: 'Nessuno',
    reasonLabel: 'Perché vuoi cambiare {{field}}?',
    reasonMax: 'Il motivo non può superare i 1000 caratteri.',
    cancel: 'Annulla',
    submitting: 'Invio…',
    submit: 'Invia richiesta',
  },
}
