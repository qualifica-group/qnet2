/**
 * Dominio Modelli di Task (spec 0124). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Un modello e' testata + righe ordinate
 * (D-1): chi crea una Commessa puo' sceglierne uno attivo per generare i task
 * corrispondenti, come copie indipendenti (D-6).
 */

export const taskTemplates = {
  title: 'Modelli di Task',
  subtitle: 'Configura i modelli usati per generare i task di una Commessa.',
  forbidden: 'Non hai i permessi per visualizzare i modelli di task.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    items_count: 'Righe',
    is_active: 'Attivo',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio modello di task',
    subtitle: 'Visualizzazione in sola lettura del modello selezionato.',
    loadError: 'Impossibile caricare il modello di task. Riprova.',
    description: 'Descrizione',
    isActive: 'Attivo',
    itemsCount: '{{count}} righe',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
    items: {
      title: 'Righe',
      dueOffsetDays: 'Scadenza: +{{count}} giorni',
      estimatedMinutes: 'Stima: {{value}} min',
    },
  },
  form: {
    newTaskTemplate: 'Nuovo modello di task',
    createTitle: 'Crea modello di task',
    createSubtitle: 'Aggiungi un nuovo modello di task.',
    editTitle: 'Modifica modello di task',
    editSubtitle: 'Aggiorna il modello di task selezionato.',
    name: 'Nome',
    description: 'Descrizione',
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Modello di task creato con successo.',
    updated: 'Modello di task aggiornato con successo.',
    deleted: 'Modello di task eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il modello di task. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo modello di task.',
    deleteInUse:
      'Questo modello di task è stato usato per generare commesse e non può essere eliminato. Disattivalo.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione e stato.',
      },
      items: {
        title: 'Righe',
        description: 'Task generati dal modello, in ordine.',
      },
      stages: {
        title: 'Fasi',
        description: 'Raggruppa le righe in fasi, trascinale tra una fase e l\'altra.',
      },
    },
    /** Spec 0146 D-2: le fasi raggruppano le righe del modello; copiate nei `work_order_stages` della commessa alla generazione. */
    stages: {
      add: 'Aggiungi fase',
      namePlaceholder: 'Nome fase…',
      remove: 'Rimuovi fase',
      dragHandleLabel: 'Riordina fase',
      noStage: 'Senza fase',
      empty: 'Nessuna fase.',
    },
    items: {
      add: 'Aggiungi riga',
      remove: 'Rimuovi riga',
      dragHandleLabel: 'Riordina riga',
      title: 'Titolo',
      description: 'Descrizione',
      descriptionPlaceholder: 'Descrizione facoltativa…',
      estimatedMinutes: 'Tempo stimato (min)',
      dueOffsetDays: 'Scadenza (giorni da inizio Commessa)',
      status: 'Stato iniziale',
      statusPlaceholder: 'Nessuno stato',
      statusSearchPlaceholder: 'Cerca stato…',
      statusEmpty: 'Nessuno stato disponibile.',
      statusError: 'Impossibile caricare gli stati.',
      statusClear: 'Rimuovi stato selezionato',
      attachments: 'Allegati',
      attachmentsAdd: 'Aggiungi allegato',
      attachmentsRemove: 'Rimuovi allegato',
      attachmentsUploadFailed: 'Caricamento non riuscito per: {{files}}.',
      required: 'Aggiungi almeno una riga.',
      tooMany: 'Puoi aggiungere al massimo {{max}} righe.',
      titleRequired: 'Il titolo è obbligatorio.',
      titleMax: 'Il titolo può contenere al massimo 191 caratteri.',
      dueOffsetInvalid: 'La scadenza deve essere un numero di giorni tra 0 e {{max}}.',
      estimatedMinutesInvalid: 'Il tempo stimato non è valido.',
      hasErrors: 'Correggi le righe evidenziate prima di salvare.',
    },
  },
}
