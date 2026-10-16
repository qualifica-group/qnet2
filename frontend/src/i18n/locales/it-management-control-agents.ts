/** Controllo di gestione, agenti (spec 0272). */
export const agents = {
  title: 'Agenti',
  loading: 'Caricamento degli agenti',
  error: 'Impossibile caricare gli agenti.',
  empty: 'Nessuna provvigione maturata, in estratto conto o pagata nel periodo scelto con questi filtri.',
  filters: {
    title: 'Filtri degli agenti',
    dateFrom: 'Maturati e pagamenti dal',
    top: 'Agenti mostrati',
    topOption: 'Primi {{count}}',
  },
  figures: {
    accrued: 'Maturato',
    to_settle: 'Da liquidare',
    in_statement: 'In estratto conto',
    approved_unpaid: 'Approvato non pagato',
    paid: 'Pagato',
  },
  totals: {
    title: 'Totali del periodo',
    pendingDocument: '{{count}} in attesa documento',
    agents_count: 'Agenti',
  },
  table: {
    title: 'Provvigioni per agente',
    hint: 'Maturato e pagato riguardano il periodo; da liquidare, in estratto conto e approvato non pagato sono a oggi. Gli agenti sono ordinati per importo da liquidare.',
    caption: 'Provvigioni per agente: maturato, da liquidare, in estratto conto, approvato e pagato',
    agent: 'Agente',
    pendingDocument: 'In attesa documento',
    others: 'Altri ({{count}} agenti)',
    total: 'Totale',
  },
  chart: {
    title: 'Maturato e pagato',
    agentsTitle: 'Maturato e pagato per agente',
    monthsTitle: 'Maturato e pagato per mese',
    empty: 'Nessun dato nel periodo.',
  },
}
