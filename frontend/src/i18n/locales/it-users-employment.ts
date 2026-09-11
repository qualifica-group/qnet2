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
    description: 'Ruolo organizzativo, mansione e linea di riporto.',
  },
  contract: {
    title: 'Rapporto contrattuale',
    description: 'Tipo di rapporto e società di riferimento.',
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
  productLines: 'Competenza',
  productLinesHint: 'Ogni riga abbina una funzione aziendale a una categoria prodotto, sottocategorie comprese: sono le competenze su cui la persona può essere assegnata. Senza almeno una riga non riceve alcuna assegnazione.',
  productLineIncomplete: 'Ogni riga richiede sia la funzione aziendale sia la categoria prodotto.',
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

/** Etichette in sola lettura per le sezioni del rapporto di lavoro (spec 0015). */
export const usersDetailEmployment = {
  isManager: 'Responsabile',
  jobDescription: 'Mansione',
  reportsTo: 'Risponde a',
  productLines: 'Competenza',
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

/**
 * Configurazione di assegnazione (direttiva utente 2026-09-11): competenza +
 * sedi, cioè le due metà che il server interseca per decidere chi può ricevere
 * un record. Condivise da form e scheda, così le due superfici non possono
 * raccontare la regola in due modi diversi.
 */
export const usersAssignment = {
  title: 'Configurazione assegnazione',
  description: 'Competenze e sedi: da qui dipende quali offerte possono essere abbinate a questa persona.',
  rule: 'Una richiesta raggiunge questa persona solo se appartiene alla sede della richiesta E una sua competenza copre la categoria prodotto richiesta.',
  sitesHint: "Sede fisica e sedi remote valgono allo stesso modo per l'assegnazione: conta appartenere alla sede, non come ci si lavora.",
  assignable: 'Assegnabile',
  notAssignable: 'Non assegnabile',
  chips: {
    competence: 'Competenze',
    physicalSite: 'Sede fisica',
    remoteSites: 'Sedi remote',
  },
  blockers: {
    competence: 'Nessuna competenza configurata: senza almeno una coppia funzione aziendale + categoria prodotto la persona non entra in nessun abbinamento.',
    site: 'Nessuna sede operativa: senza sede fisica o remota la persona non entra in nessun abbinamento.',
  },
  stats: {
    competence: 'Competenze',
    sites: 'Sedi',
    sitesBreakdown: '{{physical}} fisica · {{remote}} remote',
    matching: 'Abbinamento',
    blockers: {
      competence: 'Manca la competenza',
      site: 'Manca la sede',
    },
  },
  summary: {
    title: 'Riepilogo',
    description: 'Si aggiorna mentre compili.',
  },
}
