/**
 * Localized strings for the Company Sites module (spec 0020): grid columns,
 * the in-place editable detail and the create form.
 *
 * Extracted from `it.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Mirrors `en-company-sites.ts` 1:1.
 */
export const companySites = {
  title: 'Società sedi',
  subtitle: 'Sfoglia, filtra e gestisci le sedi della tua società.',
  forbidden: 'Non hai i permessi per visualizzare le società sedi.',
  columns: {
    id: 'ID',
    isDefault: 'Predefinita',
    defaultBadge: 'Predefinita',
    logo: 'Logo',
    name: 'Nome',
    company: 'Società',
    primaryContact: 'Contatto principale',
    city: 'Comune',
    province: 'Provincia',
    region: 'Regione',
    postalCode: 'CAP',
    createdAt: 'Creato il',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettagli sede',
    subtitle: 'Consulta e modifica la sede selezionata.',
    loadError: 'Impossibile caricare la sede. Riprova.',
    defaultBadge: 'Predefinita',
  },
  form: {
    newCompanySite: 'Nuova sede',
    createTitle: 'Crea sede',
    createSubtitle: 'Aggiungi una nuova sede alla tua società.',
    logoLabel: 'Logo',
    name: 'Nome',
    company: 'Società',
    companyPlaceholder: 'Seleziona una società…',
    companySearch: 'Cerca società…',
    companyEmpty: 'Nessuna società trovata.',
    companyError: 'Impossibile caricare le società.',
    notes: 'Note',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Sede creata con successo.',
    updated: 'Sede aggiornata con successo.',
    deleted: 'Sede eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la sede. Riprova.',
    setDefault: 'Imposta come sede predefinita',
    settingDefault: 'Impostazione in corso…',
    defaultSet: 'Questa sede è ora quella predefinita.',
    defaultError: 'Impossibile impostare questa sede come predefinita. Riprova.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'La sede non è ancora stata creata: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      general: {
        title: 'Generale',
      },
      identity: {
        title: 'Dati azienda',
      },
      contacts: {
        title: 'Contatti',
      },
      address: {
        title: 'Indirizzo',
      },
      company: {
        title: 'Società',
      },
      banks: {
        title: 'Banche',
      },
    },
    banks: {
      name: 'Nome',
      iban: 'IBAN',
      notes: 'Note',
      nameRequired: 'Il nome è obbligatorio.',
      nameMax: 'Il nome può contenere al massimo 191 caratteri.',
      ibanMax: "L'IBAN può contenere al massimo 50 caratteri.",
      notesMax: 'Le note possono contenere al massimo 191 caratteri.',
      preferred: 'Banca preferita',
      preferredBadge: 'Preferita',
      add: 'Aggiungi banca',
      editAction: 'Modifica banca',
      deleteAction: 'Elimina banca',
      deleteConfirm: 'Questa banca verrà rimossa dalla sede.',
      empty: 'Nessuna banca inserita.',
      cancel: 'Annulla',
      save: 'Salva',
    },
  },
}
