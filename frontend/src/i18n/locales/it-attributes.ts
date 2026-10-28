/**
 * Dominio Attributi (spec 0017). Separato da `it-products.ts` per tenere
 * quel file entro i limiti dimensionali (`.claude/rules/engineering.md` §6).
 */

export const attributes = {
  title: 'Attributi',
  subtitle: 'Sfoglia, filtra e gestisci gli attributi dinamici riutilizzabili.',
  forbidden: 'Non hai i permessi per visualizzare gli attributi.',
  columns: {
    code: 'Codice',
    name: 'Nome',
    type: 'Tipo',
    created_at: 'Creato il',
  },
  detail: {
    loadError: "Impossibile caricare l'attributo. Riprova.",
    details: 'Dettagli',
    created_at: 'Creato il',
  },
  form: {
    newAttribute: 'Nuovo attributo',
    createTitle: 'Crea attributo',
    createSubtitle: 'Aggiungi un nuovo attributo dinamico riutilizzabile.',
    code: 'Codice',
    name: 'Nome',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Attributo creato con successo.',
    updated: 'Attributo aggiornato con successo.',
    deleted: 'Attributo eliminato con successo.',
    codeRequired: 'Il codice è obbligatorio.',
    codeMax: 'Il codice deve avere al massimo 64 caratteri.',
    codeInvalid: 'Il codice deve essere un identificatore snake_case minuscolo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome deve avere al massimo 191 caratteri.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: "Impossibile eliminare l'attributo. Riprova.",
    deleteForbidden: 'Non puoi eliminare questo attributo.',
    deleteInUse: 'Questo attributo è assegnato a una categoria o ha valori prodotto.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: "L'attributo non è ancora stato creato: i dati inseriti andranno persi.",
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    optionValueRequired: 'Il valore è obbligatorio.',
    optionValueMax: 'Il valore deve avere al massimo 191 caratteri.',
    optionLabelRequired: "L'etichetta è obbligatoria.",
    optionLabelMax: "L'etichetta deve avere al massimo 191 caratteri.",
    optionsRequiredForEnum: 'Aggiungi almeno una opzione per un attributo a elenco.',
    optionValuesDuplicate: 'I valori delle opzioni devono essere unici.',
    relationEntityTypeRequired: 'Il modulo di destinazione della relazione è obbligatorio.',
    relationForSelectResourceRequired: 'La risorsa del selettore è obbligatoria.',
  },
  layout: {
    // Titolo della sezione sintetica "Altre informazioni" generata a runtime
    // da `AttributeLayoutRenderer` (spec 0062) per gli attributi effettivi
    // non piazzati in nessuna sezione del layout configurato — mai persistita.
    otherInformation: 'Altre informazioni',
    // Hint on a conditional section (spec 0288): the boolean attribute that shows it.
    visibleWhen: 'Visibile se: {{name}}',
  },
}
