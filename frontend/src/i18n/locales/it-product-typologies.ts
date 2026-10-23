/**
 * Dominio Tipologie Prodotto (spec 0099). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Anagrafica delle tipologie
 * selezionabili sui Prodotti e usata dal riepilogo per tipologia
 * dell'Offerta, dove viene letta VIVA attraverso il prodotto (D-5). Segue
 * `units-of-measure` per la leanness (niente `is_active`, niente
 * `sort_order`, niente reorder) e per `code` (unico, immutabile dopo la
 * create, D-2).
 *
 * Nota: "Tipologia" e' distinta da "Tipo" (`products.product_type`), l'enum
 * preesistente che questo modulo lascia invariato (D-1).
 */

export const productTypologies = {
  title: 'Tipologie Prodotto',
  subtitle: 'Sfoglia, filtra e gestisci le tipologie usate dal tuo catalogo prodotti.',
  forbidden: 'Non hai i permessi per visualizzare le tipologie prodotto.',
  columns: {
    name: 'Nome',
    code: 'Codice',
    color: 'Colore',
    description: 'Descrizione',
    supplier_commission_enabled: 'Commissione Fornitore',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio tipologia prodotto',
    subtitle: 'Dettaglio della tipologia selezionata, modificabile sul posto.',
    loadError: 'Impossibile caricare la tipologia prodotto. Riprova.',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newProductTypology: 'Nuova tipologia prodotto',
    createTitle: 'Crea tipologia prodotto',
    createSubtitle: 'Aggiungi una nuova tipologia prodotto.',
    editTitle: 'Modifica tipologia prodotto',
    editSubtitle: 'Aggiorna la tipologia prodotto selezionata.',
    name: 'Nome',
    code: 'Codice',
    color: 'Colore',
    description: 'Descrizione',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Tipologia prodotto creata con successo.',
    updated: 'Tipologia prodotto aggiornata con successo.',
    deleted: 'Tipologia prodotto eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    codeRequired: 'Il codice è obbligatorio.',
    codeMax: 'Il codice può contenere al massimo 64 caratteri.',
    codeInvalid:
      'Il codice deve iniziare con una lettera minuscola e contenere solo lettere minuscole, cifre e underscore.',
    colorRequired: 'Il colore è obbligatorio.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    supplierCommissionEnabled: "Calcolo commissione Fornitore",
    proformaKind: 'Tipo richiesta proforma',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la tipologia prodotto. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa tipologia prodotto.',
    deleteInUse:
      'Impossibile eliminare la Tipologia Prodotto perché risulta associata a uno o più prodotti.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'La tipologia prodotto non è ancora stata creata: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      billing: {
        title: 'Fatturazione',
        description: 'In quale richiesta proforma confluiscono le righe di questa tipologia.',
      },
      commission: {
        title: "Commissione Fornitore",
        description: "Se e come si calcola la commissione Fornitore.",
      },
      identity: {
        title: 'Dettagli',
        description: 'Nome, codice e descrizione.',
      },
    },
    hints: {
      proformaKind:
        'Decide in quale richiesta proforma confluiscono le righe dei prodotti di questa tipologia. Nessuna: le righe non si fatturano tramite richiesta proforma.',
    },
  },
  proformaKind: {
    none: 'Nessuna',
    consultancy: 'Consulenza',
    institution: 'Ente',
  },
}
