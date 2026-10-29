/**
 * Dominio Tag (spec 0019). Estratto in un file affiancato per mantenere
 * `it.ts` entro i limiti dimensionali (vedi `.claude/rules/engineering.md`
 * §6); rispecchia `sources`/`referentTypes` 1:1, più il ramo di eliminazione
 * bloccata (409) che rispecchia `productCategories`.
 */

export const tags = {
  title: 'Tag',
  subtitle: 'Sfoglia, filtra e gestisci i tag della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare i tag.',
  columns: {
    name: 'Nome',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio tag',
    subtitle: 'Dettaglio del tag selezionato, modificabile sul posto.',
    loadError: 'Impossibile caricare il tag. Riprova.',
    created_at: 'Creato il',
  },
  form: {
    newTag: 'Nuovo tag',
    createTitle: 'Crea tag',
    createSubtitle: 'Aggiungi un nuovo tag alla tua organizzazione.',
    name: 'Nome',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Tag creato con successo.',
    updated: 'Tag aggiornato con successo.',
    deleted: 'Tag eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il tag. Riprova.',
    deleteInUse: 'Impossibile eliminare: il tag è associato ad almeno un record.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'Il tag non è ancora stato creato: i dati inseriti andranno persi.',
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
