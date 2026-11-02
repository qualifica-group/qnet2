/**
 * Localized strings for the generic per-table export feature (spec 0014).
 * Mirrors `en-exports.ts` 1:1.
 */
export const exports = {
  action: 'Esporta',
  title: 'Esporta dati',
  subtitle: 'Esporta la vista corrente esattamente come filtrata, ordinata e visualizzata.',
  fields: {
    format: 'Formato file',
  },
  formats: {
    csv: 'CSV',
    xlsx: 'Excel (XLSX)',
  },
  stateSummary: {
    columns: 'Colonne',
    filters: 'Filtri attivi',
    sort: 'Ordinamento',
    sortActive: 'Applicato',
    sortNone: 'Nessuno',
    search: 'Ricerca',
    searchNone: 'Nessuna',
  },
  buttons: {
    export: 'Esporta',
    exporting: 'Esportazione in corso…',
    download: 'Scarica il file',
    downloading: 'Download in corso…',
    close: 'Chiudi',
  },
  status: {
    processing: 'Elaborazione in corso',
    completed: 'Completato',
    failed: 'Fallito',
  },
  rowCount_one: '{{count}} riga esportata',
  rowCount_other: '{{count}} righe esportate',
  errors: {
    validation: "La richiesta di esportazione non è valida. Riprova.",
    rateLimited: 'Troppe richieste. Attendi un momento e riprova.',
    generic: 'Si è verificato un errore. Riprova.',
    jobFailed: "L'esportazione non è riuscita. Riprova.",
  },
}
