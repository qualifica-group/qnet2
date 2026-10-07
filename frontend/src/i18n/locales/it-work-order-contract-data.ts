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
    consultancy: 'Imponibile {{quantity}} × {{unitPrice}} = {{net}} → ricavo {{revenue}}',
    institutionPercentage: 'Commissione Fornitore {{rate}}% di {{base}} = {{amount}} → ricavo {{revenue}}',
    institutionPercentageNoBase: 'Commissione Fornitore {{rate}}% = {{amount}} → ricavo {{revenue}}',
    institutionFixed: 'Commissione Fornitore fissa {{amount}} → ricavo {{revenue}}',
    institutionMissing: 'Nessuna commissione Fornitore → ricavo {{revenue}}',
    institutionHidden: 'Ricavo Ente {{revenue}}',
    netOfCommissions: 'Netto commissioni: {{net}} − {{commissions}} = {{result}}',
  },
  warnings: {
    missingSupplierCommission: 'Commissione Fornitore mancante: il ricavo Ente è 0,00.',
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
