/**
 * Localized strings for the operational-sites module (spec 0011): grid
 * columns, the read-only detail sheet and the create/edit form (single
 * embedded address — the site has no own name/label, it IS its address).
 *
 * Extracted from `it.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Mirrors `en-operational-sites.ts` 1:1.
 */
export const operationalSites = {
  title: 'Sedi operative',
  subtitle: 'Sfoglia, filtra e gestisci le sedi operative della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare le sedi operative.',
  columns: {
    alias: 'Alias',
    city: 'Comune',
    street: 'Via',
    postal_code: 'CAP',
    province: 'Provincia',
    region: 'Regione',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettagli sede operativa',
    subtitle: 'Visualizzazione in sola lettura della sede operativa selezionata.',
    loadError: 'Impossibile caricare la sede operativa. Riprova.',
    alias: 'Alias',
    line1: 'Via',
    postal_code: 'CAP',
    city: 'Comune',
    province: 'Provincia',
    region: 'Regione',
    country: 'Nazione',
    created_at: 'Creato il',
    summary: {
      title: 'Riepilogo',
      description: 'Si aggiorna mentre compili.',
    },
  },
  form: {
    newOperationalSite: 'Nuova sede operativa',
    createTitle: 'Crea sede operativa',
    createSubtitle: 'Aggiungi una nuova sede operativa alla tua organizzazione.',
    editTitle: 'Modifica sede operativa',
    editSubtitle: 'Aggiorna la sede operativa selezionata.',
    alias: 'Alias',
    line1: 'Via',
    postalCode: 'CAP',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Sede operativa creata con successo.',
    updated: 'Sede operativa aggiornata con successo.',
    deleted: 'Sede operativa eliminata con successo.',
    line1Required: 'La via è obbligatoria.',
    line1Max: 'La via può contenere al massimo 255 caratteri.',
    postalCodeMax: 'Il CAP può contenere al massimo 20 caratteri.',
    aliasMax: "L'alias può contenere al massimo 255 caratteri.",
    cityRequired: 'Il comune è obbligatorio.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la sede operativa. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa sede operativa.',
    sections: {
      address: {
        title: 'Indirizzo',
        description: 'Ubicazione della sede operativa.',
      },
    },
  },
}
