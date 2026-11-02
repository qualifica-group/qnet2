/**
 * Contract types catalogue (spec 0290). Satellite file to keep `it.ts` within the size limits.
 * Root key `contractTypes.*`.
 */

export const contractTypes = {
  forbidden: 'Non hai il permesso di vedere le tipologie contrattuali.',
  columns: {
    name: 'Nome',
    is_active: 'Attiva',
    sort_order: 'Ordine',
  },
  detail: {
    loadError: 'Impossibile caricare la tipologia contrattuale. Riprova.',
    created_at: 'Creata il',
    updated_at: 'Aggiornata il',
  },
  form: {
    newContractType: 'Nuova tipologia contrattuale',
    createTitle: 'Crea tipologia contrattuale',
    createSubtitle: 'Aggiungi una nuova tipologia contrattuale al catalogo.',
    name: 'Nome',
    isActive: 'Attiva',
    sortOrder: 'Ordine',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Tipologia contrattuale creata con successo.',
    updated: 'Tipologia contrattuale aggiornata con successo.',
    deleted: 'Tipologia contrattuale eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 100 caratteri.',
    sortOrderInvalid: "L'ordine deve essere un numero intero maggiore o uguale a 0.",
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la tipologia contrattuale. Riprova.',
    deleteInUse: 'Impossibile eliminare: la tipologia è usata da almeno una condizione contrattuale. Disattivala invece.',
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
}
