/**
 * Catalogo struttura organizzativa HR (spec 0271). File affiancato per mantenere `it.ts` entro i limiti dimensionali.
 */

export const jobActivities = {
  title: 'Attività',
  subtitle: 'Sfoglia, filtra e gestisci le attività collegate alle mansioni.',
  forbidden: 'Non hai i permessi per visualizzare le attività.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    job_roles: 'Mansioni',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio attività',
    subtitle: 'Visualizzazione in sola lettura dell’attività selezionata.',
    loadError: 'Impossibile caricare l’attività. Riprova.',
    jobRoles: 'Mansioni',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newJobActivity: 'Nuova attività',
    createTitle: 'Crea attività',
    createSubtitle: 'Aggiungi una nuova attività.',
    name: 'Nome',
    description: 'Descrizione',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Attività creata con successo.',
    updated: 'Attività aggiornata con successo.',
    deleted: 'Attività eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 1000 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare l’attività. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa attività.',
    deleteInUse: 'Impossibile eliminare: l’attività è collegata a una mansione.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome e descrizione.',
      },
    },
  },
}
