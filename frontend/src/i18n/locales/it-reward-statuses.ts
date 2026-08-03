/**
 * Dominio Stati Buoni Collegati (spec 0060). Estratto in un file affiancato
 * per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Anagrafica degli STATI applicabili ai
 * buoni collegati (`rewards`), clone di `opportunity-statuses` con
 * `description` e `is_active` in più. Spec 0073: aggiunge il `group` a
 * quattro fasi (come gli stati offerta) e porta le righe di sistema a quattro
 * ("Aperto", "In attesa", "Chiuso positivo", "Chiuso negativo").
 */

export const rewardStatuses = {
  title: 'Stati Buoni Collegati',
  subtitle: 'Sfoglia, filtra e gestisci gli stati usati dai buoni collegati.',
  forbidden: 'Non hai i permessi per visualizzare gli stati dei buoni collegati.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    group: 'Gruppo',
    sort_order: 'Ordine',
    is_active: 'Attivo',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  advancedFilters: {
    name: 'Nome',
    isActive: 'Attivo',
    sortOrderRange: 'Ordine',
    createdRange: 'Creato il',
    updatedRange: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio stato buono',
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
    loadError: 'Impossibile caricare lo stato del buono. Riprova.',
    description: 'Descrizione',
    color: 'Colore',
    group: 'Gruppo',
    sort_order: 'Ordine',
    is_active: 'Attivo',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newRewardStatus: 'Nuovo stato',
    createTitle: 'Crea stato buono',
    createSubtitle: 'Aggiungi un nuovo stato per i buoni collegati.',
    editTitle: 'Modifica stato buono',
    editSubtitle: 'Aggiorna lo stato buono selezionato.',
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    group: {
      label: 'Gruppo',
      open: 'Aperto',
      pending: 'In pending',
      closed_won: 'Chiuso positivo',
      closed_lost: 'Chiuso negativo',
    },
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato buono creato con successo.',
    updated: 'Stato buono aggiornato con successo.',
    deleted: 'Stato buono eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    colorRequired: 'Il colore è obbligatorio.',
    groupRequired: 'Il gruppo è obbligatorio.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato buono. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato buono.',
    deleteInUseFallback: 'Questo stato buono è usato da un buono collegato e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione, colore e stato attivo/disattivo.',
      },
    },
    hints: {
      systemStatusLocked: 'Gli stati di sistema hanno questi campi fissi e non possono essere modificati.',
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati',
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "In attesa" resta sempre primo.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare questi stati.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
