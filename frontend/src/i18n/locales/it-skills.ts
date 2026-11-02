/**
 * Catalogo competenze (spec 0286). File satellite per restare nei limiti dimensionali.
 * Mirror di `en-skills.ts` — stesso set di chiavi. Root key `skills.*`.
 */

export const skills = {
  forbidden: 'Non hai il permesso di vedere il catalogo competenze.',
  levels: { 1: 'Base', 2: 'Intermedio', 3: 'Avanzato', 4: 'Esperto' },
  columns: {
    name: 'Nome',
    category: 'Categoria',
    job_roles: 'Mansioni',
    is_active: 'Attiva',
    users_count: 'Persone',
  },
  detail: {
    loadError: 'Impossibile caricare la competenza. Riprova.',
    usersCount: 'Persone con la competenza',
    created_at: 'Creata il',
    updated_at: 'Aggiornata il',
  },
  form: {
    newSkill: 'Nuova competenza',
    createTitle: 'Crea competenza',
    createSubtitle: 'Aggiungi una competenza al catalogo.',
    name: 'Nome',
    category: 'Categoria',
    categoryHint: 'Testo libero, facoltativo: serve a raggruppare le competenze (per esempio “Lingue”).',
    description: 'Descrizione',
    isActive: 'Attiva',
    jobRoles: 'Mansioni che la richiedono',
    jobRole: 'Mansione',
    jobRolePlaceholder: 'Seleziona la mansione',
    jobRoleSearch: 'Cerca una mansione…',
    jobRoleEmpty: 'Nessuna mansione trovata.',
    jobRoleError: 'Impossibile caricare le mansioni.',
    minLevel: 'Livello minimo',
    minLevelShort: 'livello minimo',
    addJobRole: 'Aggiungi mansione',
    removeJobRole: 'Rimuovi mansione',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Competenza creata con successo.',
    updated: 'Competenza aggiornata con successo.',
    deleted: 'Competenza eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 255 caratteri.',
    categoryMax: 'La categoria può contenere al massimo 100 caratteri.',
    jobRoleRequired: 'Scegli la mansione in ogni riga o rimuovi la riga vuota.',
    jobRoleDuplicate: 'La stessa mansione non può comparire due volte.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la competenza. Riprova.',
    deleteInUse: 'Impossibile eliminare: la competenza è assegnata ad almeno una persona. Disattivala invece.',
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
}
