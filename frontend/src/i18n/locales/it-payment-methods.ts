/**
 * Dominio Modalità di Pagamento (spec 0068). Estratto in un file affiancato
 * per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Anagrafica delle modalità di pagamento
 * selezionabili nel gestionale (preventivi, offerte, contratti, ordini,
 * fatture e moduli futuri), clone di `reward-statuses` con `code`
 * (immutabile dopo la create, D-3), `payment_instructions` e `payment_days`
 * al posto di `color`, senza riga di sistema (nessun consumatore esiste
 * ancora, D-2).
 */

export const paymentMethods = {
  title: 'Modalità di Pagamento',
  subtitle: 'Sfoglia, filtra e gestisci le modalità di pagamento disponibili nel gestionale.',
  forbidden: 'Non hai i permessi per visualizzare le modalità di pagamento.',
  columns: {
    name: 'Nome',
    code: 'Codice',
    description: 'Descrizione',
    payment_days: 'Giorni di pagamento',
    sort_order: 'Ordine',
    is_active: 'Attivo',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  advancedFilters: {
    isActive: 'Attivo',
    paymentDaysRange: 'Giorni di pagamento',
  },
  detail: {
    title: 'Dettaglio modalità di pagamento',
    subtitle: 'Visualizzazione in sola lettura della modalità di pagamento selezionata.',
    loadError: 'Impossibile caricare la modalità di pagamento. Riprova.',
    description: 'Descrizione',
    payment_instructions: 'Istruzioni di pagamento',
    payment_days: 'Giorni di pagamento',
    sort_order: 'Ordine',
    is_active: 'Attivo',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newPaymentMethod: 'Nuova modalità di pagamento',
    createTitle: 'Crea modalità di pagamento',
    createSubtitle: 'Aggiungi una nuova modalità di pagamento.',
    editTitle: 'Modifica modalità di pagamento',
    editSubtitle: 'Aggiorna la modalità di pagamento selezionata.',
    name: 'Nome',
    code: 'Codice',
    description: 'Descrizione',
    paymentInstructions: 'Istruzioni di pagamento',
    paymentDays: 'Giorni di pagamento',
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Modalità di pagamento creata con successo.',
    updated: 'Modalità di pagamento aggiornata con successo.',
    deleted: 'Modalità di pagamento eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    codeRequired: 'Il codice è obbligatorio.',
    codeMax: 'Il codice può contenere al massimo 64 caratteri.',
    codeInvalid:
      'Il codice deve iniziare con una lettera minuscola e contenere solo lettere minuscole, cifre e underscore.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    paymentInstructionsMax: 'Le istruzioni di pagamento possono contenere al massimo 5000 caratteri.',
    paymentDaysInvalid: 'I giorni di pagamento devono essere un numero intero.',
    paymentDaysMin: 'I giorni di pagamento non possono essere negativi.',
    paymentDaysMax: 'I giorni di pagamento possono essere al massimo 3650.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la modalità di pagamento. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa modalità di pagamento.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, codice, descrizione, istruzioni e termini di pagamento.',
      },
    },
    hints: {
      codeLocked: 'Il codice non può essere modificato dopo la creazione.',
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina modalità di pagamento',
    subtitle: 'Trascina le modalità di pagamento per riordinarle.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare le modalità di pagamento. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare queste modalità di pagamento.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
