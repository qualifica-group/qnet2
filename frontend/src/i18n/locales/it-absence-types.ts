/**
 * Absence types catalogue (spec 0278). Satellite file to keep `it.ts` within the size limits.
 * Root key `absenceTypes.*`.
 */

export const absenceTypes = {
  forbidden: 'Non hai il permesso di vedere i tipi di assenza.',
  columns: {
    name: 'Nome',
    deducts_balance: 'Scala un saldo',
    requires_approval: 'Richiede approvazione',
    is_active: 'Attivo',
    sort_order: 'Ordine',
  },
  detail: {
    loadError: 'Impossibile caricare il tipo di assenza. Riprova.',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newAbsenceType: 'Nuovo tipo di assenza',
    createTitle: 'Crea tipo di assenza',
    createSubtitle: 'Aggiungi un nuovo tipo di assenza al catalogo.',
    name: 'Nome',
    deductsBalance: 'Scala un saldo',
    deductsBalanceHint: 'Se attivo, le richieste di questo tipo riducono il saldo annuale del dipendente.',
    requiresApproval: 'Richiede approvazione',
    isActive: 'Attivo',
    sortOrder: 'Ordine',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Tipo di assenza creato con successo.',
    updated: 'Tipo di assenza aggiornato con successo.',
    deleted: 'Tipo di assenza eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 100 caratteri.',
    sortOrderInvalid: "L'ordine deve essere un numero intero maggiore o uguale a 0.",
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il tipo di assenza. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo tipo di assenza.',
    deleteInUse: 'Impossibile eliminare: il tipo è usato da almeno una richiesta. Disattivalo invece.',
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
}
