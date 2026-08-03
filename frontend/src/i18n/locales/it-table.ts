/**
 * The generic table framework's strings (toolbar, saved views, bulk actions).
 * Split out of `it.ts` to keep it within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); spread back under the `table` key so
 * `t('table.xxx')` call sites are unaffected.
 */
export const table = {
  // The default hidden `id` column injected into every table
  // (AbstractTableDefinition / InjectsDefaultIdColumn).
  columns: {
    id: 'ID',
  },
  actionsHeader: 'Azioni',
  rowActions: 'Azioni riga',
  moreActions: 'Altre azioni',
  search: 'Cerca…',
  searchPlaceholder: 'Cerca {{columns}}…',
  rowCount_one: '{{count}} riga',
  rowCount_other: '{{count}} righe',
  options: 'Opzioni tabella',
  export: 'Esporta',
  fullscreen: 'Schermo intero',
  exitFullscreen: 'Esci da schermo intero',
  confirmAction: 'Sei sicuro di voler eseguire questa azione?',
  loadError: 'Impossibile caricare la tabella. Riprova.',
  cellUpdateError: 'Impossibile salvare la modifica.',
  emptyConfig: 'Nessuna colonna disponibile per questa tabella.',
  noRows: 'Nessun record da mostrare.',
  resetLayout: 'Ripristina layout',
  layoutReset: 'Layout della tabella ripristinato ai valori predefiniti.',
  layoutError: 'Impossibile aggiornare il layout della tabella.',
  resetFilters: 'Azzera filtri',
  filtersReset: 'Filtri della tabella azzerati.',
  filtersError: 'Impossibile azzerare i filtri della tabella.',
  filterValuesTruncated:
    'Vengono mostrati solo i primi valori corrispondenti. Usa una condizione di filtro per restringere ulteriormente.',
  textFilters: 'Filtri testo',
  numberFilters: 'Filtri numero',
  dateFilters: 'Filtri data',
  primaryContactsCount: '{{count}} contatti principali',
  copy: 'Copia',
  copied: 'Copiato',
  savedFilters: 'Filtri salvati',
  savedFiltersSubtitle: 'Riapplica un set di filtri con un clic.',
  saveViewHeading: 'Salva la vista corrente',
  saveView: 'Salva vista',
  applyFilterToSaveHint: 'Applica prima un filtro per salvarlo come vista.',
  viewActive: 'Attiva',
  viewNamePlaceholder: 'Nome vista',
  visibility: 'Visibilità',
  visibilityPrivate: 'Privata',
  visibilityShared: 'Condivisa',
  myViews: 'Le mie viste',
  sharedViews: 'Condivise',
  sharedBy: 'Condivisa da {{name}}',
  applyView: 'Applica vista',
  deleteView: 'Elimina vista',
  save: 'Salva',
  viewSaved: 'Vista dei filtri salvata.',
  viewSaveError: 'Impossibile salvare la vista dei filtri.',
  viewDeleted: 'Vista dei filtri eliminata.',
  viewDeleteError: 'Impossibile eliminare la vista dei filtri.',
  duplicateViewName: 'Hai già una vista con questo nome.',
  noSavedViews: 'Nessuna vista salvata.',
  selectedCount_one: '{{count}} riga selezionata',
  selectedCount_other: '{{count}} righe selezionate',
  bulkActions: 'Azioni ({{count}})',
  deleteSelected: 'Elimina selezionati ({{count}})',
  bulkDeleteConfirmTitle: 'Elimina le righe selezionate',
  bulkDeleteConfirmBody_one:
    'Verranno eliminate definitivamente {{count}} riga selezionata. Operazione irreversibile.',
  bulkDeleteConfirmBody_other:
    'Verranno eliminate definitivamente {{count}} righe selezionate. Operazione irreversibile.',
  bulkDeleted_one: '{{count}} riga eliminata.',
  bulkDeleted_other: '{{count}} righe eliminate.',
  bulkDeletePartial:
    '{{deleted}} righe eliminate, {{failed}} non eliminabili.',
  bulkDeleteError: 'Impossibile eliminare le righe selezionate. Riprova.',
  relationEditor: {
    placeholder: 'Seleziona…',
    searchPlaceholder: 'Cerca…',
    empty: 'Nessun risultato.',
    error: 'Impossibile caricare le opzioni.',
    clear: 'Rimuovi',
    trigger: 'Scegli un valore',
    retry: 'Riprova',
    loadMore: 'Carica altri',
  },
  multiSelectEditor: {
    list: 'Scegli uno o più valori',
    searchPlaceholder: 'Cerca…',
    empty: 'Nessun risultato.',
    error: 'Impossibile caricare le opzioni.',
    retry: 'Riprova',
    loadMore: 'Carica altri',
    noScope: 'Questa riga non ha ancora un ambito: sblocca il catalogo completo per scegliere.',
    hintScoped: 'Solo le opzioni dell\'ambito di questa riga.',
    // Spec 0075, D-4: qui l'ambito non si sblocca — il modulo rifiuta quello
    // che ne sta fuori, quindi non c'è nessuno sblocco da spiegare.
    hintLocked: 'Solo le opzioni dell\'ambito di questa riga: le altre il modulo le rifiuta.',
    hintUnlocked: "Catalogo completo: una scelta fuori ambito estenderà l'ambito della riga.",
    unlock: 'Mostra tutto',
    relock: "Limita all'ambito della riga",
    unlockTitle: 'Mostrare tutto il catalogo?',
    unlockDescription:
      "Scegliendo un elemento fuori dall'ambito di questa riga, quell'ambito verrà esteso automaticamente per includerlo.",
  },
  // Spec 0075: l'editor in cella {funzione aziendale, categoria prodotto} —
  // lo stesso flusso in due passi del ProductLinesField del form.
  productLinesEditor: {
    selected: 'Categorie prodotto di questo record',
    none: 'Nessuna categoria prodotto.',
    remove: 'Rimuovi {{name}}',
    back: 'Torna alle funzioni aziendali',
    businessFunctionStep: 'Passo 1: scegli la funzione aziendale.',
    categoryStep: 'Passo 2: scegli una categoria prodotto di {{name}}.',
    businessFunctionSearch: 'Cerca funzioni aziendali…',
    categorySearch: 'Cerca categorie prodotto…',
    empty: 'Nessun risultato.',
    error: 'Impossibile caricare le opzioni.',
    retry: 'Riprova',
    loadMore: 'Carica altri',
    uncoveredProducts:
      'Questi prodotti di interesse non sarebbero più coperti da nessuna categoria prodotto: {{names}}. Il salvataggio verrà rifiutato.',
  },
  selectEditor: {
    list: 'Scegli un valore',
    empty: 'Nessun valore disponibile per questa riga.',
  },
  // L'editor e' un gruppo di due campi da quando l'ora e' facoltativa
  // (direttiva utente 2026-07-31): `label` nomina il gruppo, gli altri due i campi.
  dateTimeEditor: {
    label: 'Data e ora',
    dateLabel: 'Data',
    timeLabel: 'Ora (facoltativa)',
    clear: 'Svuota',
  },
  // Spec 0064: il gemello sola-data di `dateTimeEditor`, usato dall'attributo
  // di categoria prodotto di tipo `date` (nessuna componente oraria).
  dateEditor: {
    label: 'Data',
  },
  noteDialog: {
    title: 'Aggiungi una nota',
    description: 'Questo stato richiede una nota esplicativa prima di poter essere salvato.',
    label: 'Nota',
    required: 'La nota è obbligatoria.',
    cancel: 'Annulla',
    confirm: 'Salva',
  },
  advancedFilters: {
    toggle: 'Filtri avanzati',
    activeCount_one: '{{count}} filtro attivo',
    activeCount_other: '{{count}} filtri attivi',
    apply: 'Applica',
    reset: 'Azzera',
    requiredError: 'Campo obbligatorio.',
    rangeFrom: 'Da',
    rangeTo: 'A',
    rangeSeparator: '–',
    selectPlaceholder: 'Seleziona…',
    searchPlaceholder: 'Cerca…',
    empty: 'Nessun risultato.',
    loadError: 'Impossibile caricare le opzioni.',
    clearLabel: 'Rimuovi',
    removeLabel: 'Rimuovi',
  },
}
