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
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
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
    editTitle: 'Modifica stato progetto',
    editSubtitle: 'Aggiorna lo stato progetto selezionato.',
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
    deleteForbidden: 'Non puoi eliminare questo stato progetto.',
    deleteInUseFallback: 'Questo stato è usato da un progetto o una campagna e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, colore e gruppo dello stato.',
      },
    },
    hints: {
      systemStatusGroup: 'Gli stati di sistema hanno un gruppo fisso e non possono essere riclassificati.',
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati',
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "Nuovo" resta primo e "Chiuso" resta ultimo.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare questi stati.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
