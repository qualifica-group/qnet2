/**
 * Dominio Modelli email (spec 0175): tabella di appoggio CRUD usata dal
 * composer email della commessa. File affiancato per mantenere `it.ts` entro
 * i limiti dimensionali (vedi `.claude/rules/engineering.md` §6), mirror di
 * `en-email-templates.ts`.
 *
 * Chiavi base per FE-02 (modulo "Modelli email"): tabella, dettaglio e form
 * con picker segnaposto (AC-022). `modules` ammette work_orders e invoices
 * (D-10, spec 0195), resta una mappa per estendersi in futuro senza
 * rompere la forma.
 */

export const emailTemplates = {
  title: 'Modelli email',
  subtitle: 'Sfoglia, filtra e gestisci i modelli email riusabili nelle commesse.',
  forbidden: 'Non hai i permessi per visualizzare i modelli email.',
  columns: {
    name: 'Nome',
    module: 'Modulo',
    subject: 'Oggetto',
    description: 'Descrizione',
    is_active: 'Attivo',
    created_at: 'Creato il',
  },
  modules: {
    work_orders: 'Commesse',
    invoices: 'Fatture',
  },
  detail: {
    title: 'Dettaglio modello email',
    subtitle: 'Visualizzazione in sola lettura del modello selezionato.',
    loadError: 'Impossibile caricare il modello email. Riprova.',
    module: 'Modulo',
    subject: 'Oggetto',
    body: 'Corpo',
    description: 'Descrizione',
    isActive: 'Attivo',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newEmailTemplate: 'Nuovo modello email',
    createTitle: 'Crea modello email',
    createSubtitle: 'Aggiungi un nuovo modello email.',
    editTitle: 'Modifica modello email',
    editSubtitle: 'Aggiorna il modello email selezionato.',
    name: 'Nome',
    module: 'Modulo',
    subject: 'Oggetto',
    body: 'Corpo',
    description: 'Descrizione',
    isActive: 'Attivo',
    variablesPicker: 'Inserisci segnaposto',
    variablesPickerEmpty: 'Nessun segnaposto disponibile.',
    variablesPickerError: 'Impossibile caricare i segnaposto. Riprova.',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Modello email creato con successo.',
    updated: 'Modello email aggiornato con successo.',
    deleted: 'Modello email eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    nameDuplicate: 'Esiste già un modello con questo nome per questo modulo.',
    subjectRequired: "L'oggetto è obbligatorio.",
    subjectMax: "L'oggetto può contenere al massimo 255 caratteri.",
    bodyRequired: 'Il corpo è obbligatorio.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il modello email. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo modello email.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, modulo, oggetto e stato del modello.',
      },
      content: {
        title: 'Contenuto',
        description: 'Corpo del modello, con segnaposto e picker di inserimento.',
      },
    },
  },
}
