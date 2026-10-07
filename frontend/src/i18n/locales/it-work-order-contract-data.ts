/**
 * Dati contrattuali di commessa (spec 0201): blocco dati contrattuali della
 * card del dettaglio commessa, annidato in `workOrders.contractData` (file affiancato:
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
    actions: 'Azioni',
  },
  /** Abbreviated headers of the wide table; the full label stays as accessible name and tooltip. */
  columnsShort: {
    unitPrice: 'Prezzo',
    supplierCommission: 'Comm. Fornitore',
    netOfCommissions: 'Netto comm.',
    effectiveRevenue: 'Ricavo',
  },
  kind: {
    consultancy: 'Consulenza',
  },
  totals: {
    netAmount: 'Imponibile',
    revenue: 'Totale Ricavi',
    commissions: 'Commissioni',
    netOfCommissions: 'Netto commissioni',
    byTypology: 'Ricavo per tipologia (imponibile → ricavo)',
  },
  hint: {
    netAmountRule: 'Quantità × prezzo unitario.',
    netAmountFormula: '{{quantity}} × {{unitPrice}} = {{net}}',
    commissionTitle: {
      RECEIVED: 'Commissione Fornitore ricevuta',
      PAID: 'Commissione Fornitore pagata',
    },
    commissionPercentage: '{{rate}}% di {{base}} = {{amount}}',
    commissionFixed: 'Importo fisso: {{amount}}',
    commissionBase: 'La base è il margine della riga.',
    netOfCommissionsRule: 'Imponibile meno tutte le commissioni della riga.',
    netOfCommissionsFormula: '{{net}} − {{commissions}} = {{result}}',
    revenueReceived: 'Commissione Fornitore ricevuta: il ricavo è la commissione, non l\'imponibile.',
    revenuePaid: 'Commissione Fornitore pagata: il ricavo è l\'imponibile, la commissione è un costo.',
    revenueNone: 'Commissione Fornitore non calcolata: il ricavo è l\'imponibile.',
    revenueFrom: '{{detail}} → ricavo {{revenue}}',
    revenueOnly: 'Ricavo {{revenue}}',
    receivedMissing: 'Commissione mancante → ricavo {{revenue}}',
    totals: {
      netAmount: 'Somma degli imponibili di tutte le righe.',
      revenue: 'Somma dei ricavi effettivi delle righe: commissione ricevuta, altrimenti imponibile.',
      commissions: 'Somma delle commissioni di tutte le righe.',
      netOfCommissions: 'Imponibile meno commissioni.',
    },
  },
  warnings: {
    title: 'Avviso',
    missingSupplierCommission: 'Commissione Fornitore ricevuta mancante: il ricavo è 0,00.',
    staleCommissionBase: 'Importo calcolato su una base precedente: salva di nuovo l\'offerta per aggiornarlo.',
  },
  payment: {
    noStatus: 'Nessuno stato',
    unpaid: 'Insoluto',
    edit: 'Modifica il pagamento di {{product}}',
  },
  editor: {
    title: 'Pagamento riga: {{product}}',
    description: 'Stato di pagamento, accordo e insoluti della riga.',
    status: 'Stato pagamento',
    noStatus: 'Nessuno stato',
    agreement: 'Accordo sui pagamenti',
    agreementMax: 'L\'accordo può contenere al massimo 2000 caratteri.',
    unpaid: 'Insoluti',
    save: 'Salva',
    saveError: 'Impossibile salvare i dati di pagamento. Riprova.',
  },
}
