/**
 * Dati contrattuali di commessa (spec 0201): tab "Dati contrattuali" del
 * dettaglio commessa, annidato in `workOrders.contractData` (file affiancato:
 * `it-work-orders.ts` e' vicino al limite dimensionale, vedi
 * `.claude/rules/engineering.md` §6).
 */

export const workOrderContractData = {
  title: 'Dati contrattuali',
  loading: 'Caricamento dei dati contrattuali',
  loadError: 'Impossibile caricare i dati contrattuali della commessa.',
  empty: 'La commessa non ha righe prodotto.',
  tableCaption: 'Dati contrattuali per riga prodotto: importi, ricavo effettivo e pagamento',
  columns: {
    product: 'Prodotto',
    quantity: 'Q.tà',
    unitPrice: 'Prezzo unitario',
    netAmount: 'Imponibile',
    supplierCommission: 'Commissione Fornitore',
    netOfCommissions: 'Netto commissioni',
    effectiveRevenue: 'Ricavo effettivo',
    payment: 'Pagamento',
  },
  kind: {
    consultancy: 'Consulenza',
  },
  totals: {
    netAmount: 'Imponibile',
    typologyNet: 'Imponibile',
    typologyRevenue: 'Ricavo',
    revenue: 'Totale Ricavi',
    commissions: 'Commissioni',
    netOfCommissions: 'Netto commissioni',
  },
  formula: {
    net: 'Imponibile {{quantity}} × {{unitPrice}} = {{net}} → ricavo {{revenue}}',
    paid: '{{net}} · Commissione Fornitore pagata: {{commission}} (è un costo, non riduce il ricavo)',
    paidPercentage: '{{rate}}% di {{base}} = {{amount}}',
    paidAmount: '{{amount}}',
    received: 'Commissione Fornitore ricevuta: {{commission}} → ricavo {{revenue}}',
    receivedPercentage: '{{rate}}% di {{base}} = {{amount}}',
    receivedAmount: 'fissa {{amount}}',
    receivedMissing: 'Commissione Fornitore ricevuta mancante → ricavo {{revenue}}',
    receivedHidden: 'Commissione Fornitore ricevuta: ricavo {{revenue}}',
    netOfCommissions: 'Netto commissioni: {{net}} − {{commissions}} = {{result}}',
  },
  warnings: {
    missingSupplierCommission: 'Commissione Fornitore ricevuta mancante: il ricavo è 0,00.',
    staleCommissionBase: 'Importo calcolato su una base precedente: salva di nuovo l\'offerta per aggiornarlo.',
  },
  payment: {
    noStatus: 'Nessuno stato',
    unpaid: 'Insoluti',
    edit: 'Modifica il pagamento di {{product}}',
  },
  editor: {
    status: 'Stato pagamento',
    noStatus: 'Nessuno stato',
    agreement: 'Accordo sui pagamenti',
    agreementMax: 'L\'accordo può contenere al massimo 2000 caratteri.',
    unpaid: 'Insoluti',
    done: 'Fatto',
    reset: 'Ripristina',
    saveError: 'Impossibile salvare i dati di pagamento. Riprova.',
  },
}
