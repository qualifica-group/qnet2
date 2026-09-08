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
    program: 'Programma',
    changeStatus: 'Cambia stato',
    reactivate: 'Riapri',
    viewAll: 'Visualizza tutti',
    // Spec 0105: terzo livello di visibilità di Gestione Richieste — le
    // richieste delle sedi di appartenenza, anche senza esserne il gestore.
    viewSite: 'Visualizza per sede',
    viewDocuments: 'Visualizza documenti',
    // Oltre il CRUD di BasePolicy: l'atto da supervisore di assegnare
    // l'Operatore (GA2) in creazione (direttiva utente 2026-07-29).
    assignOperator: 'Assegna operatore',
    // Direttiva utente 2026-09-08: terzo stato del blocco Team del pannello
    // "Lavora" — squadra visibile, membri già assegnati congelati, sola aggiunta.
    appendTeamMember: 'Aggiungi al team',
    impersonate: 'Impersona',
    // Ability non canoniche introdotte dalla spec 0078: `manage` governa
    // l'approvazione/rifiuto di una richiesta di modifica campo
    // (FieldChangeRequestPolicy); `updateSource` governa la scrittura
    // diretta del campo protetto "Fonte" di Gestione Richieste su
    // ciascuno dei suoi tre canali di scrittura (RequestManagementPolicy,
    // generato come `request-management.updateSource`).
    manage: 'Gestire',
    updateSource: 'Modificare la Fonte',
    // Spec 0106: genera/scarica il report CSV di Gestione Richieste
    // (`request-management.report`), indipendente da `export` (righe di griglia).
    report: 'Genera report',
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
    'field-change-requests': 'Richieste di modifica',
    leads: 'Lead',
    'operational-sites': 'Sedi operative',
    opportunities: 'Opportunità',
    'payment-methods': 'Modalità di Pagamento',
    'pipeline-statuses': 'Stati progetto/campagna',
    'product-categories': 'Categorie Prodotto',
    products: 'Prodotti',
    projects: 'Progetti',
    'quote-workflows': 'Configuratore Stati Offerta',
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
    'units-of-measure': 'Unita di Misura',
    'product-typologies': 'Tipologie Prodotto',
    // Modulo Task e i suoi cinque configuratori (spec 0101).
    tasks: 'Task',
    'task-statuses': 'Stati Task',
    'task-types': 'Tipologie Task',
    'task-categories': 'Categorie Task',
    'task-priorities': 'Priorità Task',
    'task-importances': 'Importanza Task',
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
