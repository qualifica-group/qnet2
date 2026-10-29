/**
 * Catalogo struttura organizzativa HR (spec 0271). File affiancato per mantenere `it.ts` entro i limiti dimensionali.
 */

export const jobRoles = {
  title: 'Mansioni',
  subtitle: 'Sfoglia, filtra e gestisci le mansioni assegnate alle persone.',
  forbidden: 'Non hai i permessi per visualizzare le mansioni.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    job_activities: 'Attività',
    users_count: 'Persone',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio mansione',
    subtitle: 'Visualizzazione in sola lettura della mansione selezionata.',
    loadError: 'Impossibile caricare la mansione. Riprova.',
    usersCount: 'Persone',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newJobRole: 'Nuova mansione',
    createTitle: 'Crea mansione',
    createSubtitle: 'Aggiungi una nuova mansione.',
    name: 'Nome',
    description: 'Descrizione',
    jobActivities: 'Attività',
    jobActivitiesSearch: 'Cerca attività…',
    jobActivitiesPlaceholder: 'Seleziona le attività',
    jobActivitiesEmpty: 'Nessuna attività trovata.',
    jobActivitiesError: 'Impossibile caricare le attività.',
    jobActivitiesRemove: 'Rimuovi attività',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Mansione creata con successo.',
    updated: 'Mansione aggiornata con successo.',
    deleted: 'Mansione eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 1000 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la mansione. Riprova.',
    deleteInUse: 'Impossibile eliminare: la mansione è assegnata a delle persone.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione e attività.',
      },
    },
  },
}
