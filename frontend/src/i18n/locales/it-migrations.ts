import { migrationRequirements } from '@/i18n/locales/it-migration-requirements'

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
    'business-function-members': 'Funzioni aziendali — collega responsabile e operatori',
    'referent-types': 'Tipi di referente',
    referents: 'Referenti',
    sources: 'Fonti',
    tags: 'Tag',
    sectors: 'Settori',
    'task-templates': 'Modelli di task',
    'vat-rates': 'Aliquote IVA',
    'payment-methods': 'Modalità di pagamento',
    attributes: 'Attributi',
    'product-categories': 'Categorie prodotto',
    'product-category-attributes': 'Categorie prodotto — collega attributi',
    'attribute-layouts': 'Layout attributi',
    products: 'Prodotti',
    'cost-products': 'Prodotti di costo',
    'email-templates': 'Modelli email',
    'document-bundles': 'Modelli documenti',
    registries: 'Anagrafiche',
    opportunities: 'Opportunità',
    quotes: 'Offerte',
    'work-orders': 'Commesse',
    'product-suppliers': 'Prodotti — collega fornitori',
    'commission-configurations': 'Configuratore commissioni',
  },
  state: {
    notMigrated: 'Non migrata',
    running: 'In corso',
    failed: 'Ultimo import fallito',
    partial: 'Migrata con righe fallite',
    migrated: 'Migrata',
  },
  overview: {
    title: 'Stato migrazione',
    description: 'Le sorgenti nell’ordine in cui importarle. Seleziona una sorgente per vederne il template e cosa le serve.',
    progress: '{{migrated}} di {{total}} sorgenti migrate',
    phase: 'Fase {{phase}}',
    unordered: 'Senza fase',
    migratedAt: 'Migrata il {{date}}',
    lastRunAt: 'Ultimo import il {{date}}',
    failedRows_one: '{{count}} riga fallita',
    failedRows_other: '{{count}} righe fallite',
  },
  requirements: {
    title: 'Cosa serve per migrare bene',
    dependenciesTitle: 'Da importare prima',
    none: 'Nessuna sorgente da importare prima.',
    required: 'Obbligatoria',
    optional: 'Consigliata',
    legend:
      'Obbligatoria: senza, le righe non vengono importate. Consigliata: senza, il collegamento resta vuoto con un avviso nel report.',
    missingRequired: 'Mancano sorgenti obbligatorie: {{sources}}. Importale prima di questa.',
  },
  sourceRequirements: migrationRequirements,
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
    importUnavailable:
      'Non disponibile in questa installazione: gli utenti si migrano in modalità solo abbinamento e responsabili e operatori delle funzioni si gestiscono in qnet.',
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
      'Scegli quali sorgenti include “Importa tutto” e trascina per ordinarle. Le sorgenti successive dipendono dalle precedenti.',
    dragHandle: 'Riordina sorgente',
    enabledLabel: 'Includi {{source}} nell’import massivo',
    unavailable: 'Non disponibile',
    save: 'Salva ordine',
    saving: 'Salvataggio…',
    saved: 'Ordine salvato.',
    loadError: 'Impossibile caricare l’ordine di import. Riprova.',
  },
  massImport: {
    button: 'Importa tutto',
    title: 'Importa tutte le sorgenti',
    subtitle: 'Esegue le sorgenti abilitate nell’ordine salvato, fermandosi al primo errore.',
    confirmDescription:
      'Esegue ogni sorgente abilitata in ordine, in background. Si ferma alla prima sorgente che fallisce, perché le successive dipendono dalle precedenti. I record già importati vengono saltati, quindi è sicuro rilanciare.',
    start: 'Avvia import',
    starting: 'Avvio…',
    empty: 'Nessuna sorgente abilitata. Abilitane almeno una in “Configura ordine”.',
    stopped:
      'L’import si è fermato alla sorgente in errore; le sorgenti successive non sono state eseguite.',
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
