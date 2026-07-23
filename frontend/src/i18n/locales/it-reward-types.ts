/**
 * Dominio Buoni, Premi e Incentivi (spec 0058). Estratto in un file affiancato
 * per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Anagrafica pura delle TIPOLOGIE di
 * buono/premio/incentivo: solo `name` e `color` (token palette), nessuno
 * strato "status di sistema" (niente `group`/`sort_order`/riordino, a
 * differenza del template `opportunity-statuses`).
 */

export const rewardTypes = {
  title: 'Buoni, Premi e Incentivi',
  subtitle: 'Sfoglia, filtra e gestisci le tipologie di buono, premio o incentivo.',
  forbidden: 'Non hai i permessi per visualizzare le tipologie di premio.',
  columns: {
    name: 'Nome',
    color: 'Colore',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  advancedFilters: {
    name: 'Nome',
    createdRange: 'Creato il',
    updatedRange: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio tipologia di premio',
    subtitle: 'Visualizzazione in sola lettura della tipologia selezionata.',
    loadError: 'Impossibile caricare la tipologia di premio. Riprova.',
    color: 'Colore',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newRewardType: 'Nuova tipologia',
    createTitle: 'Crea tipologia di premio',
    createSubtitle: 'Aggiungi una nuova tipologia di buono, premio o incentivo.',
    editTitle: 'Modifica tipologia di premio',
    editSubtitle: 'Aggiorna la tipologia di premio selezionata.',
    name: 'Nome',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    color: 'Colore',
    colorRequired: 'Il colore è obbligatorio.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Tipologia di premio creata con successo.',
    updated: 'Tipologia di premio aggiornata con successo.',
    deleted: 'Tipologia di premio eliminata con successo.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la tipologia di premio. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa tipologia di premio.',
    deleteInUseFallback: 'Questa tipologia di premio è in uso e non può essere eliminata.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome e colore della tipologia.',
      },
    },
  },
}
