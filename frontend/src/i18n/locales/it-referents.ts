/**
 * Domini Referenti + Tipi referente (spec 0016). Estratto in un file
 * affiancato per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); la forma pubblica non cambia.
 */

export const referents = {
  title: 'Referenti',
  subtitle: 'Sfoglia, filtra e gestisci i referenti di contatto della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare i referenti.',
  columns: {
    name: 'Nome',
    referent_type: 'Tipo referente',
    contact_scope: 'Ambito contatto',
    primary_contact: 'Contatto principale',
    created_at: 'Creato il',
    user: 'Utente collegato',
  },
  detail: {
    title: 'Dettaglio referente',
    subtitle: 'Dettaglio del referente selezionato, modificabile sul posto.',
    loadError: 'Impossibile caricare il referente. Riprova.',
    details: 'Dettagli',
    // Etichette della striscia KPI: sono SUE, non i titoli dei blocchi laterali
    // Contatti/Indirizzi, altrimenti la stessa parola direbbe due cose diverse.
    stats: {
      contacts: 'Recapiti',
      addresses: 'Sedi',
      cardType: 'Tipo anagrafica',
    },
  },
  form: {
    newReferent: 'Nuovo referente',
    createTitle: 'Crea referente',
    createSubtitle: 'Aggiungi un nuovo referente alla tua organizzazione.',
    referentType: 'Tipo referente',
    referentTypePlaceholder: 'Seleziona un tipo referente…',
    referentTypeSearch: 'Cerca tipi referente…',
    referentTypeEmpty: 'Nessun tipo referente trovato.',
    referentTypeError: 'Impossibile caricare i tipi referente.',
    linkedUser: 'Utente collegato',
    linkedUserPlaceholder: 'Seleziona un utente…',
    linkedUserSearch: 'Cerca utenti…',
    linkedUserEmpty: 'Nessun utente trovato.',
    linkedUserError: 'Impossibile caricare gli utenti.',
    supplierRegistry: 'Anagrafica fornitore',
    supplierRegistryPlaceholder: 'Seleziona un’anagrafica fornitore…',
    supplierRegistrySearch: 'Cerca fornitori…',
    supplierRegistryEmpty: 'Nessun fornitore trovato.',
    supplierRegistryError: 'Impossibile caricare i fornitori.',
    contactScope: 'Ambito contatto',
    notes: 'Note',
    notesMax: 'Le note possono contenere al massimo 5000 caratteri.',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Referente creato con successo.',
    updated: 'Referente aggiornato con successo.',
    deleted: 'Referente eliminato con successo.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il referente. Riprova.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'Il referente non è ancora stato creato: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    deleteForbidden: 'Non puoi eliminare questo referente.',
    sections: {
      identity: {
        title: 'Dati anagrafici',
      },
      details: {
        title: 'Dettagli referente',
      },
      contacts: {
        title: 'Contatti',
      },
      addresses: {
        title: 'Indirizzi',
      },
    },
  },
}

export const referentTypes = {
  title: 'Tipi referente',
  subtitle: 'Sfoglia, filtra e gestisci i tipi referente della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare i tipi referente.',
  columns: {
    name: 'Nome',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio tipo referente',
    subtitle: 'Dettaglio del tipo referente selezionato, modificabile sul posto.',
    loadError: 'Impossibile caricare il tipo referente. Riprova.',
    created_at: 'Creato il',
  },
  form: {
    newReferentType: 'Nuovo tipo referente',
    createTitle: 'Crea tipo referente',
    createSubtitle: 'Aggiungi un nuovo tipo referente alla tua organizzazione.',
    name: 'Nome',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Tipo referente creato con successo.',
    updated: 'Tipo referente aggiornato con successo.',
    deleted: 'Tipo referente eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il tipo referente. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo tipo referente.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'Il tipo referente non è ancora stato creato: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
}
