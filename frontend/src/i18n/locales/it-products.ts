/**
 * Domini Categorie Prodotto + Prodotti (spec 0017). Estratto in un
 * file affiancato per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); la forma pubblica di `it.ts` è invariata.
 * Gli Attributi stanno in `it-attributes.ts`, i Prodotti in `it-product-items.ts`.
 */

export { attributes } from './it-attributes'
export { products } from './it-product-items'

export const productCategories = {
  title: 'Categorie Prodotto',
  subtitle: 'Sfoglia, filtra e gestisci le categorie prodotto.',
  forbidden: 'Non hai i permessi per visualizzare le categorie prodotto.',
  // Pill dell'identita', condivise tra la barra del form e la scheda.
  badges: {
    root: 'Radice',
    notSelectable: 'Non selezionabile',
    notActive: 'Non attiva',
    reportable: 'Visibile nei report',
  },
  columns: {
    name: 'Nome',
    parent: 'Padre',
    description: 'Descrizione',
    attributes_count: 'Attributi',
    products_count: 'Prodotti',
    business_function: 'Funzione aziendale',
    requires_quote: 'Prevede preventivo',
    is_selectable: 'Selezionabile',
    is_active: 'Attiva',
    is_reportable: 'Visibile nei report',
    management_mode: 'Modalità di gestione',
    single_quote_per_opportunity: 'Offerta unica per opportunità',
    generates_contract: 'Prevede un contratto',
    multi_quote_work_order: "Commessa multi-offerta",
    usage_area: 'Visibile in',
    multiple_open_opportunities: 'Più opportunità aperte per anagrafica',
    request_registry_type: 'Anagrafica in Gestione Richieste',
    simplified_offer_line: 'Semplificazione riga offerta',
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
    isReportableInherited: 'Ereditato da {{category}}',
    reportColumnsInherited: 'Ereditato da {{category}}',
    managementModeInherited: 'Ereditata da {{category}}',
    singleQuotePerOpportunityInherited: 'Ereditata da {{category}}',
    multipleOpenOpportunitiesInherited: 'Ereditata da {{category}}',
    generatesContractInherited: 'Ereditata da {{category}}',
    multiQuoteWorkOrderInherited: "Ereditata da {{category}}",
    usageAreaInherited: 'Ereditato da {{category}}',
    requestRegistryTypeInherited: 'Ereditato da {{category}}',
    simplifiedOfferLineInherited: 'Ereditata da {{category}}',
    requestOfferHiddenInherited: 'Ereditata da {{category}}',
    managerLabelInherited: 'Ereditata',
    inheritedCount: '+{{count}} ereditati',
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
    currentCategory: 'Categoria attuale',
    currentCategoryPlaceholder: 'Seleziona la categoria attuale…',
    currentCategorySearch: 'Cerca categorie madri attuali…',
    currentCategoryEmpty: 'Nessuna categoria madre attuale trovata.',
    currentCategoryError: 'Impossibile caricare le categorie madri attuali.',
    currentCategoryHint: 'La categoria madre attuale che ha preso il posto di questa. Le collega nei filtri e nelle colonne dei campi flessibili.',
    businessFunctionInheritedHint:
      'Ereditata da "{{category}}". Per modificarla, agisci su quella categoria.',
    businessFunctionResetTitle: 'Le sottocategorie perderanno la loro funzione aziendale',
    businessFunctionResetDescription_one:
      'Assegnando una funzione aziendale a questa categoria, la sottocategoria seguente perde la propria e eredita quella scelta qui:',
    businessFunctionResetDescription_other:
      'Assegnando una funzione aziendale a questa categoria, le {{count}} sottocategorie seguenti perdono la propria e ereditano quella scelta qui:',
    businessFunctionResetConfirm: 'Assegna e azzera',
    requiresQuote: 'Prevede preventivo',
    requiresQuoteHint:
      'Se attivo, questa categoria e tutte le sue sottocategorie prevedono il preventivo.',
    requiresQuoteInheritedHint:
      'Ereditato dalla categoria radice "{{category}}". Per modificarlo, agisci su quella categoria.',
    isSelectable: 'Selezionabile',
    requiresContactTransfer: 'Trasferimento contatto prima del contratto',
    requiresContactTransferHint:
      "Se attivo, un'offerta di questa categoria non può passare a Vinto (e generare il contratto) finché il contatto non è stato trasferito con Trasferisci contatto. Vinto provvisorio resta sempre possibile.",
    requiresContactTransferInfo:
      "Vale solo per questa categoria, non per le sue sottocategorie. Il controllo scatta quando l'offerta passerebbe a Vinto generando un contratto, da qualsiasi modulo (Offerte, Gestione Richieste, Gestione Iscritti, griglia). Le offerte già vinte e le categorie senza contratto non sono toccate.",
    requiresContactTransferInfoLabel: 'Maggiori informazioni su Trasferimento contatto prima del contratto',
    isSelectableHint:
      'Se disattivo, la categoria serve solo a raggruppare sottocategorie: sparisce dalle liste di scelta e non è più associabile a un prodotto, a una linea di prodotto, a un progetto, a una campagna o a una regola provvigionale. Le associazioni già esistenti restano.',
    isActive: 'Attiva',
    isActiveHint:
      'Se disattiva, la categoria sparisce con tutto il suo ramo e i suoi prodotti dai campi di inserimento. I collegamenti esistenti restano.',
    isActiveInheritedNotice: 'Non attiva perché lo è «{{category}}»',
    isReportable: 'Visibile nei report',
    isReportableHint:
      'La categoria compare nei report e nella dashboard di Gestione Richieste e Gestione Iscritti; selezionandola si vedono anche le sue sottocategorie.',
    isReportableInheritedHint:
      'Valore ereditato da "{{category}}". Cambialo per forzarlo solo su questa categoria e sulle sue sottocategorie.',
    isReportableForcedHint:
      'Valore forzato su questa categoria: non segue più "{{category}}". Riportalo al valore del padre per tornare a ereditarlo.',
    isReportableForcedBadge: 'Forzato',
    isReportableOriginHint:
      'Impostato su questa categoria: tutte le sue sottocategorie lo ereditano, salvo quelle forzate diversamente.',
    isReportableNoSourceHint:
      'Nessuna categoria superiore è visibile nei report. Attivalo qui per includere questa categoria e tutte le sue sottocategorie.',
    managementMode: 'Modalità di gestione',
    managementModeHint:
      'Come si comportano le righe Categoria Prodotto su una scheda: questa categoria e tutte le sue sottocategorie seguono la stessa regola.',
    managementModeInheritedHint:
      'La modalità di gestione è ereditata dalla categoria radice "{{category}}". Per modificarla, agisci su quella categoria.',
    managementModeSingle: 'Singola (una riga per scheda)',
    managementModeMultiple: 'Multipla (più righe per scheda)',
    // Versione corta per il riepilogo laterale, dove la riga e' troncata.
    managementModeSingleShort: 'Singola',
    managementModeMultipleShort: 'Multipla',
    requiresQuoteInfo:
      "Stabilisce se le opportunità di questo ramo passano o meno da un'offerta. Se attivo, il modulo Offerte fa parte del flusso per ogni prodotto di questa categoria e delle sue sottocategorie; se disattivo, il ramo si lavora senza. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue, a meno che una sottocategoria non forzi un proprio valore (che vale poi per il suo sottoalbero).",
    requiresQuoteInfoLabel: 'Maggiori informazioni su Prevede preventivo',
    managementModeInfo:
      "Limita quante righe Categoria Prodotto può contenere una scheda. \"Singola\" blocca la scheda su UNA sola categoria prodotto: la sua offerta porta allora anche una sola riga prodotto, e un prodotto di un'altra categoria viene rifiutato invece di allargare in silenzio la copertura. \"Multipla\" è il comportamento senza vincoli. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue, a meno che una sottocategoria non forzi un proprio valore (che vale poi per il suo sottoalbero).",
    managementModeInfoLabel: 'Maggiori informazioni su Modalità di gestione',
    singleQuotePerOpportunity: "Offerta unica per opportunità",
    singleQuotePerOpportunityHint:
      "Se attivo, un'opportunità su questa categoria accetta una sola offerta.",
    singleQuotePerOpportunityInheritedHint:
      'La regola di offerta unica è ereditata dalla categoria radice "{{category}}". Per modificarla, agisci su quella categoria.',
    singleQuotePerOpportunityInfo:
      "Limita quanti DOCUMENTI OFFERTA può contenere un'opportunità: è una regola diversa dalla modalità di gestione, che limita invece le righe prodotto di una scheda. Se attivo, la creazione di una seconda offerta su un'opportunità di questo ramo viene rifiutata. Le opportunità che ne hanno già più di una le mantengono e restano modificabili. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue, a meno che una sottocategoria non forzi un proprio valore (che vale poi per il suo sottoalbero).",
    singleQuotePerOpportunityInfoLabel: 'Maggiori informazioni su Offerta unica per opportunità',
    requestRegistryType: 'Anagrafica in Gestione Richieste',
    requestRegistryTypeHint:
      'Con quale tipo parte la scheda del nuovo cliente quando in Gestione Richieste si crea una richiesta di questa categoria.',
    requestRegistryTypeInheritedHint: 'Ereditato dalla categoria radice "{{category}}". Per modificarlo, agisci su quella categoria.',
    multipleOpenOpportunities: 'Più opportunità aperte per anagrafica',
    multipleOpenOpportunitiesHint:
      "Se attivo, un'anagrafica può avere più opportunità aperte su questa categoria.",
    multipleOpenOpportunitiesInheritedHint:
      'La regola è ereditata dalla categoria radice "{{category}}". Per modificarla, agisci su quella categoria.',
    multipleOpenOpportunitiesInfo:
      "Di norma un'anagrafica può avere una sola opportunità aperta: la seconda viene rifiutata e si rimanda a quella esistente. Se attivo, le opportunità di questo ramo non bloccano e non vengono bloccate, senza cambiare la modalità di gestione: con la modalità Multipla le offerte restano su più righe. Vale solo se tutte le categorie attuali dell'opportunità hanno la regola attiva. Le categorie a gestione Singola non bloccano mai. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue.",
    multipleOpenOpportunitiesInfoLabel: 'Maggiori informazioni su Più opportunità aperte per anagrafica',
    multipleOpenOpportunitiesOffWarning: "Spenta: in Gestione Richieste una nuova richiesta per un cliente con un'opportunità aperta viene rifiutata.",
    generatesContract: 'Prevede un contratto',
    generatesContractHint:
      "Se attivo, un'offerta di questa categoria che si chiude con esito positivo apre un contratto.",
    generatesContractInheritedHint:
      'La regola sul contratto è ereditata dalla categoria radice "{{category}}". Per modificarla, agisci su quella categoria.',
    generatesContractInfo:
      "Decide se il ramo viene venduto sotto contratto. Se spento, quando l'offerta entra in uno stato di lavorazione chiuso positivo NON viene creato alcun contratto e la scheda non compare nel modulo Contratti; la chiusura positiva resta comunque possibile. I contratti già aperti prima dello spegnimento restano dove sono e continuano a vivere normalmente. Se una scheda copre più categorie, basta che UNA non preveda il contratto perché non venga creato. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue, a meno che una sottocategoria non forzi un proprio valore (che vale poi per il suo sottoalbero).",
    generatesContractLockedByMulti: 'Spegni prima Commessa multi-offerta.',
    generatesContractInfoLabel: 'Maggiori informazioni su Prevede un contratto',
    multiQuoteWorkOrder: "Commessa multi-offerta",
    usageArea: 'Visibile in',
    usageAreaHint:
      'In quali moduli si sceglie questa categoria e tutte le sue sottocategorie: negli altri non compare nelle scelte né nelle tab. I valori già salvati restano.',
    usageAreaInheritedHint: 'Ereditato dalla categoria radice "{{category}}". Per modificarlo, agisci su quella categoria.',
    multiQuoteWorkOrderHint:
      "Consente di programmare dalla tabella Contratti un'unica commessa con righe di piu' contratti vinti di questo ramo.",
    multiQuoteWorkOrderInheritedHint:
      "La regola sulla commessa multi-offerta è ereditata dalla categoria radice \"{{category}}\". Per modificarla, agisci su quella categoria.",
    multiQuoteWorkOrderInfo:
      "Decide se, dalla tabella Contratti, si può programmare un'unica commessa che raccoglie righe di ricavo di più contratti vinti (anche di clienti diversi) appartenenti a questo ramo. Se spento, ogni commessa resta legata a una sola offerta. Le commesse già create non cambiano. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue.",
    multiQuoteWorkOrderNeedsContract: 'Richiede Prevede un contratto.',
    multiQuoteWorkOrderInfoLabel: "Maggiori informazioni su Commessa multi-offerta",
    simplifiedOfferLine: 'Semplificazione riga offerta',
    simplifiedOfferLineHint:
      "Se attivo, in Gestione Richieste l'offerta diventa una semplice scelta di prodotti: quantità, prezzo unitario e aliquota IVA li imposta il sistema e i prezzi sono mostrati solo a titolo informativo.",
    simplifiedOfferLineInheritedHint:
      'Valore ereditato da "{{category}}". Cambialo per forzare la regola solo su questa categoria e sulle sue sottocategorie.',
    simplifiedOfferLineForcedHint:
      'Valore forzato su questa categoria: non segue più "{{category}}". Riportalo al valore ereditato per tornare a ereditarlo.',
    simplifiedOfferLineForcedBadge: 'Forzata',
    simplifiedOfferLineInfo:
      "Elimina la compilazione manuale della riga offerta in Gestione Richieste: sulle richieste di questo ramo l'operatore sceglie SOLO il prodotto e il sistema valorizza quantità (sempre 1), prezzo unitario e aliquota IVA con quelli del prodotto. Il modulo Offerte non è toccato: chi lavora l'offerta commerciale continua a governare quantità e prezzi anche su una categoria semplificata. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue, a meno che una sottocategoria non forzi un proprio valore (che vale poi per il suo sottoalbero).",
    simplifiedOfferLineInfoLabel: 'Maggiori informazioni su Semplificazione riga offerta',
    simplifiedOfferLineNeedsRequests: 'Non ha effetto: il ramo non si usa in Gestione Richieste (Visibile in).',
    requestOfferHidden: 'Nascondi offerta in Gestione Richieste',
    requestOfferHiddenHint: "Se attivo, nelle richieste di questo ramo la sezione Offerta non compare: l'operatore non sceglie prodotti né prezzi.",
    requestOfferHiddenInheritedHint: 'Ereditata dalla categoria radice "{{category}}". Per modificarla, agisci su quella categoria.',
    requestOfferHiddenInfo:
      "Nasconde la sezione Offerta in Gestione Richieste (Nuova richiesta, pannello Lavora e modifica dalla tabella) quando tutte le linee di prodotto della richiesta appartengono a rami con questa regola; con rami misti il catalogo mostra solo i prodotti dei rami visibili. L'offerta viene comunque creata e le righe già presenti restano invariate; il modulo Offerte e i Contratti non sono toccati. La regola è decisa dalla categoria RADICE e tutto il sottoalbero la segue.",
    requestOfferHiddenInfoLabel: 'Maggiori informazioni su Nascondi offerta in Gestione Richieste',
    isSelectableInfo:
      "Trasforma la categoria in un puro contenitore. Resta padre delle sue sottocategorie e conserva tutte le associazioni già fatte, ma non compare più nelle liste di scelta. A differenza delle altre regole questa appartiene SOLO a questa categoria: non viene mai ereditata, quindi un padre non selezionabile può avere figli selezionabili.",
    isSelectableInfoLabel: 'Maggiori informazioni su Selezionabile',
    isActiveInfo:
      "Quando la categoria non è attiva sparisce, insieme a tutte le sue sottocategorie e ai prodotti di quel ramo, dai campi di inserimento e di modifica: non si può più collegare a nulla di nuovo. I collegamenti già esistenti restano validi e vengono ancora mostrati, e i filtri delle griglie continuano a vedere la categoria. A differenza di Selezionabile (che lascia la categoria visibile come contenitore) il flag Attiva si propaga a tutto il ramo, senza modificare il flag delle sottocategorie.",
    isActiveInfoLabel: 'Maggiori informazioni su Attiva',
    isReportableInfo:
      'Decide se la categoria compare come riga selezionabile nei report e nella dashboard di Gestione Richieste e di Gestione Iscritti. Una sottocategoria eredita il valore del padre: se il padre è visibile nei report lo sono anche i figli, ma ogni figlio può essere forzato a "no" (e con lui le sue sottocategorie). Una categoria esclusa non viene conteggiata neanche nei totali del padre.',
    isReportableInfoLabel: 'Maggiori informazioni su Visibile nei report',
    reportColumns: 'Colonne report',
    reportColumnsInfo:
      'Sceglie quali colonne statistiche di Gestione Richieste vengono calcolate per questa categoria: la stessa selezione vale per il report (CSV/Excel) e per la dashboard. Una sottocategoria eredita le colonne della categoria antenata più vicina che le ha configurate.',
    reportColumnsInfoLabel: 'Maggiori informazioni su Colonne report',
    // Badge contatore nell'header della card ("7/11").
    reportColumnsCount: '{{selected}}/{{total}}',
    reportColumnsStatusOwn: 'Proprie',
    reportColumnsStatusNone: 'Nessuna colonna',
    reportColumnsSelectAll: 'Tutte',
    reportColumnsSelectNone: 'Nessuna',
    reportColumnsError: 'Impossibile caricare il catalogo delle colonne. Riprova.',
    // Nessun antenato configurato: questa categoria è l'origine della configurazione (spec 0141 rev-1).
    reportColumnsOwnHint: 'Impostate su questa categoria: ogni sottocategoria le eredita.',
    // Un antenato ha colonne proprie: qui vengono sovrascritte (l'azione "Torna alle ereditate" è disponibile).
    reportColumnsOverridesHint: 'Sovrascrivono quelle ereditate da "{{category}}".',
    reportColumnsInheritedHint: 'Ereditate da "{{category}}".',
    reportColumnsNoSourceHint:
      'Nessuna categoria superiore ha colonne configurate. Selezionane alcune per includere questa categoria nel report e nella dashboard.',
    reportColumnsUnknownHint: 'Le colonne ereditate si vedranno dopo il salvataggio.',
    reportColumnsResetToInherited: 'Torna alle ereditate',
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
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: 'La categoria non è ancora stata creata: i dati inseriti andranno persi.',
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    created: 'Categoria creata con successo.',
    updated: 'Categoria aggiornata con successo.',
    deleted: 'Categoria eliminata con successo.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: 'Impossibile eliminare la categoria. Riprova.',
    sections: {
      identity: {
        title: 'Dettagli',
      },
      rules: {
        title: 'Regole di gestione',
      },
      attributes: {
        title: 'Attributi',
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
      workOrderAttributes: {
        title: 'Attributi Commessa',
        description:
          'Caricati nelle Informazioni aggiuntive della Commessa, quando una sua riga usa un prodotto di questa categoria.',
      },
      managerLabels: {
        title: 'Gestori Account',
      },
    },
  },
}
