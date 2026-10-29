/**
 * Dominio Fonti (spec 0018). Estratto in un file affiancato per mantenere
 * `it.ts` entro i limiti dimensionali (vedi `.claude/rules/engineering.md`
 * §6); rispecchia `referentTypes` (spec 0016) 1:1.
 */

export const sources = {
  title: 'Fonti',
  subtitle: 'Sfoglia, filtra e gestisci le fonti della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare le fonti.',
  columns: {
    name: 'Nome',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio fonte',
    subtitle: 'Dettaglio della fonte selezionata, modificabile sul posto.',
    loadError: 'Impossibile caricare la fonte. Riprova.',
    created_at: 'Creato il',
  },
  form: {
    newSource: 'Nuova fonte',
    createTitle: 'Crea fonte',
    createSubtitle: 'Aggiungi una nuova fonte alla tua organizzazione.',
    name: 'Nome',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Fonte creata con successo.',
    updated: 'Fonte aggiornata con successo.',
    deleted: 'Fonte eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la fonte. Riprova.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'La fonte non è ancora stata creata: i dati inseriti andranno persi.',
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
