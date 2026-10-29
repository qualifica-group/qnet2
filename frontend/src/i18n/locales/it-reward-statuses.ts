/**
 * Dominio Stati Buoni Collegati (spec 0060). Estratto in un file affiancato
 * per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Anagrafica degli STATI applicabili ai
 * buoni collegati (`rewards`), clone di `opportunity-statuses` con
 * `description` e `is_active` in più. Spec 0073: aggiunge il `group` a tre
 * fasi e porta le righe di sistema a tre ("In attesa", "Approvato",
 * "Negato").
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
    subtitle: 'Dettaglio del record selezionato, modificabile sul posto.',
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
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    group: {
      label: 'Gruppo',
      pending: 'In attesa',
      closed_won: 'Approvato',
      closed_lost: 'Negato',
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
    deleteInUseFallback: 'Questo stato buono è usato da un buono collegato e non può essere eliminato.',
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
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "In attesa" resta sempre primo.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
