/**
 * Dominio Richieste Proforma (spec 0193). File affiancato per mantenere `it.ts`
 * entro i limiti dimensionali (vedi `.claude/rules/engineering.md` §6). Copre
 * sia il modulo Contabilità > Attiva sia il pulsante "€" e la modale
 * dell'elenco Commesse.
 */

export const proformaRequests = {
  title: 'Richieste Proforma',
  subtitle: 'Consulta le richieste di emissione proforma inviate alla Contabilità.',
  forbidden: 'Non hai il permesso di visualizzare le richieste proforma.',
  columns: {
    id: 'ID',
    work_order_code: 'Commessa n.',
    work_order_title: 'Titolo commessa',
    company: 'Società',
    kind: 'Tipo',
    supplier: 'Fornitore',
    payment_method: 'Modalità di pagamento',
    status: 'Stato',
    note: 'Note',
    assigned_to: 'Assegnata a',
    assigned_by: 'Richiesta da',
    created_at: 'Richiesta il',
  },
  kinds: {
    consultancy: 'Consulenza',
    institution: 'Ente',
  },
  statuses: {
    pending: 'Da evadere',
    issued: 'Evasa',
  },
  detail: {
    title: 'Richiesta proforma: Commessa #{{code}}',
    sectionTitle: 'Dettagli della richiesta',
    issuedAt: 'Evasa il',
    loadError: 'Impossibile caricare la richiesta. Riprova.',
  },
  form: {
    editTitle: 'Modifica richiesta',
    editSubtitle: 'Puoi modificare solo la nota per la Contabilità.',
    workOrderLine: 'Commessa #{{code}}',
    note: 'Note per la Contabilità',
    noteRequired: 'La nota per la Contabilità è obbligatoria.',
    noteTooLong: 'La nota non può superare {{max}} caratteri.',
    save: 'Salva',
    updated: 'Richiesta aggiornata.',
    genericError: 'Impossibile salvare la richiesta. Riprova.',
    deleted: 'Richiesta eliminata.',
    deleteForbidden: 'Non hai il permesso di eliminare questa richiesta.',
    deleteError: 'Impossibile eliminare la richiesta. Riprova.',
  },
  cell: {
    none: 'Richiedi emissione proforma',
    pending: 'Proforma richiesta: apri la richiesta',
    issued: 'Proforma emessa',
  },
  dialog: {
    title: 'Richiesta emissione Proforma: Commessa #{{code}}',
    description: 'Invia alla Contabilità la richiesta di emissione della proforma per questa commessa.',
    paymentMethod: 'Modalità di pagamento',
    paymentMethodNone: 'Non indicata',
    lastRequest: 'Ultima richiesta del {{date}}',
    close: 'Chiudi',
    send: 'Invia richiesta',
    sent: 'Richiesta di proforma inviata.',
    sendError: 'Impossibile inviare la richiesta. Riprova.',
    loadError: 'Impossibile caricare i dati della commessa. Riprova.',
  },
}
