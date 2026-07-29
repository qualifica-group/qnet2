/**
 * Dominio Stati Offerta (spec 0065). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Clone di `opportunity-statuses` (D-2),
 * con il messaggio del delete-guard adattato alle Offerte. Gli stati di
 * sistema sono "Bozza"/"Accettata"/"Rifiutata" e un enum `group` fisso a 3
 * valori (open/pending/closed), oltre allo sheet di riordino drag & drop per
 * le righe personalizzate.
 */

export const quoteStatuses = {
  title: 'Stati offerta',
  subtitle: 'Sfoglia, filtra e gestisci gli stati usati dalle offerte.',
  forbidden: 'Non hai i permessi per visualizzare gli stati offerta.',
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
    title: 'Dettaglio stato offerta',
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
    loadError: 'Impossibile caricare lo stato offerta. Riprova.',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
    created_at: 'Creato il',
  },
  form: {
    newQuoteStatus: 'Nuovo stato',
    createTitle: 'Crea stato offerta',
    createSubtitle: 'Aggiungi un nuovo stato per le offerte.',
    editTitle: 'Modifica stato offerta',
    editSubtitle: 'Aggiorna lo stato offerta selezionato.',
    name: 'Nome',
    color: 'Colore',
    group: {
      label: 'Gruppo',
      open: 'Aperto',
      pending: 'In pending',
      closed: 'Chiuso',
    },
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato offerta creato con successo.',
    updated: 'Stato offerta aggiornato con successo.',
    deleted: 'Stato offerta eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato offerta. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato offerta.',
    deleteInUseFallback: "Questo stato offerta è usato da un'offerta e non può essere eliminato.",
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
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "Bozza" resta prima e "Rifiutata" resta ultima.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare questi stati.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
