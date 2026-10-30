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
  /** Last-resort 403 sentence (`lib/forbidden-message.ts`) when the server sent no reason. */
  forbidden: 'Non hai i permessi per questa operazione.',
  /** 403 with a missing permission: ability and module are the permission's localized labels. */
  missingPermission: 'Non hai il permesso «{{ability}}» sul modulo «{{module}}».',
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
  /** Record detail side column (collaboration): the record's own tab when narrow, collapse/expand when wide. */
  recordSide: {
    details: 'Dettagli',
    collapse: 'Comprimi pannello laterale',
    expand: 'Espandi pannello laterale',
  },
  /** Record detail "Buoni" section when nothing is assigned (spec 0331 D-4). */
  noRewards: 'Nessun buono',
  /** Badge next to a record title: its legacy id, shown only to who may see it (`legacy-ids.view`). */
  legacyIdBadge: 'OLD ID #{{id}}',
  /** In-place editing of a record's rows (spec 0195): the pencil, and the open row's confirm/cancel. */
  inlineEdit: {
    edit: 'Modifica {{field}}',
    save: 'Salva',
    apply: 'Fatto',
    revert: 'Ripristina',
    cancel: 'Annulla',
    lines: {
      add: 'Aggiungi riga',
      edit: 'Modifica riga {{n}}',
      remove: 'Elimina riga {{n}}',
      removeConfirm: {
        title: 'Eliminare la riga?',
        description: 'La riga {{n}} viene eliminata subito.',
        confirm: 'Elimina',
      },
    },
  },
}
