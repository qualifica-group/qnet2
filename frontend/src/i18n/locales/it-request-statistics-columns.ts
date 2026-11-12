/** "Colonne e stati" of Statistiche Gestione Richieste (spec 0391). */

export const requestStatisticsColumns = {
  open: 'Colonne e stati',
  title: 'Colonne e stati',
  description:
    'Scegli quali stati di lavorazione conta ogni colonna. La colonna "nel periodo selezionato" usa la stessa scelta e conta solo le richieste create nel periodo.',
  columnsLabel: 'Colonne',
  columns: {
    unhandled_new_contacts: 'Nuovi contatti non gestiti',
    current_potentials: 'Potenziali associati',
    associati: 'Associati',
    trattative_concluse: 'Trattative concluse',
    invio_presa_in_carico: 'Invio presa in carico',
  },
  twinNote: 'Vale anche per "{{column}} (nel periodo selezionato)".',
  periodNote: 'Conta le richieste create nel periodo selezionato.',
  searchPlaceholder: 'Cerca flusso, categoria o stato…',
  noMatch: 'Nessun flusso o stato trovato.',
  inactive: 'Non attivo',
  noStatuses: 'Nessuno stato in questo flusso.',
  chosenCount: '{{count}} scelti',
  selectAll: 'Tutti',
  selectNone: 'Nessuno',
  loadError: 'Impossibile caricare la configurazione delle colonne.',
  saveError: 'Impossibile salvare la configurazione delle colonne.',
  saved: 'Configurazione delle colonne salvata.',
  actions: {
    reset: 'Ripristina',
    cancel: 'Annulla',
    save: 'Salva',
  },
}
