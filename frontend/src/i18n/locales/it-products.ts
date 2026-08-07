/**
 * Domini Attributi + Categorie Prodotto + Prodotti (spec 0017). Estratto in un
 * file affiancato per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); la forma pubblica di `it.ts` è invariata.
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
    title: 'Dettagli attributo',
    subtitle: "Vista di sola lettura dell'attributo selezionato.",
    loadError: "Impossibile caricare l'attributo. Riprova.",
    details: 'Dettagli',
    options: 'Opzioni',
    created_at: 'Creato il',
  },
  form: {
    newAttribute: 'Nuovo attributo',
    createTitle: 'Crea attributo',
    createSubtitle: 'Aggiungi un nuovo attributo dinamico riutilizzabile.',
    editTitle: 'Modifica attributo',
    editSubtitle: "Aggiorna l'attributo selezionato.",
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
    sections: {
      identity: {
        title: 'Dettagli',
        description: "Codice, nome e tipo dell'attributo.",
      },
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
  },
}

export const productCategories = {
  title: 'Categorie Prodotto',
  subtitle: 'Sfoglia, filtra e gestisci le categorie prodotto.',
  forbidden: 'Non hai i permessi per visualizzare le categorie prodotto.',
  columns: {
    name: 'Nome',
    parent: 'Padre',
    description: 'Descrizione',
    attributes_count: 'Attributi',
    products_count: 'Prodotti',
    business_function: 'Funzione aziendale',
    requires_quote: 'Prevede preventivo',
    is_selectable: 'Selezionabile',
    management_mode: 'Modalità di gestione',
    single_quote_per_opportunity: 'Offerta unica per opportunità',
    created_at: 'Creato il',
    tooltipEmpty: 'Nessun elemento da mostrare.',
    productsMore: '+{{count}} altri',
  },
  detail: {
    title: 'Dettagli categoria',
    subtitle: 'Vista di sola lettura della categoria selezionata.',
    loadError: 'Impossibile caricare la categoria. Riprova.',
    businessFunctionInherited: 'Ereditata da {{category}}',
    requiresQuoteInherited: 'Ereditato da {{category}}',
    managementModeInherited: 'Ereditata da {{category}}',
    singleQuotePerOpportunityInherited: 'Ereditata da {{category}}',
    managerLabelInherited: 'Ereditata',
  },
  bulkMove: {
    tableButton: 'Sposta sotto…',
    title: 'Sposta categorie',
    description: '{{count}} categoria/e selezionata/e. Mantengono le proprie sottocategorie.',
    destination: 'Destinazione',
    confirm: 'Sposta',
    success: '{{count}} categoria/e spostata/e.',
    genericError: 'Impossibile spostare le categorie. Riprova.',
    reasons: {
      self_parent: 'La destinazione è una delle categorie selezionate. Nessuna categoria è stata spostata.',
      nested_selection:
        'La selezione contiene categorie annidate una dentro l’altra. Deseleziona quelle sottostanti: seguono il padre. Nessuna categoria è stata spostata.',
      cycle:
        'La destinazione si trova dentro una delle categorie selezionate. Nessuna categoria è stata spostata.',
      business_function_conflict:
        'Alcune categorie sovrascriverebbero la funzione aziendale ereditata dalla destinazione. Nessuna categoria è stata spostata.',
    },
  },
  form: {
    newRootCategory: 'Nuova categoria',
    createTitle: 'Crea categoria',
    createSubtitle: 'Aggiungi una nuova categoria prodotto.',
    editTitle: 'Modifica categoria',
    editSubtitle: 'Aggiorna la categoria selezionata.',
    name: 'Nome',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome deve avere al massimo 191 caratteri.',
    parent: 'Categoria padre',
    parentPlaceholder: 'Seleziona una categoria padre…',
    parentSearch: 'Cerca categorie…',
    parentEmpty: 'Nessuna categoria trovata.',
    parentNoMatch: 'Nessun risultato.',
    parentError: 'Impossibile caricare le categorie.',
    noParent: 'Nessun padre (categoria radice)',
    description: 'Descrizione',
    inheritsAttributes: 'Eredita dal padre',
    inheritsAttributesHint:
      'Vale solo per questa sezione. Se disattivato, questa categoria ignora i suoi antenati: né lei né le sue sottocategorie ereditano questi attributi dai livelli superiori.',
    attributes: 'Attributi',
    attributesHelp:
      'Assegna gli attributi che i prodotti di questa categoria dovranno compilare (in aggiunta a quelli ereditati).',
    attributesEmpty: 'Nessun attributo assegnato ancora.',
    addAttributePlaceholder: 'Assegna un attributo…',
    attributeSearch: 'Cerca attributi…',
    attributeEmpty: 'Nessun attributo trovato.',
    attributeNoMatch: 'Nessun risultato.',
    attributeError: 'Impossibile caricare gli attributi.',
    isRequired: 'Obbligatorio',
    isRequiredHelp: 'Se attivo, il prodotto DEVE valorizzare questo attributo.',
    sortOrder: 'Ordine',
    sortOrderHelp: 'La posizione in cui questo campo compare nel form del prodotto.',
    removeAttribute: 'Rimuovi attributo',
    inheritedAttributes: 'Ereditati dalle categorie antenate',
    businessFunction: 'Funzione aziendale',
    businessFunctionPlaceholder: 'Seleziona una funzione aziendale…',
    businessFunctionSearch: 'Cerca funzioni aziendali…',
    businessFunctionEmpty: 'Nessuna funzione aziendale trovata.',
    businessFunctionError: 'Impossibile caricare le funzioni aziendali.',
    businessFunctionInheritedHint:
      'Ereditata da "{{category}}". Per modificarla, agisci su quella categoria.',
    requiresQuote: 'Prevede preventivo',
    requiresQuoteHint:
      'Se attivo, questa categoria e tutte le sue sottocategorie prevedono il preventivo.',
    requiresQuoteInheritedHint:
      'Ereditato dalla categoria radice "{{category}}". Per modificarlo, agisci su quella categoria.',
    isSelectable: 'Selezionabile',
    isSelectableHint:
      'Se disattivo, la categoria serve solo a raggruppare sottocategorie: sparisce dalle liste di scelta e non è più associabile a un prodotto, a una linea di prodotto, a un progetto, a una campagna o a una regola provvigionale. Le associazioni già esistenti restano.',
    managementMode: 'Modalità di gestione',
    managementModeHint:
      'Come si comportano le righe Categoria Prodotto su una scheda: questa categoria e tutte le sue sottocategorie seguono la stessa regola.',
    managementModeInheritedHint:
      'La modalità di gestione è ereditata dalla categoria radice "{{category}}". Per modificarla, agisci su quella categoria.',
    managementModeSingle: 'Singola (una riga per scheda)',
    managementModeMultiple: 'Multipla (più righe per scheda)',
    requiresQuoteInfo:
      "Stabilisce se le opportunità di questo ramo passano o meno da un'offerta. Se attivo, il modulo Offerte fa parte del flusso per ogni prodotto di questa categoria e delle sue sottocategorie; se disattivo, il ramo si lavora senza. La regola appartiene alla categoria RADICE e tutto il sottoalbero la segue.",
    requiresQuoteInfoLabel: 'Maggiori informazioni su Prevede preventivo',
    managementModeInfo:
      "Limita quante righe Categoria Prodotto può contenere una scheda. \"Singola\" blocca la scheda su UNA sola categoria prodotto: la sua offerta porta allora anche una sola riga prodotto, e un prodotto di un'altra categoria viene rifiutato invece di allargare in silenzio la copertura. \"Multipla\" è il comportamento senza vincoli. La regola appartiene alla categoria RADICE e tutto il sottoalbero la segue.",
    managementModeInfoLabel: 'Maggiori informazioni su Modalità di gestione',
    singleQuotePerOpportunity: "Offerta unica per opportunità",
    singleQuotePerOpportunityHint:
      "Se attivo, un'opportunità su questa categoria accetta una sola offerta.",
    singleQuotePerOpportunityInheritedHint:
      'La regola di offerta unica è ereditata dalla categoria radice "{{category}}". Per modificarla, agisci su quella categoria.',
    singleQuotePerOpportunityInfo:
      "Limita quanti DOCUMENTI OFFERTA può contenere un'opportunità: è una regola diversa dalla modalità di gestione, che limita invece le righe prodotto di una scheda. Se attivo, la creazione di una seconda offerta su un'opportunità di questo ramo viene rifiutata. Le opportunità che ne hanno già più di una le mantengono e restano modificabili. La regola appartiene alla categoria RADICE e tutto il sottoalbero la segue.",
    singleQuotePerOpportunityInfoLabel: 'Maggiori informazioni su Offerta unica per opportunità',
    isSelectableInfo:
      "Trasforma la categoria in un puro contenitore. Resta padre delle sue sottocategorie e conserva tutte le associazioni già fatte, ma non compare più nelle liste di scelta. A differenza delle altre regole questa appartiene SOLO a questa categoria: non viene mai ereditata, quindi un padre non selezionabile può avere figli selezionabili.",
    isSelectableInfoLabel: 'Maggiori informazioni su Selezionabile',
    inheritedFrom: 'Ereditata da {{category}}',
    managerLabelLevel: 'G.A. {{n}}',
    managerLabelPlaceholder: 'Gestore account {{n}}',
    managerLabelMax: "L'etichetta deve avere al massimo 60 caratteri.",
    managerLabelsHelp:
      'Aggiungi un livello per ogni rango di Gestore Account usato da questa categoria. Ripristinare un livello cancella solo la sua etichetta personalizzata: non rimuove mai un gestore account assegnato.',
    addManagerLabelLevel: 'Aggiungi livello',
    resetManagerLabelLevel: "Ripristina G.A. {{n}} all'etichetta predefinita",
    inheritsManagerLabels: 'Eredita dal padre',
    inheritsManagerLabelsHint:
      'Se disattivato, questa categoria ignora i suoi antenati: né lei né le sue sottocategorie ereditano queste etichette dai livelli superiori.',
    inheritedManagerLabels: 'Ereditate dalle categorie antenate',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Categoria creata con successo.',
    updated: 'Categoria aggiornata con successo.',
    deleted: 'Categoria eliminata con successo.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: 'Impossibile eliminare la categoria. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa categoria.',
    deleteInUse: 'Questa categoria ha sottocategorie o prodotti.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, padre e descrizione della categoria.',
      },
      rules: {
        title: 'Regole di gestione',
        description:
          "Cosa impone questa categoria a valle: preventivo, righe di scheda, offerte per opportunità e selezionabilità.",
      },
      attributes: {
        title: 'Attributi',
        description: 'Attributi assegnati a questa categoria, più quelli ereditati.',
      },
      productAttributes: {
        title: 'Attributi Prodotto',
        description: 'Caricati nella scheda Prodotto (creazione/modifica) per i prodotti di questa categoria.',
      },
      quoteAttributes: {
        title: 'Attributi Offerta',
        description:
          "Caricati nelle Informazioni aggiuntive dell'Offerta, quando una riga usa un prodotto di questa categoria.",
      },
      managerLabels: {
        title: 'Gestori Account',
        description:
          'Personalizza la denominazione di ogni livello di Gestore Account per questa categoria. I campi lasciati vuoti usano la denominazione predefinita.',
      },
    },
  },
}

export const products = {
  title: 'Prodotti',
  subtitle: 'Sfoglia, filtra e gestisci i tuoi prodotti.',
  forbidden: 'Non hai i permessi per visualizzare i prodotti.',
  /**
   * "Prodotti di interesse" (direttiva utente 2026-07-22): il picker condiviso
   * tra il form Opportunità e il pannello di Gestione Richieste.
   */
  ofInterest: {
    sectionTitle: 'Prodotti di interesse',
    sectionDescription: 'I prodotti che interessano al cliente per questa richiesta.',
    fieldLabel: 'Prodotti di interesse',
    placeholder: 'Seleziona uno o più prodotti',
    searchPlaceholder: 'Cerca prodotti…',
    empty: 'Nessun prodotto trovato.',
    remove: 'Rimuovi prodotto',
    // Spec 0075, D-4/D-5, estesa a entrambi i moduli dalla direttiva utente
    // 2026-08-05: un prodotto fuori dalle categorie prodotto del record viene
    // rifiutato, quindi il picker non esce mai da quell'ambito.
    hintScoped: 'Solo i prodotti delle categorie prodotto selezionate sopra.',
    prunedNotice:
      'Rimossi dalla selezione, la loro categoria prodotto non è più sul record: {{names}}.',
    hintNoCategories: 'Aggiungi prima una funzione aziendale con la sua categoria prodotto.',
    required: 'Seleziona almeno un prodotto di interesse.',
  },
  empty: 'Nessun prodotto selezionato.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    cost: 'Costo',
    price: 'Prezzo',
    category: 'Categoria',
    product_type: 'Tipo',
    business_function: 'Funzione aziendale',
    state: 'Regione',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettagli prodotto',
    subtitle: 'Vista di sola lettura del prodotto selezionato.',
    loadError: 'Impossibile caricare il prodotto. Riprova.',
    details: 'Dettagli',
    created_at: 'Creato il',
  },
  form: {
    newProduct: 'Nuovo prodotto',
    createTitle: 'Crea prodotto',
    createSubtitle: 'Aggiungi un nuovo prodotto.',
    editTitle: 'Modifica prodotto',
    editSubtitle: 'Aggiorna il prodotto selezionato.',
    code: 'Codice',
    codePlaceholder: 'Codice suggerito, modificabile',
    codeMax: 'Il codice deve avere al massimo 32 caratteri.',
    codeRequired: 'Il codice è obbligatorio.',
    name: 'Nome',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome deve avere al massimo 191 caratteri.',
    description: 'Descrizione',
    cost: 'Costo',
    costInvalid: 'Il costo deve essere zero o un numero positivo.',
    costRequired: 'Il costo è obbligatorio.',
    price: 'Prezzo',
    priceInvalid: 'Il prezzo deve essere zero o un numero positivo.',
    priceRequired: 'Il prezzo è obbligatorio.',
    productType: 'Tipo',
    category: 'Categoria',
    categoryPlaceholder: 'Seleziona una categoria…',
    categorySearch: 'Cerca categorie…',
    categoryEmpty: 'Nessuna categoria trovata.',
    categoryNoMatch: 'Nessun risultato.',
    categoryError: 'Impossibile caricare le categorie.',
    categoryRequired: 'La categoria è obbligatoria.',
    vatRate: 'IVA',
    vatRatePlaceholder: "Seleziona un'aliquota IVA…",
    vatRateSearch: 'Cerca aliquote IVA…',
    vatRateEmpty: 'Nessuna aliquota IVA trovata.',
    vatRateError: 'Impossibile caricare le aliquote IVA.',
    supplier: 'Fornitore',
    supplierPlaceholder: 'Seleziona un fornitore…',
    supplierSearch: 'Cerca fornitori…',
    supplierEmpty: 'Nessun fornitore trovato.',
    supplierError: 'Impossibile caricare i fornitori.',
    state: 'Regione',
    statePlaceholder: 'Seleziona una regione…',
    stateSearch: 'Cerca regioni…',
    stateEmpty: 'Nessuna regione trovata.',
    stateError: 'Impossibile caricare le regioni.',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Prodotto creato con successo.',
    updated: 'Prodotto aggiornato con successo.',
    deleted: 'Prodotto eliminato con successo.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: 'Impossibile eliminare il prodotto. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo prodotto.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Codice, nome, descrizione, prezzi e categoria del prodotto.',
      },
    },
    hints: {
      code: 'Precompilato con il prossimo codice sequenziale; modificalo se ne vuoi uno personalizzato.',
    },
    dynamicFields: {
      title: 'Attributi',
      empty: 'Seleziona una categoria per vedere i suoi attributi.',
    },
  },
}
