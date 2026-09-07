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
    subtitle: 'Visualizzazione in sola lettura del referente selezionato.',
    loadError: 'Impossibile caricare il referente. Riprova.',
    details: 'Dettagli',
  },
  form: {
    newReferent: 'Nuovo referente',
    createTitle: 'Crea referente',
    createSubtitle: 'Aggiungi un nuovo referente alla tua organizzazione.',
    editTitle: 'Modifica referente',
    editSubtitle: 'Aggiorna il referente selezionato.',
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
    contactScope: 'Ambito contatto',
    activitySectors: 'Settori attività',
    activitySectorsComingSoon: 'Prossimamente',
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
    deleteForbidden: 'Non puoi eliminare questo referente.',
    sections: {
      identity: {
        title: 'Dati anagrafici',
        description: 'Dati identificativi del referente.',
      },
      details: {
        title: 'Dettagli referente',
        description: 'Tipo, utente collegato, ambito di contatto e note.',
      },
      contacts: {
        title: 'Contatti',
        description: 'Recapiti telefonici ed email.',
      },
      addresses: {
        title: 'Indirizzi',
        description: 'Sedi e indirizzi di fatturazione.',
      },
    },
    duplicateWarning: {
      title: 'Possibile duplicato',
      entry: '{{name}} potrebbe essere un duplicato ({{criteria}}).',
      criteria: {
        email: 'email',
        phone: 'telefono',
        mobile: 'cellulare',
        taxCode: 'codice fiscale',
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
    subtitle: 'Visualizzazione in sola lettura del tipo referente selezionato.',
    loadError: 'Impossibile caricare il tipo referente. Riprova.',
    created_at: 'Creato il',
  },
  form: {
    newReferentType: 'Nuovo tipo referente',
    createTitle: 'Crea tipo referente',
    createSubtitle: 'Aggiungi un nuovo tipo referente alla tua organizzazione.',
    editTitle: 'Modifica tipo referente',
    editSubtitle: 'Aggiorna il tipo referente selezionato.',
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
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome del tipo referente.',
      },
    },
  },
}
