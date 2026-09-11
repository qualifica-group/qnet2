/**
 * Localized strings for the companies module (spec 0010): grid columns, the
 * read-only detail sheet and the create/edit form (general + single embedded
 * address section).
 *
 * Extracted from `it.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Mirrors `en-companies.ts` 1:1.
 */
export const companies = {
  title: 'Società aziendali',
  subtitle: 'Sfoglia, filtra e gestisci le società aziendali della tua applicazione.',
  forbidden: 'Non hai i permessi per visualizzare le società aziendali.',
  columns: {
    id: 'ID',
    denomination: 'Denominazione',
    vat_number: 'Partita IVA',
    city: 'Comune',
    province: 'Provincia',
    region: 'Regione',
    postal_code: 'CAP',
    country: 'Nazione',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettagli società',
    subtitle: 'Visualizzazione in sola lettura della società selezionata.',
    loadError: 'Impossibile caricare la società. Riprova.',
    summary: {
      title: 'Riepilogo',
      description: 'Si aggiorna mentre compili.',
    },
    // Etichette proprie del dettaglio, come le ha `operationalSites.detail`:
    // quelle sotto `columns` sono intestazioni di tabella, non di scheda.
    city: 'Comune',
    province: 'Provincia',
    region: 'Regione',
    country: 'Nazione',
  },
  form: {
    newCompany: 'Nuova società',
    createTitle: 'Crea società',
    createSubtitle: 'Aggiungi una nuova società aziendale alla tua applicazione.',
    editTitle: 'Modifica società',
    editSubtitle: 'Aggiorna la società selezionata.',
    denomination: 'Denominazione',
    vatNumber: 'Partita IVA',
    line1: 'Indirizzo',
    line2: 'Indirizzo (riga 2)',
    postalCode: 'CAP',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Società creata con successo.',
    updated: 'Società aggiornata con successo.',
    deleted: 'Società eliminata con successo.',
    denominationRequired: 'La denominazione è obbligatoria.',
    denominationMax: 'La denominazione può contenere al massimo 255 caratteri.',
    line1Required: "L'indirizzo è obbligatorio se si inserisce un indirizzo.",
    vatNumberInvalid: 'La partita IVA non è valida.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la società. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa società.',
    sections: {
      general: {
        title: 'Generale',
        description: 'Denominazione e partita IVA della società.',
      },
      address: {
        title: 'Indirizzo',
        description: 'Indirizzo della sede legale.',
      },
    },
  },
}
