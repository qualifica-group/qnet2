/** Schede "Proforma attiva" / "Proforma passiva" della commessa (spec 0282), annidate in `workOrders.invoices`. */

export const workOrderInvoices = {
  tabs: {
    active: 'Proforma attiva',
    passive: 'Proforma passiva',
  },
  loading: 'Caricamento documenti',
  loadError: 'Impossibile caricare proforma e fatture della commessa.',
  totals: 'Totali',
  columns: {
    type: 'Tipo',
    number: 'Numero',
    date: 'Data',
    externalNumber: 'Numero esterno',
    customer: 'Cliente',
    supplier: 'Fornitore',
    total: 'Totale',
    collected: 'Incassato',
    paymentStatus: 'Stato incasso',
    documentTotal: 'Totale documento',
    workOrderAmount: 'Quota commessa',
    paid: 'Pagato',
    residual: 'Residuo',
  },
  active: {
    caption: 'Proforma e fatture verso il cliente della commessa',
    empty: 'Nessuna proforma o fattura attiva per questa commessa.',
  },
  passive: {
    caption: 'Proforma e fatture dei fornitori con righe sulla commessa',
    empty: 'Nessuna proforma o fattura passiva per questa commessa.',
  },
}
