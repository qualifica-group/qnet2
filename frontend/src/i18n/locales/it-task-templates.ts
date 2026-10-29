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
    subtitle: 'Dettaglio del modello di task selezionato, modificabile sul posto.',
    loadError: 'Impossibile caricare il modello di task. Riprova.',
    itemsCount: '{{count}} righe',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
    items: {
      dueOffsetDays: 'Scadenza: +{{count}} giorni',
      estimatedMinutes: 'Stima: {{value}} min',
      workOrderStatus: 'Stato lavorazione commessa:',
    },
  },
  form: {
    newTaskTemplate: 'Nuovo modello di task',
    createTitle: 'Crea modello di task',
    createSubtitle: 'Aggiungi un nuovo modello di task.',
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
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'Il modello di task non è ancora stato creato: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dettagli',
      },
      items: {
        title: 'Righe',
        description: 'Task generati dal modello, in ordine.',
      },
      stages: {
        title: 'Fasi',
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
      addSubtask: 'Aggiungi sotto-task',
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
      workOrderStatus: 'Stato lavorazione commessa',
      workOrderStatusPlaceholder: 'Nessuno stato lavorazione',
      workOrderStatusSearchPlaceholder: 'Cerca stato…',
      workOrderStatusEmpty: 'Nessuno stato di lavorazione disponibile.',
      workOrderStatusError: 'Impossibile caricare gli stati di lavorazione.',
      workOrderStatusClear: 'Rimuovi stato lavorazione commessa selezionato',
      workOrderStatusDefaultSet: 'Set predefinito',
      attachments: 'Allegati',
      attachmentsAdd: 'Aggiungi allegato',
      attachmentsRemove: 'Rimuovi allegato',
      attachmentsUploadFailed: 'Caricamento non riuscito per: {{files}}.',
      required: 'Aggiungi almeno una riga.',
      tooMany: 'Puoi aggiungere al massimo {{max}} righe.',
      titleRequired: 'Il titolo è obbligatorio.',
      titleMax: 'Il titolo può contenere al massimo 191 caratteri.',
      dueOffsetInvalid: 'La scadenza deve essere un numero di giorni tra 0 e {{max}}.',
      dueOffsetExceedsParent: 'La scadenza di un sotto-task non può superare quella della riga padre.',
      tooDeep: 'Questa riga supera i 3 livelli di sotto-task consentiti sotto una riga radice.',
      estimatedMinutesInvalid: 'Il tempo stimato non è valido.',
      hasErrors: 'Correggi le righe evidenziate prima di salvare.',
    },
  },
}
