/**
 * Azioni sul record dell'ODA (spec 0210 MT-11): cambio stato, unifica, storico
 * unificazioni, PDF e invio al fornitore. File laterale di `it.ts`.
 */

export const purchaseOrderActions = {
  buttons: {
    status: 'Cambia stato',
    merge: 'Unifica',
    history: 'Storico unificazioni',
    pdf: 'Stampa PDF',
    send: 'Invia al fornitore',
  },
  tooltips: {
    status: "Sposta l'ordine in un altro stato",
    merge: 'Sposta in questo ordine righe di altre ODA dello stesso fornitore',
    history: 'Ordini unificati in questo',
    pdf: "Scarica l'ordine in PDF",
    send: "Prepara l'email al fornitore con il PDF allegato",
  },
  status: {
    title: 'Cambia stato',
    subtitle: 'ODA {{code}}',
    current: 'Stato attuale',
    newStatus: 'Nuovo stato',
    none: 'Nessun cambio di stato disponibile per questo ordine con i tuoi permessi.',
    confirm: 'Cambia stato',
    done: 'Stato cambiato in {{status}}.',
    error: 'Impossibile cambiare lo stato. Riprova.',
  },
  merge: {
    title: 'Unifica ordini di acquisto',
    subtitle: "Sposta nell'ODA {{code}} righe di altre ODA dello stesso fornitore e della stessa società.",
    loading: 'Caricamento ordini',
    loadError: 'Impossibile caricare gli ordini unificabili.',
    empty: 'Non ci sono altre ODA in bozza o in validazione di questo fornitore e società.',
    unsavedChanges: "Salva le modifiche all'ordine prima di unificare: l'unificazione ha effetto immediato.",
    emptiedWarning: 'Le ODA che restano senza righe vengono eliminate.',
    selectOrder: "Seleziona tutte le righe dell'ordine {{code}}",
    selectLine: 'Seleziona la riga {{description}}',
    lineCount_one: '{{count}} riga',
    lineCount_other: '{{count}} righe',
    confirm_one: 'Unifica {{count}} riga',
    confirm_other: 'Unifica {{count}} righe',
    done_one: '{{count}} riga unificata.',
    done_other: '{{count}} righe unificate.',
    deleted_one: '{{count}} ODA svuotata è stata eliminata.',
    deleted_other: '{{count}} ODA svuotate sono state eliminate.',
    error: 'Impossibile unificare le righe. Riprova.',
  },
  history: {
    title: 'Storico unificazioni',
    subtitle: "Ordini unificati nell'ODA {{code}}, dal più recente.",
    loadError: 'Impossibile caricare lo storico delle unificazioni.',
    empty: 'Nessuna unificazione eseguita su questo ordine.',
    actions: {
      deleted: 'Ordine eliminato',
      partial: 'Unificazione parziale',
    },
    movedLines: 'Righe spostate',
    by: 'da {{name}}',
    movedTotal: 'Totale spostato',
  },
  pdf: {
    ready: 'PDF generato.',
    forbidden: 'Non puoi scaricare questo PDF.',
    error: 'Impossibile generare il PDF. Riprova.',
  },
  send: {
    error: "Impossibile preparare l'email. Riprova.",
  },
}
