/** Cross-module UI strings (`common.*`): split out of `it.ts` for the engineering size limit. */
export const common = {
  loading: 'Caricamento…',
  retry: 'Riprova',
  search: 'Cerca',
  notFound: 'Pagina non trovata',
  backToDashboard: 'Torna alla dashboard',
  comingSoon: 'Questa sezione non è ancora disponibile.',
  clear: 'Cancella',
  confirm: 'Conferma',
  cancel: 'Annulla',
  confirmTitle: 'Sei sicuro?',
  yes: 'Sì',
  no: 'No',
  back: 'Indietro',
  edit: 'Modifica',
  new: 'Nuovo',
  viewProfile: 'Vedi il profilo di {{name}}',
  /** Label of the select a tab strip collapses into when the tabs no longer fit. */
  tabsSelectLabel: 'Sezione',
  /** Accessible name of the button that removes one chip from a multi-select. */
  remove: 'Rimuovi',
  close: 'Chiudi',
  /** Sheet toolbar action that leaves the modal for the record's dedicated detail page. */
  openDetailPage: 'Apri pagina di dettaglio',
  /** Appended to the name when duplicating a record (row action "duplicate"); leading space by design. */
  copySuffix: ' (copia)',
  /** Final state of a record fetch answered 404/403 (`DetailError`, `RecordUnavailable`): no retry. */
  recordUnavailable: {
    notFound: {
      title: 'Record non trovato',
      description: 'Il record che cerchi non esiste o è stato eliminato.',
    },
    forbidden: {
      title: 'Accesso negato',
      description: 'Non hai i permessi necessari per visualizzare questo record.',
    },
  },
  /** In-place editing of a record's rows (spec 0195): the pencil, and the open row's confirm/cancel. */
  inlineEdit: {
    edit: 'Modifica {{field}}',
    save: 'Salva',
    apply: 'Fatto',
    revert: 'Ripristina',
    cancel: 'Annulla',
  },
}
