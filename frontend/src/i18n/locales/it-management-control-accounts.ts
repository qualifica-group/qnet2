/** Controllo di gestione, conti (spec 0271). */
export const accounts = {
  title: 'Conti',
  loading: 'Caricamento dei conti',
  error: 'Impossibile caricare i conti.',
  empty: 'Nessun incasso e nessun pagamento nel periodo scelto con questi filtri.',
  filters: {
    title: 'Filtri dei conti',
    dateFrom: 'Incassi e pagamenti dal',
  },
  totals: {
    title: 'Totali del periodo',
    collections: 'Incassi',
    payments: 'Pagamenti',
    net: 'Netto',
  },
  types: { bank_account: 'Conto bancario', card: 'Carta', cash: 'Contanti' },
  collections: {
    title: 'Incassi per conto',
    hint: 'Rate attive incassate nel periodo, sul totale del documento.',
    caption: 'Incassi del periodo per conto finanziario',
  },
  payments: {
    title: 'Pagamenti per conto',
    hint: 'Rate passive pagate nel periodo, sul totale del documento. Senza conto sulla rata vale quello della fattura.',
    caption: 'Pagamenti del periodo per conto finanziario',
  },
  table: {
    account: 'Conto',
    type: 'Tipo',
    noType: '-',
    amount: 'Importo',
    installments: 'Rate',
    total: 'Totale',
  },
  chart: {
    title: 'Andamento',
    monthsTitle: 'Incassi e pagamenti per mese',
    collectionsTitle: 'Ripartizione degli incassi per conto',
    paymentsTitle: 'Ripartizione dei pagamenti per conto',
    empty: 'Nessun dato nel periodo.',
  },
}
