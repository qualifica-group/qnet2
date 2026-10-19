/**
 * User employment profile strings (spec 0015: sezioni Profilo / Rapporto /
 * Dati contrattuali). Extracted from `it.ts` to keep that file within the
 * engineering size limits (see `.claude/rules/engineering.md` §6); merged
 * into the `users.*` namespace from there.
 */

/** Section titles of the three employment sections (spec 0222: the record sections carry no description). */
export const usersFormEmploymentSections = {
  profile: { title: 'Profilo' },
  contract: { title: 'Rapporto contrattuale' },
  contractHistory: { title: 'Storico contrattuale' },
  payment: { title: 'Pagamento' },
  contractData: { title: 'Dati contrattuali' },
}

/** Field labels, placeholders e messaggi di validazione per `employment.*`. */
export const usersFormEmployment = {
  jobRoles: 'Mansioni',
  jobRolesPlaceholder: 'Seleziona le mansioni…',
  jobRolesSearch: 'Cerca mansioni…',
  jobRolesEmpty: 'Nessuna mansione trovata.',
  jobRolesError: 'Impossibile caricare le mansioni.',
  jobRolesRemove: 'Rimuovi mansione',
  businessFunctions: 'Funzioni aziendali di appartenenza',
  businessFunctionsPlaceholder: 'Seleziona le funzioni (obbligatoria per una persona attiva)…',
  businessFunctionsSearch: 'Cerca funzioni aziendali…',
  businessFunctionsEmpty: 'Nessuna funzione aziendale trovata.',
  businessFunctionsError: 'Impossibile caricare le funzioni aziendali.',
  businessFunctionsRemove: 'Rimuovi funzione aziendale',
  managedBusinessFunctions: 'Responsabile delle funzioni',
  managedBusinessFunctionsDescription: 'Se la funzione ha già un responsabile, passa a questa persona.',
  managedBusinessFunctionsPlaceholder: 'Seleziona le funzioni di cui è responsabile…',
  managedBusinessFunctionsSearch: 'Cerca funzioni aziendali…',
  managedBusinessFunctionsEmpty: 'Nessuna funzione aziendale trovata.',
  managedBusinessFunctionsError: 'Impossibile caricare le funzioni aziendali.',
  managedBusinessFunctionsRemove: 'Rimuovi funzione gestita',
  reportsTo: 'Risponde a',
  reportsToPlaceholder: 'Seleziona i responsabili…',
  reportsToSearch: 'Cerca utenti…',
  reportsToEmpty: 'Nessun utente trovato.',
  reportsToError: 'Impossibile caricare gli utenti.',
  reportsToRemove: 'Rimuovi responsabile',
  isAssignable: 'Assegnabile',
  isAssignableDescription: 'Se spento la persona non riceve nessuna assegnazione (Lead, Gestione Richieste, Iscritti, import), anche con sede e competenza.',
  coversAllProductCategories: 'Competente per tutte le categorie',
  coversAllProductCategoriesDescription: 'La persona è competente su qualunque categoria prodotto, a prescindere dalla funzione aziendale: le righe sottostanti spariscono e vengono azzerate.',
  productLines: 'Competenza',
  productLinesHint: 'Ogni riga abbina una funzione aziendale a una categoria prodotto, sottocategorie comprese: spunta "Tutte" per coprire ogni categoria di quella funzione, oppure scegli una categoria madre (es. "Formazione") per coprire lei e le sue figlie. Senza almeno una riga (o senza il flag sopra) non riceve alcuna assegnazione.',
  productLineIncomplete: 'Ogni riga richiede sia la funzione aziendale sia la categoria prodotto (oppure "Tutte").',
  primaryOperationalSite: 'Sede fisica',
  primaryOperationalSitePlaceholder: 'Seleziona una sede fisica…',
  primaryOperationalSiteSearch: 'Cerca sedi operative…',
  primaryOperationalSiteEmpty: 'Nessuna sede operativa trovata.',
  primaryOperationalSiteError: 'Impossibile caricare le sedi operative.',
  remoteOperationalSites: 'Sedi remote',
  remoteOperationalSitesPlaceholder: 'Seleziona una o più sedi remote…',
  remoteOperationalSitesSearch: 'Cerca sedi operative…',
  remoteOperationalSitesEmpty: 'Nessuna sede operativa trovata.',
  remoteOperationalSitesError: 'Impossibile caricare le sedi operative.',
  remoteOperationalSitesRemove: 'Rimuovi sede operativa',
  hiredAt: 'Assunto il',
  terminatedAt: 'Cessato il',
  standardDailyMinutes: 'Durata giornaliera standard',
  breakDailyMinutes: 'Durata pausa giornaliera',
  terminatedBeforeHiredAt: 'La data di cessazione deve essere successiva o uguale a quella di assunzione.',
}

/** Nuove colonne della griglia utenti (spec 0015). */
export const usersColumnsEmployment = {
  business_function: 'Funzione aziendale',
  company: 'Società',
  operational_site: 'Sede operativa',
  relationship_type: 'Tipo di rapporto',
  employment_status: 'Stato',
  managed_business_functions: 'Responsabile delle funzioni',
  reports_to: 'Risponde a',
  hired_at: 'Assunto il',
  terminated_at: 'Cessato il',
}

/** Etichette in sola lettura per le sezioni del rapporto di lavoro (spec 0015). */
export const usersDetailEmployment = {
  functionManager: 'Responsabile',
  jobRoles: 'Mansioni',
  businessFunctions: 'Funzioni aziendali di appartenenza',
  managedBusinessFunctions: 'Responsabile delle funzioni',
  managedBusinessFunctionsEmpty: 'Non è responsabile di nessuna funzione.',
  reportsTo: 'Risponde a',
  productLines: 'Competenza',
  relationshipType: 'Tipo di rapporto',
  company: 'Società',
  primaryOperationalSite: 'Sede fisica',
  remoteOperationalSites: 'Sedi remote',
  hiredAt: 'Assunto il',
  terminatedAt: 'Cessato il',
  standardDailyMinutes: 'Durata giornaliera standard',
  breakDailyMinutes: 'Durata pausa giornaliera',
  hourlyCost: 'Costo orario',
  hourlyCostPerHour: '/h',
  hourlyCostHint: 'Calcolato dalla retribuzione della condizione contrattuale in vigore oggi.',
  collectiveAgreementLevel: 'CCNL e livello',
  contractStartsOn: 'In vigore dal',
  contractReadOnlyHint: 'Mostra la condizione in vigore oggi: si cambia dallo storico contrattuale.',
  terminatedAtHint: 'Alla data di cessazione la persona viene disattivata; i suoi dati restano in archivio.',
  iban: 'IBAN',
  ibanHolder: 'Intestatario',
  contractHistory: {
    add: 'Nuova condizione',
    createNote: 'Lo storico contrattuale si compila dopo il salvataggio dell\'utente.',
    period: 'Dal {{from}} al {{to}}',
    ongoing: 'in corso',
    current: 'Vigente',
    reason: 'Motivo',
    delete: 'Cancella ultima condizione',
    deleteConfirm: 'Cancellare la condizione con decorrenza {{date}}? Potrai poi inserirne una nuova.',
    created: 'Condizione contrattuale aggiunta.',
    deleted: 'Condizione contrattuale cancellata.',
    loadError: 'Impossibile caricare lo storico contrattuale.',
    forbidden: 'Non hai i permessi per questa operazione.',
    notLast: 'Si può cancellare solo l\'ultima condizione.',
    genericError: 'Operazione non riuscita. Riprova.',
    periods: { monthly: 'mese', yearly: 'anno', hourly: 'ora' },
    form: {
      startsOn: 'Decorrenza',
      startsOnRequired: 'Indica la data di decorrenza.',
      relationshipType: 'Tipo di rapporto',
      company: 'Società',
      companyPlaceholder: 'Seleziona una società…',
      companySearch: 'Cerca società…',
      companyEmpty: 'Nessuna società trovata.',
      companyError: 'Impossibile caricare le società.',
      level: 'CCNL e livello',
      levelPlaceholder: 'Seleziona il livello…',
      levelSearch: 'Cerca CCNL o livello…',
      levelEmpty: 'Nessun livello trovato.',
      levelError: 'Impossibile caricare i livelli.',
      salaryAmount: 'Retribuzione',
      salaryPeriod: 'Periodicità',
      salaryPeriodPlaceholder: 'Scegli…',
      salaryInvalid: 'Importo non valido.',
      salaryPeriodRequired: 'Indica la periodicità della retribuzione.',
      changeReason: 'Motivo',
      save: 'Salva',
    },
  },
  none: 'Nessuno',
}

/**
 * Configurazione di assegnazione (direttiva utente 2026-09-11): competenza +
 * sedi, cioè le due metà che il server interseca per decidere chi può ricevere
 * un record. Condivise da form e scheda, così le due superfici non possono
 * raccontare la regola in due modi diversi.
 */
export const usersAssignment = {
  title: 'Configurazione assegnazione',
  sitesHint: "Sede fisica e sedi remote valgono allo stesso modo per l'assegnazione: conta appartenere alla sede, non come ci si lavora.",
  assignable: 'Assegnabile',
  notAssignable: 'Non assegnabile',
  /** Spec 0129 D-1: mostrata al posto del conteggio/elenco quando il flag jolly è attivo. */
  allCategories: 'Tutte le categorie',
  chips: {
    competence: 'Competenze',
    physicalSite: 'Sede fisica',
    remoteSites: 'Sedi remote',
  },
  blockers: {
    disabled: "Assegnazione disattivata dall'impostazione Assegnabile",
    competence: 'Nessuna competenza configurata: senza almeno una coppia funzione aziendale + categoria prodotto la persona non entra in nessun abbinamento.',
    site: 'Nessuna sede operativa: senza sede fisica o remota la persona non entra in nessun abbinamento.',
  },
  stats: {
    competence: 'Competenze',
    sites: 'Sedi',
    sitesBreakdown: '{{physical}} fisica · {{remote}} remote',
    matching: 'Abbinamento',
    blockers: {
      disabled: 'Assegnazione disattivata',
      competence: 'Manca la competenza',
      site: 'Manca la sede',
    },
  },
  summary: { title: 'Riepilogo' },
}
