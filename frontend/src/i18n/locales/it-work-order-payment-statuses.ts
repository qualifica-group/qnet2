/**
 * Dominio Stati pagamento commessa (spec 0201). File affiancato per mantenere
 * `it.ts` entro i limiti dimensionali. Anagrafica degli stati di pagamento
 * delle righe commessa, stesso schema di `rewardStatuses` senza gruppo e
 * stati di sistema, con il flag "Si può consegnare".
 */

export const workOrderPaymentStatuses = {
  title: 'Stati pagamento commessa',
  subtitle: 'Sfoglia, filtra e gestisci gli stati di pagamento delle righe commessa.',
  forbidden: 'Non hai i permessi per visualizzare gli stati pagamento commessa.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    sort_order: 'Ordine',
    is_active: 'Attivo',
    allows_delivery: 'Si può consegnare',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  advancedFilters: {
    name: 'Nome',
    isActive: 'Attivo',
    allowsDelivery: 'Si può consegnare',
    sortOrderRange: 'Ordine',
    createdRange: 'Creato il',
    updatedRange: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio stato pagamento',
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
    loadError: 'Impossibile caricare lo stato pagamento. Riprova.',
    description: 'Descrizione',
    color: 'Colore',
    sort_order: 'Ordine',
    is_active: 'Attivo',
    allows_delivery: 'Si può consegnare',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newWorkOrderPaymentStatus: 'Nuovo stato',
    createTitle: 'Crea stato pagamento',
    createSubtitle: 'Aggiungi un nuovo stato di pagamento per le righe commessa.',
    editTitle: 'Modifica stato pagamento',
    editSubtitle: 'Aggiorna lo stato pagamento selezionato.',
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    isActive: 'Attivo',
    allowsDelivery: 'Si può consegnare',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato pagamento creato con successo.',
    updated: 'Stato pagamento aggiornato con successo.',
    deleted: 'Stato pagamento eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    colorRequired: 'Il colore è obbligatorio.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato pagamento. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato pagamento.',
    deleteInUseFallback: 'Questo stato pagamento è usato da una riga commessa e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione, colore, stato attivo e consegna.',
      },
    },
    hints: {
      allowsDelivery:
        'Quando una riga passa a uno stato con questo flag, supervisori e partecipanti della commessa vengono avvisati.',
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati',
    subtitle: 'Trascina gli stati per riordinarli.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare questi stati.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
