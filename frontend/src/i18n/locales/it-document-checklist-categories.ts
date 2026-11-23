/**
 * Dominio Categorie checklist documentali (spec 0410): categorie sincronizzate
 * con ArchivioSmart. Lo stato di sincronizzazione e lo storico riusano le
 * chiavi `documentChecklists.syncStatus` / `documentChecklists.syncLogs`.
 * Mirror di `en-document-checklist-categories.ts`.
 */

export const documentChecklistCategories = {
  title: 'Categorie checklist',
  subtitle: 'Raggruppa le checklist documentali in categorie, sincronizzate con ArchivioSmart.',
  forbidden: 'Non hai i permessi per visualizzare le categorie delle checklist.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    sort_order: 'Ordine',
    checklists_count: 'Checklist',
    sync_status: 'Sincronizzazione',
    sync_error: 'Errore di sincronizzazione',
    synced_at: 'Ultima sincronizzazione',
    created_at: 'Creata il',
  },
  actions: {
    syncAgain: 'Sincronizza di nuovo',
  },
  toast: {
    syncAgainQueued: 'Sincronizzazione messa in coda.',
    syncError: 'Impossibile avviare la sincronizzazione. Riprova.',
  },
  detail: {
    loadError: 'Impossibile caricare la categoria. Riprova.',
  },
  form: {
    newCategory: 'Nuova categoria',
    createTitle: 'Crea categoria',
    createSubtitle: 'Aggiungi una categoria per raggruppare le checklist.',
    editTitle: 'Modifica categoria',
    editSubtitle: 'Aggiorna la categoria.',
    name: 'Nome',
    description: 'Descrizione',
    sortOrder: 'Ordine',
    sortOrderHint: 'Numero da 0 a 65535: le categorie sono elencate dal più basso.',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Categoria creata con successo.',
    updated: 'Categoria aggiornata con successo.',
    deleted: 'Categoria eliminata con successo.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la categoria. Riprova.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'La categoria non è ancora stata creata: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
  validation: {
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 255 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 2000 caratteri.',
    sortOrderRange: "L'ordine deve essere un intero tra 0 e 65535.",
  },
}
