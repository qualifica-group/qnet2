/**
 * Catalogo corsi del personale (spec 0284). File satellite per restare nei limiti dimensionali.
 * Mirror di `en-staff-courses.ts` — stesso set di chiavi. Root key `staffCourses.*`.
 */

export const staffCourses = {
  forbidden: 'Non hai il permesso di vedere i corsi.',
  columns: {
    name: 'Nome',
    duration_hours: 'Durata (ore)',
    validity_months: 'Validità (mesi)',
    job_roles: 'Mansioni',
    is_active: 'Attivo',
    trainings_count: 'Formazioni',
  },
  detail: {
    loadError: 'Impossibile caricare il corso. Riprova.',
    trainingsCount: 'Formazioni registrate',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newCourse: 'Nuovo corso',
    createTitle: 'Crea corso',
    createSubtitle: 'Aggiungi un corso al catalogo della formazione del personale.',
    name: 'Nome',
    description: 'Descrizione',
    durationHours: 'Durata',
    durationHint: 'In ore, anche con decimali (per esempio 7,5). Lascia vuoto se non è definita.',
    hoursUnit: 'ore',
    validityMonths: 'Validità',
    validityHint: 'Per quanti mesi vale il corso dopo il completamento. Lascia vuoto se non scade.',
    monthsUnit: 'mesi',
    isActive: 'Attivo',
    jobRoles: 'Mansioni che lo richiedono',
    jobRolesHint: 'Chi ha una di queste mansioni deve avere il corso valido: se manca risulta “Mancante” nella Situazione.',
    jobRolesSearch: 'Cerca una mansione…',
    jobRolesPlaceholder: 'Seleziona le mansioni',
    jobRolesEmpty: 'Nessuna mansione trovata.',
    jobRolesError: 'Impossibile caricare le mansioni.',
    jobRolesRemove: 'Rimuovi mansione',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Corso creato con successo.',
    updated: 'Corso aggiornato con successo.',
    deleted: 'Corso eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 255 caratteri.',
    durationInvalid: 'La durata deve essere un numero di ore maggiore di zero (almeno un minuto).',
    validityInvalid: 'La validità deve essere un numero intero di mesi da 1 a 240.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il corso. Riprova.',
    deleteInUse: 'Impossibile eliminare: il corso ha formazioni registrate. Disattivalo invece.',
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
}
