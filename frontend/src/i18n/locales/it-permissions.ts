/**
 * Catalogo permessi (italiano): verbi delle azioni, nomi dei moduli assegnabili
 * e stringhe dell'esploratore permessi del form Ruolo (spec 0076). Estratto da
 * `it.ts` per rientrare nei limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6), mirror di `en-permissions.ts`.
 *
 * Le chiavi di `resources` combaciano coi prefissi di permesso reali
 * (`backend/config/authorization.php`, `AssignablePermissionCatalogue`), letti
 * a lookup diretto da `resourceLabel()`/`abilityLabel()`
 * (`features/roles/permission-labels.ts`): nessuna mappa aggiuntiva lato client.
 * I nomi riusano testualmente le label già scelte in `it-navigation.ts` per lo
 * stesso modulo, per evitare due nomi diversi per la stessa cosa nella UI.
 */

export const permissions = {
  abilities: {
    viewAny: 'Visualizza elenco',
    view: 'Visualizza',
    create: 'Crea',
    update: 'Modifica',
    delete: 'Elimina',
    export: 'Esporta',
    import: 'Importa',
    viewActivity: 'Visualizza attività',
    // Azioni oltre il CRUD/export/import/viewActivity di BasePolicy, esposte
    // solo da alcune policy (es. ContractPolicy, RequestManagementPolicy).
    validate: 'Valida',
    terminate: 'Termina',
    schedule: 'Programma',
    changeStatus: 'Cambia stato',
    reactivate: 'Riattiva',
    viewAll: 'Visualizza tutti',
    viewDocuments: 'Visualizza documenti',
    // Oltre il CRUD di BasePolicy: l'atto da supervisore di assegnare
    // l'Operatore (GA2) in creazione (direttiva utente 2026-07-29).
    assignOperator: 'Assegna operatore',
    impersonate: 'Impersona',
  },
  resources: {
    users: 'Utenti',
    roles: 'Ruoli',
    // Sub-entità non assegnabili dal form Ruolo (governate dalla matrice
    // campi del modulo padre), ma i cui permessi restano nel catalogo DB.
    addresses: 'Indirizzi',
    contacts: 'Contatti',
    personal_data: 'Dati anagrafici',
    // Moduli permission-only senza voce di menu (area `shared`).
    attachments: 'Allegati',
    notes: 'Note',
    attributes: 'Attributi',
    'business-functions': 'Funzioni aziendali',
    campaigns: 'Campagne',
    'commission-configurations': 'Configuratore Commissioni',
    companies: 'Società aziendali',
    'company-sites': 'Società sedi',
    'contract-statuses': 'Stati Contratto',
    contracts: 'Contratti',
    'custom-fields': 'Campi personalizzati',
    'document-layouts': 'Layout',
    leads: 'Lead',
    'operational-sites': 'Sedi operative',
    opportunities: 'Opportunità',
    'opportunity-statuses': 'Stati Opportunità',
    'opportunity-workflows': 'Configuratore Stati Lavorazione',
    'payment-methods': 'Modalità di Pagamento',
    'pipeline-statuses': 'Stati progetto/campagna',
    'product-categories': 'Categorie Prodotto',
    products: 'Prodotti',
    projects: 'Progetti',
    'quote-statuses': 'Stati Offerta',
    quotes: 'Offerte',
    'referent-types': 'Tipi referente',
    referents: 'Referenti',
    registries: 'Anagrafiche',
    'request-management': 'Gestione Richieste',
    'reward-statuses': 'Stati Buoni Collegati',
    'reward-types': 'Buoni, Premi e Incentivi',
    'rewarded-referents': 'Referenti con Buoni',
    sectors: 'Settori',
    sources: 'Fonti',
    tags: 'Tag',
    'vat-rates': 'IVA',
  },
  areas: {
    // Area finale dell'albero: moduli permission-only assegnabili ma senza
    // voce di menu propria (`notes`, `attachments` — spec 0076).
    shared: 'Trasversali',
  },
}

/** Stringhe UI dell'esploratore permessi a due pannelli del form Ruolo (spec 0076). */
export const permissionExplorer = {
  searchPlaceholder: 'Cerca moduli o permessi…',
  searchLabel: 'Cerca nel catalogo permessi',
  searchEmpty: 'Nessun modulo o permesso trovato.',
  actionsHeading: 'Azioni',
  fieldsHeading: 'Campi',
  nativeFieldsLabel: 'Nativi',
  customFieldsLabel: 'Personalizzati',
  // Contatore selezionati/totali, sia per area/modulo sia globale.
  selectionCount: '{{selected}}/{{total}}',
  selectAllArea: 'Seleziona area',
}
