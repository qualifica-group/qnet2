/**
 * User employment profile strings (spec 0015: sezioni Profilo / Rapporto /
 * Dati contrattuali). Extracted from `it.ts` to keep that file within the
 * engineering size limits (see `.claude/rules/engineering.md` §6); merged
 * into the `users.*` namespace from there.
 */

/** `FormSection` header (title/description) for the three employment sections. */
export const usersFormEmploymentSections = {
  profile: {
    title: 'Profilo',
    description: 'Ruolo organizzativo, responsabile e linea di riporto.',
  },
  contract: {
    title: 'Rapporto contrattuale',
    description: 'Tipo di rapporto, società e sede operativa.',
  },
  contractData: {
    title: 'Dati contrattuali',
    description: 'Qualifica, date del rapporto e durate giornaliere.',
  },
}

/** Field labels, placeholders e messaggi di validazione per `employment.*`. */
export const usersFormEmployment = {
  isManager: 'Responsabile',
  isManagerDescription: 'Questa persona è responsabile di altri dipendenti.',
  jobDescription: 'Mansione',
  reportsTo: 'Risponde a',
  reportsToPlaceholder: 'Seleziona un responsabile…',
  reportsToSearch: 'Cerca utenti…',
  reportsToEmpty: 'Nessun utente trovato.',
  reportsToError: 'Impossibile caricare gli utenti.',
  businessFunction: 'Funzione aziendale',
  businessFunctionPlaceholder: 'Seleziona una funzione aziendale…',
  businessFunctionSearch: 'Cerca funzioni aziendali…',
  businessFunctionEmpty: 'Nessuna funzione aziendale trovata.',
  businessFunctionError: 'Impossibile caricare le funzioni aziendali.',
  productCategories: 'Categorie prodotto',
  productCategoriesPlaceholder: 'Seleziona una o più categorie prodotto…',
  productCategoriesSearch: 'Cerca categorie prodotto…',
  productCategoriesEmpty: 'Nessuna categoria prodotto trovata.',
  productCategoriesError: 'Impossibile caricare le categorie prodotto.',
  productCategoriesRemove: 'Rimuovi categoria prodotto',
  relationshipType: 'Tipo di rapporto',
  relationshipTypeNone: 'Nessuno',
  company: 'Società',
  companyPlaceholder: 'Seleziona una società…',
  companySearch: 'Cerca società…',
  companyEmpty: 'Nessuna società trovata.',
  companyError: 'Impossibile caricare le società.',
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
  qualificationType: 'Qualifica',
  qualificationTypeNone: 'Nessuna',
  hiredAt: 'Assunto il',
  terminatedAt: 'Cessato il',
  standardDailyMinutes: 'Durata giornaliera standard',
  breakDailyMinutes: 'Durata pausa giornaliera',
  jobDescriptionMax: 'La mansione può contenere al massimo 255 caratteri.',
  terminatedBeforeHiredAt: 'La data di cessazione deve essere successiva o uguale a quella di assunzione.',
}

/** Nuove colonne della griglia utenti (spec 0015). */
export const usersColumnsEmployment = {
  business_function: 'Funzione aziendale',
  company: 'Società',
  operational_site: 'Sede operativa',
  relationship_type: 'Tipo di rapporto',
  qualification_type: 'Qualifica',
  is_manager: 'Responsabile',
  reports_to: 'Risponde a',
  hired_at: 'Assunto il',
  terminated_at: 'Cessato il',
}

/** Etichette in sola lettura per la sezione Rapporto di lavoro (spec 0015). */
export const usersDetailEmployment = {
  title: 'Rapporto di lavoro',
  isManager: 'Responsabile',
  jobDescription: 'Mansione',
  reportsTo: 'Risponde a',
  businessFunction: 'Funzione aziendale',
  relationshipType: 'Tipo di rapporto',
  company: 'Società',
  primaryOperationalSite: 'Sede fisica',
  remoteOperationalSites: 'Sedi remote',
  qualificationType: 'Qualifica',
  hiredAt: 'Assunto il',
  terminatedAt: 'Cessato il',
  standardDailyMinutes: 'Durata giornaliera standard',
  breakDailyMinutes: 'Durata pausa giornaliera',
  none: 'Nessuno',
}
