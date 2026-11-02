/**
 * Dominio Aliquote IVA. Estratto in un file affiancato per mantenere `it.ts`
 * entro i limiti dimensionali (vedi `.claude/rules/engineering.md` §6);
 * rispecchia `sources` (`it-sources.ts`) 1:1, con il campo aggiuntivo `rate`.
 */

export const vatRates = {
  title: 'IVA',
  subtitle: 'Sfoglia, filtra e gestisci le aliquote IVA della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare le aliquote IVA.',
  columns: {
    name: 'Nome',
    rate: 'Aliquota',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio aliquota IVA',
    subtitle: "Dettaglio dell'aliquota IVA selezionata, modificabile sul posto.",
    loadError: "Impossibile caricare l'aliquota IVA. Riprova.",
    details: 'Dettagli',
    created_at: 'Creato il',
  },
  form: {
    newVatRate: 'Nuova aliquota IVA',
    createTitle: 'Crea aliquota IVA',
    createSubtitle: 'Aggiungi una nuova aliquota IVA alla tua organizzazione.',
    editTitle: 'Modifica aliquota IVA',
    editSubtitle: "Aggiorna l'aliquota IVA selezionata.",
    name: 'Nome',
    rate: 'Aliquota',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Aliquota IVA creata con successo.',
    updated: 'Aliquota IVA aggiornata con successo.',
    deleted: 'Aliquota IVA eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    rateRequired: "L'aliquota è obbligatoria.",
    rateInvalid: "L'aliquota deve essere zero o un numero positivo.",
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: "Impossibile eliminare l'aliquota IVA. Riprova.",
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: "L'aliquota IVA non è ancora stata creata: i dati inseriti andranno persi.",
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dettagli',
        description: "Nome e aliquota dell'IVA.",
      },
    },
  },
}
