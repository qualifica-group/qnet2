/**
 * Dati contrattuali di commessa (spec 0201): blocco dati contrattuali della
 * card del dettaglio commessa, annidato in `workOrders.contractData` (file affiancato:
 * `it-work-orders.ts` e' vicino al limite dimensionale, vedi
 * `.claude/rules/engineering.md` §6).
 */

export const workOrderContractData = {
  title: 'Dati contrattuali commessa',
  loading: 'Caricamento dei dati contrattuali',
  loadError: 'Impossibile caricare i dati contrattuali della commessa.',
  empty: 'La commessa non ha righe prodotto.',
  fields: {
    title: 'Titolo',
    amount: 'Importo',
    paymentStatus: 'Stato Pagamento',
    document: 'Documento',
    noDocument: 'Nessuna proforma emessa',
    agreement: 'Accordo sui Pagamenti',
    unpaid: 'Insoluti',
  },
  kind: {
    consultancy: 'Consulenza',
  },
  totals: {
    revenue: 'Totale Ricavi',
    typology: 'Totale {{name}}',
    typologyRevenue: 'ricavo {{revenue}}',
  },
  hint: {
    netAmountRule: 'Quantità × prezzo unitario.',
    netAmountFormula: '{{quantity}} × {{unitPrice}} = {{net}}',
    totals: {
      revenue: 'Somma dei ricavi effettivi delle righe: commissione ricevuta, altrimenti imponibile.',
    },
  },
  warnings: {
    title: 'Avviso',
    missingSupplierCommission: 'Commissione Fornitore ricevuta mancante: il ricavo è 0,00.',
    staleCommissionBase: 'Importo calcolato su una base precedente: salva di nuovo l\'offerta per aggiornarlo.',
  },
  payment: {
    noStatus: 'Nessuno stato',
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
  bulk: {
    setAll: 'Imposta pagamento su tutte le righe',
    title_one: 'Pagamento di {{count}} riga',
    title_other: 'Pagamento di {{count}} righe',
    description: 'I valori scelti si applicano a tutte le righe della commessa; "Non modificare" lascia il valore attuale di ogni riga.',
    keep: 'Non modificare',
    unpaidYes: 'Sì',
    unpaidNo: 'No',
    nothingToSet: 'Scegli almeno un valore da impostare.',
    apply: 'Applica',
    saved_one: 'Pagamento aggiornato su {{count}} riga.',
    saved_other: 'Pagamento aggiornato su {{count}} righe.',
  },
}
