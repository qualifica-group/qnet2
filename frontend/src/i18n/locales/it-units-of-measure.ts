/**
 * Dominio Unita di Misura (spec 0088). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Anagrafica delle unita di misura
 * selezionabili sui Prodotti, congelata sulle righe Offerta/Preventivo al
 * momento del salvataggio (D-5). Segue `vat-rates` per la leanness (niente
 * `is_active`, niente `sort_order`, niente reorder) e `payment-methods` per
 * `code` (unico, immutabile dopo la create, D-1).
 */

export const unitsOfMeasure = {
  title: 'Unita di Misura',
  subtitle: 'Sfoglia, filtra e gestisci le unita di misura usate dal tuo catalogo.',
  forbidden: 'Non hai i permessi per visualizzare le unita di misura.',
  columns: {
    name: 'Nome',
    symbol: 'Simbolo',
    code: 'Codice',
    description: 'Descrizione',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  detail: {
    title: 'Dettaglio unita di misura',
    subtitle: "Visualizzazione in sola lettura dell'unita di misura selezionata.",
    loadError: "Impossibile caricare l'unita di misura. Riprova.",
    symbol: 'Simbolo',
    description: 'Descrizione',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    newUnitOfMeasure: 'Nuova unita di misura',
    createTitle: 'Crea unita di misura',
    createSubtitle: 'Aggiungi una nuova unita di misura.',
    editTitle: 'Modifica unita di misura',
    editSubtitle: "Aggiorna l'unita di misura selezionata.",
    name: 'Nome',
    symbol: 'Simbolo',
    code: 'Codice',
    description: 'Descrizione',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Unita di misura creata con successo.',
    updated: 'Unita di misura aggiornata con successo.',
    deleted: 'Unita di misura eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    symbolRequired: 'Il simbolo è obbligatorio.',
    symbolMax: 'Il simbolo può contenere al massimo 16 caratteri.',
    codeRequired: 'Il codice è obbligatorio.',
    codeMax: 'Il codice può contenere al massimo 64 caratteri.',
    codeInvalid:
      'Il codice deve iniziare con una lettera minuscola e contenere solo lettere minuscole, cifre e underscore.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: "Impossibile eliminare l'unita di misura. Riprova.",
    deleteForbidden: 'Non puoi eliminare questa unita di misura.',
    deleteInUse: "Impossibile eliminare: l'unita di misura è usata da un prodotto o da una riga offerta.",
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, simbolo, codice e descrizione.',
      },
    },
    hints: {
      codeLocked: 'Il codice non può essere modificato dopo la creazione.',
    },
  },
}
