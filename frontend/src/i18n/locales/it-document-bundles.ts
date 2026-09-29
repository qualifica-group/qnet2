/**
 * Dominio Modelli documenti (spec 0175): tabella di appoggio CRUD con file
 * gestiti da `DocumentsSection` (alias `document_bundle`), usata dal composer
 * email della commessa come sorgente di allegati. File affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6), mirror di `en-document-bundles.ts`.
 */

export const documentBundles = {
  title: 'Modelli documenti',
  subtitle: 'Sfoglia, filtra e gestisci i modelli documenti riusabili nelle commesse.',
  forbidden: 'Non hai i permessi per visualizzare i modelli documenti.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    is_active: 'Attivo',
    files_count: 'File',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio modello documenti',
    subtitle: 'Visualizzazione in sola lettura del modello selezionato.',
    loadError: 'Impossibile caricare il modello documenti. Riprova.',
    description: 'Descrizione',
    isActive: 'Attivo',
    filesCount: 'File',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newDocumentBundle: 'Nuovo modello documenti',
    createTitle: 'Crea modello documenti',
    createSubtitle: 'Aggiungi un nuovo modello documenti.',
    editTitle: 'Modifica modello documenti',
    editSubtitle: 'Aggiorna il modello documenti selezionato.',
    name: 'Nome',
    description: 'Descrizione',
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Modello documenti creato con successo.',
    updated: 'Modello documenti aggiornato con successo.',
    deleted: 'Modello documenti eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    nameDuplicate: 'Esiste già un modello documenti con questo nome.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il modello documenti. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo modello documenti.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione e stato del modello.',
      },
      files: {
        title: 'File',
        description: 'File allegati al modello documenti.',
      },
    },
  },
}
