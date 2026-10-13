/**
 * Fatture passive (spec 0250): elenco, scheda dettaglio, eliminazione e azione
 * "Registra fattura" dall’ODA. Le stringhe dell’editor e del dialog dettagli
 * stanno in `it-purchase-invoices.ts`. File affiancato per rispettare i limiti
 * dimensionali (`engineering.md` §6).
 */

export const purchaseInvoiceViews = {
  title: 'Fatture passive',
  subtitle: 'Fatture e proforma dei fornitori con righe, scadenze, ODA collegati e documenti.',
  forbidden: 'Non hai il permesso di visualizzare le fatture passive.',
  newInvoice: 'Nuova fattura passiva',
  columns: {
    document_type: 'Tipo',
    document_number: 'Numero fornitore',
    document_date: 'Data documento',
    supplier: 'Fornitore',
    company: 'Società',
    purchase_orders: 'ODA collegati',
    net_amount: 'Imponibile',
    vat_amount: 'IVA',
    total_amount: 'Totale',
    paid_amount: 'Pagato',
    residual_amount: 'Residuo',
    payment_status: 'Stato pagamento',
  },
  actions: {
    view: 'Visualizza',
    update: 'Modifica',
    details: 'Dettagli fornitore',
    delete: 'Elimina',
    activity: 'Attività',
  },
  detail: {
    title: 'Fattura {{number}}',
    titleProforma: 'Proforma passivo',
    loadError: 'Impossibile caricare il documento. Riprova.',
    edit: 'Modifica',
    details: 'Dettagli fornitore',
    delete: 'Elimina',
    header: 'Testata',
    totals: 'Totali',
    notes: 'Note',
    fields: {
      document_date: 'Data documento',
      company: 'Società',
      supplier: 'Fornitore',
      payment_method: 'Modalità di pagamento',
      financial_account: 'Conto',
      company_site: 'Sede aziendale',
      operational_site: 'Sede operativa',
      cost_type: 'Tipo di costo predefinito',
      work_order: 'Commessa predefinita',
      created_by: 'Registrata da',
    },
    lines: {
      title: 'Righe',
      empty: 'Nessuna riga.',
      columns: {
        description: 'Descrizione',
        order: 'ODA',
        quantity: 'Quantità',
        unit_price: 'Prezzo',
        vat_rate: 'IVA',
        cost_type: 'Tipo di costo',
        work_order: 'Commessa',
        net_amount: 'Imponibile',
        vat_amount: 'IVA',
        total_amount: 'Totale',
      },
    },
    installments: {
      title: 'Scadenze',
      empty: 'Nessuna scadenza.',
      columns: {
        sequence: 'N.',
        due_date: 'Scadenza',
        amount: 'Importo',
        payment_method_code: 'Codice modalità',
        status: 'Stato',
        paid_amount: 'Pagato',
        paid_at: 'Data pagamento',
      },
    },
    orders: {
      title: 'ODA collegati',
      empty: 'Nessun ODA collegato.',
    },
    documents: {
      title: 'Documenti',
    },
  },
  delete: {
    title: 'Eliminare il documento?',
    description: 'Il documento {{number}} verrà eliminato con righe, scadenze e allegati. L’operazione non è reversibile.',
    confirm: 'Elimina',
    cancel: 'Annulla',
    deleted: 'Documento eliminato.',
    locked: 'Impossibile eliminare: una fattura registrata o un documento con pagamenti non si elimina.',
    genericError: 'Impossibile eliminare il documento. Riprova.',
  },
  registerInvoice: {
    button: 'Registra fattura',
    tooltip: 'Registra la fattura passiva di questo ordine',
  },
}
