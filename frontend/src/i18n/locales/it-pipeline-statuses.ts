/**
 * Dominio Stati progetto (spec 0023). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); rispecchia `sources` (spec 0018) 1:1,
 * con l'aggiunta dei campi `color`/`sort_order`. La spec 0039 aggiunge gli
 * stati di sistema ("Nuovo"/"Chiuso") e un enum `group` fisso a 3 valori
 * (open/pending/closed), oltre allo sheet di riordino drag & drop per le
 * righe personalizzate.
 */

export const pipelineStatuses = {
  title: 'Stati progetto/campagna',
  subtitle: 'Sfoglia, filtra e gestisci gli stati usati da progetti e campagne.',
  forbidden: 'Non hai i permessi per visualizzare gli stati progetto.',
  columns: {
    name: 'Nome',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
    created_at: 'Creato il',
  },
  advancedFilters: {
    name: 'Nome',
    sortOrderRange: 'Ordine',
    createdRange: 'Creato il',
  },
  detail: {
    title: 'Dettaglio stato progetto',
    subtitle: 'Dettaglio del record selezionato, modificabile sul posto.',
    loadError: 'Impossibile caricare lo stato progetto. Riprova.',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
    created_at: 'Creato il',
  },
  form: {
    newPipelineStatus: 'Nuovo stato',
    createTitle: 'Crea stato progetto',
    createSubtitle: 'Aggiungi un nuovo stato per progetti e campagne.',
    name: 'Nome',
    color: 'Colore',
    group: {
      label: 'Gruppo',
      open: 'Aperto',
      pending: 'In attesa',
      closed: 'Chiuso',
    },
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato progetto creato con successo.',
    updated: 'Stato progetto aggiornato con successo.',
    deleted: 'Stato progetto eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato progetto. Riprova.',
    deleteInUseFallback: 'Questo stato è usato da un progetto o una campagna e non può essere eliminato.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'Il record non è ancora stato creato: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati',
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "Nuovo" resta primo e "Chiuso" resta ultimo.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
