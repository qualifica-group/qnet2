/**
 * Localized strings for the external data migrations module (spec 0013):
 * the super-admin-only preview (fase 1) page and the import (fase 2)
 * confirm/progress/summary dialog.
 *
 * Extracted from `it.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Mirrors `en-migrations.ts` 1:1.
 */
export const migrations = {
  nav: {
    label: 'Migrazioni',
  },
  sources: {
    roles: 'Ruoli',
    users: 'Utenti',
    'business-functions': 'Funzioni aziendali',
    companies: 'Società aziendali',
    'company-sites': 'Società sedi',
    'operational-sites': 'Sedi operative',
    'business-function-members': 'Funzioni aziendali — riconcilia responsabile e operatori',
    'referent-types': 'Tipi di referente',
    referents: 'Referenti',
    sources: 'Fonti',
    tags: 'Tag',
    sectors: 'Settori',
    'vat-rates': 'Aliquote IVA',
    attributes: 'Attributi',
    'product-categories': 'Categorie prodotto',
    'product-category-attributes': 'Categorie prodotto — collega attributi',
    products: 'Prodotti',
  },
  page: {
    sourceLabel: 'Sorgente',
    sourcePlaceholder: 'Seleziona una sorgente…',
    sourcesLoadError: 'Impossibile caricare le sorgenti di migrazione. Riprova.',
    sourcesEmpty: 'Nessuna sorgente di migrazione registrata.',
    pageIndicator: 'Pagina {{page}}',
    previous: 'Precedente',
    next: 'Successiva',
  },
  template: {
    title: 'Template atteso',
    description:
      'qnet definisce questo schema di campi per {{source}}: la sorgente esterna deve restituire record conformi.',
    fieldHeader: 'Campo',
    typeHeader: 'Tipo',
    loadError: 'Impossibile caricare il template atteso. Riprova.',
    empty: 'Questa sorgente non definisce alcun campo.',
    importButton: 'Importa questa sorgente',
    endpointTitle: 'Endpoint atteso',
    method: 'Metodo: {{method}}',
    copyUrl: 'Copia URL',
    baseUrlMissing: 'La base URL non è configurata per questa sorgente; viene mostrato solo il percorso.',
    sampleTitle: 'Esempio di risposta',
    copyJson: 'Copia JSON',
    copied: 'Copiato',
  },
  preview: {
    showButton: 'Mostra anteprima dati esterni',
    loadError: "Impossibile caricare l'anteprima. Riprova.",
    empty: 'Nessun record trovato per questa sorgente.',
  },
  import: {
    title: 'Importa {{source}}',
    subtitle:
      "L'import crea i record in background; ripeterlo è sicuro (idempotente).",
    confirmDescription:
      "Questa operazione crea i record in qnet dalla sorgente esterna, in background. I record già importati vengono saltati: ripetere l'import è sicuro.",
    start: 'Avvia import',
    starting: 'Avvio…',
    close: 'Chiudi',
    summary: {
      total: 'Righe totali',
      created: 'Creati',
      skipped: 'Saltati',
      failed: 'Falliti',
    },
    reportTitle: 'Avvisi ed errori',
    reportLevel: {
      warning: 'Avviso',
      error: 'Errore',
    },
  },
  plan: {
    configureButton: 'Configura ordine',
    title: 'Ordine import massivo',
    subtitle:
      'Scegli quali source include “Importa tutto” e trascina per ordinarle. Le source successive dipendono dalle precedenti.',
    dragHandle: 'Riordina source',
    enabledLabel: 'Includi {{source}} nell’import massivo',
    save: 'Salva ordine',
    saving: 'Salvataggio…',
    saved: 'Ordine salvato.',
    loadError: 'Impossibile caricare l’ordine di import. Riprova.',
  },
  massImport: {
    button: 'Importa tutto',
    title: 'Importa tutte le source',
    subtitle: 'Esegue le source abilitate nell’ordine salvato, fermandosi al primo errore.',
    confirmDescription:
      'Esegue ogni source abilitata in ordine, in background. Si ferma alla prima source che fallisce, perché le successive dipendono dalle precedenti. I record già importati vengono saltati, quindi è sicuro rilanciare.',
    start: 'Avvia import',
    starting: 'Avvio…',
    empty: 'Nessuna source abilitata. Abilitane almeno una in “Configura ordine”.',
    stopped:
      'L’import si è fermato alla source in errore; le source successive non sono state eseguite.',
    sourceStatus: {
      notRun: 'Non eseguita',
    },
    perSourceCounts: '{{created}} creati · {{skipped}} saltati · {{failed}} falliti',
  },
  background: {
    hint: 'L’operazione prosegue sul server anche se chiudi questa finestra.',
    stalled:
      'Sta impiegando più del previsto: abbiamo smesso di controllare. L’operazione resta in carico al server — riprova a controllare tra poco o, se non si sblocca, avvisa l’amministratore.',
    retry: 'Controlla di nuovo',
  },
  status: {
    pending: 'In attesa',
    processing: 'Importazione in corso',
    completed: 'Completato',
    failed: 'Fallito',
  },
  errors: {
    forbidden: 'Non hai i permessi per accedere alle migrazioni.',
    notFound: 'Questa sorgente di migrazione non esiste.',
    validation: 'Parametri di paginazione non validi.',
    externalUnavailable: 'Il sistema esterno non è al momento disponibile. Riprova.',
    generic: 'Si è verificato un errore. Riprova.',
    jobFailed: "L'import è fallito. Riprova.",
  },
}
