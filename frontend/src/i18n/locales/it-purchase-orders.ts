/**
 * Dominio Ordini di Acquisto - ODA (spec 0210). File laterale per tenere
 * `it.ts` entro i limiti dimensionali (vedi `.claude/rules/engineering.md` §6),
 * mirror di `en-purchase-orders.ts`.
 */

export const purchaseOrders = {
  title: 'Ordini di acquisto',
  subtitle: 'Consulta e gestisci gli ordini di acquisto (ODA).',
  forbidden: 'Non hai il permesso di visualizzare gli ordini di acquisto.',
  newOrder: 'Nuova ODA',
  formInDevelopment: "Modulo ordine di acquisto: in fase di sviluppo.",
  columns: {
    id: 'ID',
    code: 'Numero',
    number: 'N.',
    year: 'Anno',
    order_date: 'Data ordine',
    status: 'Stato',
    supplier: 'Fornitore',
    supplier_vat_number: 'P.IVA fornitore',
    company: 'Società',
    company_site: 'Sede società',
    operational_site: 'Sede operativa',
    description: 'Descrizione',
    payment_method: 'Pagamento',
    customer: 'Cliente',
    work_order: 'Commessa',
    quote: 'Contratto/Offerta',
    opportunity: 'Opportunità',
    sectors: 'Settori',
    is_internal_user: 'Fruitore interno',
    taxable_total: 'Imponibile',
    vat_total: 'IVA',
    grand_total: 'Totale',
    lines_count: 'Righe',
    created_by: 'Creato da',
    created_at: 'Creato il',
  },
  tabs: {
    label: 'Filtra per stato',
    all: 'Tutti',
  },
  actions: {
    activity: 'Attività',
    delete: 'Elimina',
    pdf: 'Scarica PDF',
  },
  sections: {
    lines: 'Righe',
  },
  lines: {
    description: 'Descrizione',
    costType: 'Tipo costo',
    unit: 'U.m.',
    quantity: 'Quantità',
    unitPrice: 'Prezzo unitario',
    taxable: 'Imponibile',
    vat: 'IVA',
    total: 'Totale',
  },
  form: {
    loadError: "Impossibile caricare l'ordine di acquisto. Riprova.",
  },
  messages: {
    deleted: 'Ordine di acquisto eliminato.',
    deleteBlocked: 'Si possono eliminare solo ordini in bozza o in validazione.',
    deleteError: "Impossibile eliminare l'ordine di acquisto. Riprova.",
  },
}
