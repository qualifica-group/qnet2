/**
 * Dominio Stati Contratto (spec 0072). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Sibling più vicino di
 * `quote-statuses` (stessa forma di configuratore stati), esteso con
 * `description`, `is_active` e `is_default` esclusivo (BR-5, stesso
 * invariante di `DocumentLayoutDefaultManager`). Le 7 righe seminate (D-2)
 * comprendono 4 righe di sistema: "Da validare" (HEAD) e la terna
 * "Sospeso"/"Annullato"/"Disdetto" (TAIL); l'enum `group` è dedicato
 * (`ContractStatusGroup`, D-5) con la fase chiusa che porta con sé l'esito
 * (open/pending/closed_won/closed_lost).
 */

export const contractStatuses = {
  title: 'Stati contratto',
  subtitle: 'Sfoglia, filtra e gestisci gli stati usati dai contratti.',
  forbidden: 'Non hai i permessi per visualizzare gli stati contratto.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
    is_active: 'Attivo',
    is_default: 'Predefinito',
    created_at: 'Creato il',
  },
  advancedFilters: {
    name: 'Nome',
    isActive: 'Attivo',
    isDefault: 'Predefinito',
    sortOrderRange: 'Ordine',
    createdRange: 'Creato il',
  },
  detail: {
    title: 'Dettaglio stato contratto',
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
    loadError: 'Impossibile caricare lo stato contratto. Riprova.',
    description: 'Descrizione',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
    isActive: 'Attivo',
    isDefault: 'Predefinito',
    created_at: 'Creato il',
  },
  form: {
    newContractStatus: 'Nuovo stato',
    createTitle: 'Crea stato contratto',
    createSubtitle: 'Aggiungi un nuovo stato per i contratti.',
    editTitle: 'Modifica stato contratto',
    editSubtitle: 'Aggiorna lo stato contratto selezionato.',
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    group: {
      label: 'Gruppo',
      open: 'Aperto',
      pending: 'Pending',
      closed_won: 'Chiuso positivo',
      closed_lost: 'Chiuso negativo',
    },
    isActive: 'Attivo',
    isDefault: 'Predefinito',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato contratto creato con successo.',
    updated: 'Stato contratto aggiornato con successo.',
    deleted: 'Stato contratto eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    defaultRequiresActive: 'Uno stato predefinito deve essere attivo.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato contratto. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato contratto.',
    deleteInUseFallback: 'Questo stato contratto è usato da un contratto e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione, colore, gruppo e stato del contratto.',
      },
    },
    hints: {
      systemStatusFields: 'Gli stati di sistema hanno campi fissi: solo nome e colore sono modificabili.',
      cannotUnsetDefault: 'Per rimuovere il predefinito, riassegnalo a un altro stato.',
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati',
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "Da validare" resta primo e "Sospeso"/"Annullato"/"Disdetto" restano ultimi.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare questi stati.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
