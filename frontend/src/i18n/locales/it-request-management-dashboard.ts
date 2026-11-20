/**
 * Dashboard grafici (spec 0107): mostrato dalla pagina Statistiche Gestione Richieste
 * (spec 0185), i nomi di categoria/operatore restano valori di dominio.
 */
export const requestManagementDashboard = {
  regionLabel: 'Dashboard di Gestione Richieste',
  pageForbidden: 'Non hai il permesso di visualizzare le Statistiche Gestione Richieste.',
  editFilters: 'Filtri',
  // Spec 0192: periodi con un clic nella toolbar.
  periods: {
    label: 'Periodo',
    today: 'Oggi',
    yesterday: 'Ieri',
    last7Days: 'Ultimi 7 giorni',
    thisMonth: 'Questo mese',
    lastMonth: 'Mese scorso',
    all: 'Tutto',
    custom: 'Personalizzato',
  },
  tabsLabel: 'Sezioni delle statistiche',
  overviewTab: 'Panoramica',
  // Chip dei filtri applicati sopra i grafici (direttiva utente 2026-09-18).
  applied: {
    title: 'Filtri applicati',
    period: 'Periodo',
    periodValue: '{{from}} – {{to}}',
    // Spec 0169 D-5: estremi aperti.
    periodFrom: 'dal {{from}}',
    periodTo: 'fino al {{to}}',
    periodAll: 'Tutto il periodo',
    categories: 'Categorie',
    sites: 'Sedi',
    operators: 'Operatori',
    rowMode: 'Righe',
    more: '+{{count}}',
    allOperatorsOfSites: 'Tutti quelli delle sedi scelte',
  },
  noCategories:
    'Nessun dato da mostrare: non ci sono ancora richieste nelle categorie incluse nel report. La dashboard si popolerà non appena ne verrà registrata una.',
  errors: {
    network: 'Impossibile contattare il server: verifica la connessione e riprova.',
    invalidFilters:
      'I filtri applicati non sono più validi (ad esempio una categoria non è più disponibile). Aggiornali dal pulsante Filtri.',
    generic: 'Impossibile caricare la dashboard per un errore del server. Riprova tra qualche istante.',
  },
  empty: 'Nessun grafico da mostrare per questa selezione.',
  // Spec 0141: una categoria reportable puo' non avere colonne configurate.
  tilesEmpty: 'Nessuna colonna configurata per questa categoria.',
  overall: 'Totale complessivo',
  indicatorsChartTitle: 'Profilo indicatori',
  // Spec 0192 D-4: confronto col periodo precedente di pari durata.
  trend: {
    new: 'Nuovo',
    vsPrevious: 'Periodo precedente: {{value}}',
  },
  shares: {
    title: 'Ripartizione per categoria',
    others: 'Altre categorie',
    item: '{{label}}: {{value}} ({{percent}}%)',
    lead: '{{label}} {{percent}}%',
    more: '+{{count}} altre',
  },
  breakdown: {
    title: 'Dettaglio per stato attuale',
    trigger: 'Dettaglio per stato di {{label}}',
    item: '{{label}}: {{value}}',
  },
  heatmap: {
    title: 'Mappa categorie × indicatori',
    description: "Colore più intenso = valore più alto per quell'indicatore. Clicca una categoria per aprirla.",
    category: 'Categoria',
    notConfigured: 'Non configurato',
  },
  leaderboard: {
    title: 'Classifica operatori',
    rank: 'Pos.',
    operator: 'Operatore',
  },
}
