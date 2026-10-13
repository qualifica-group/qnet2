/**
 * Dominio Tipi di costo (spec 0210). File affiancato per mantenere `it.ts`
 * entro i limiti dimensionali. Anagrafica configurabile scelta su ogni riga
 * degli ordini di acquisto.
 */

export const costTypes = {
  title: 'Tipi di costo',
  subtitle: 'Sfoglia, filtra e gestisci i tipi di costo usati nelle righe degli ordini di acquisto.',
  forbidden: 'Non hai i permessi per visualizzare i tipi di costo.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    nature: 'Natura',
    color: 'Colore',
    sort_order: 'Ordine',
    is_active: 'Attivo',
    lines_count: 'Righe ODA',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio tipo di costo',
    subtitle: 'Visualizzazione in sola lettura del tipo di costo selezionato.',
    loadError: 'Impossibile caricare il tipo di costo. Riprova.',
    sortOrder: 'Ordine',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newCostType: 'Nuovo tipo di costo',
    createTitle: 'Crea tipo di costo',
    createSubtitle: 'Aggiungi un nuovo tipo di costo.',
    name: 'Nome',
    description: 'Descrizione',
    nature: 'Natura',
    color: 'Colore',
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Tipo di costo creato con successo.',
    updated: 'Tipo di costo aggiornato con successo.',
    deleted: 'Tipo di costo eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 100 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    natureRequired: 'La natura è obbligatoria.',
    colorRequired: 'Il colore è obbligatorio.',
    colorInvalid: 'Scegli uno dei colori disponibili.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il tipo di costo. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo tipo di costo.',
    deleteInUse: 'Impossibile eliminare: il tipo di costo è usato da righe di ordini di acquisto.',
    hints: {
      nature:
        'Diretto: imputabile a una commessa. Indiretto: costo di produzione, ripartito. Generale: struttura e spese generali.',
    },
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione, natura, colore e stato.',
      },
    },
  },
  nature: {
    direct: 'Diretto',
    indirect: 'Indiretto',
    general: 'Generale',
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina i tipi di costo',
    subtitle: 'Trascina le righe per cambiarne l’ordine. L’ordine vale per la tabella e per tutti i menu a tendina.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare l’elenco. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare queste righe.',
    genericError: 'Impossibile aggiornare l’ordine. Riprova.',
    inactiveBadge: 'Non attivo',
  },
}
