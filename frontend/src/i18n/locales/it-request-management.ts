/**
 * Dominio Gestione Richieste (spec 0049): vista operativa "Gestione
 * Richieste" sulle Opportunità per i commerciali (D-1, nessuna entità
 * nuova — il record E' un'Opportunità). File satellite per mantenere
 * `it.ts` entro i limiti dimensionali (vedi `.claude/rules/engineering.md`
 * §6).
 */

export const requestManagement = {
  title: 'Gestione Richieste',
  subtitle: "Lavora le opportunità: verifica i contatti e completa i campi dinamici.",
  forbidden: 'Non hai il permesso di visualizzare Gestione Richieste.',
  categoryTabs: {
    all: 'Tutte',
  },
  columns: {
    source: 'Fonte',
    pendingChangeRequests: 'Richieste di modifica',
    productCategory: 'Categoria prodotto',
    offerLines: 'Linee di prodotto',
    generalNotes: 'Note generali',
    operator: 'Operatore (GA2)',
    operationalSite: 'Sede operativa',
    firstName: 'Nome',
    lastName: 'Cognome',
    taxCode: 'Codice fiscale',
    phone: 'Telefono',
    createdAt: 'Caricato il',
    nextCallbackAt: 'Prossimo richiamo',
    transferred: 'Trasferito',
  },
  pendingChangeRequests: {
    alert_one: '{{count}} richiesta di modifica in attesa',
    alert_other: '{{count}} richieste di modifica in attesa',
  },
  advancedFilters: {
    registry: 'Anagrafica',
    referent: 'Referente',
    opportunityStatus: 'Stato commerciale',
    operationalSite: 'Sede operativa',
    expectedCloseRange: 'Data chiusura prevista',
    nextCallbackRange: 'Prossimo richiamo',
  },
  detail: {
    title: 'Dettagli richiesta',
    subtitle: "Lavora l'opportunità selezionata: contatti e campi dinamici.",
  },
  delete: {
    success: 'Richiesta eliminata.',
    forbidden: 'Non hai il permesso di eliminare questa richiesta.',
    error: 'Impossibile eliminare la richiesta. Riprova.',
  },
  assign: {
    tableButton: 'Assegna operatori',
    description: '{{count}} richieste selezionate.',
    actions: {
      balancedHint: 'Distribuisce le richieste selezionate tra gli operatori della Sede, bilanciando il carico di lavoro.',
      singleHint: 'Assegna tutte le richieste selezionate allo stesso operatore.',
    },
    success: 'Operatori assegnati a {{count}} richieste.',
    errors: {
      noOperators: 'Nessun operatore trovato per la Sede selezionata.',
      generic: 'Impossibile assegnare gli operatori. Riprova.',
    },
  },
  transfer: {
    title: 'Trasferisci contatto',
    description: '{{count}} richieste selezionate.',
    notice: 'Contatto trasferito dalla sede di {{site}}',
    success: '{{count}} richieste trasferite.',
    errors: {
      generic: 'Impossibile trasferire il contatto. Riprova.',
    },
  },
  form: {
    notApplicable: 'Gestione Richieste non ha un form di modifica: lavora il record dal suo pannello di dettaglio.',
    newRequest: 'Nuova richiesta',
    createTitle: 'Nuova richiesta',
    createSubtitle: "Anagrafica cliente e linee di prodotto della nuova richiesta.",
    create: {
      client: {
        title: 'Anagrafica cliente',
        description: 'Seleziona un\'anagrafica esistente oppure compila i dati di un nuovo cliente.',
        registryLabel: 'Anagrafica esistente',
        registryPlaceholder: 'Nessuna anagrafica selezionata',
        registrySearch: 'Cerca un\'anagrafica',
        registryEmpty: 'Nessun risultato',
        registryError: 'Impossibile caricare le opzioni.',
        registryHint: "Se selezioni un'anagrafica esistente, i campi identità/contatti/indirizzo qui sotto si nascondono e la richiesta viene agganciata a quella.",
        identityGroup: 'Dati identificativi',
        contactsGroup: 'Contatti',
        addressGroup: 'Indirizzo',
      },
      generalNotes: {
        label: 'Note generali',
        placeholder: 'Cosa ha chiesto il cliente, con le sue parole…',
      },
      summary: {
        description: 'Cosa stai per creare.',
        existingRegistry: 'Anagrafica esistente',
      },
      attribution: {
        title: 'Attribuzione',
        description: 'Da dove arriva la richiesta e chi la segnala.',
        source: 'Fonte',
        sourceSearch: 'Cerca una fonte',
        reporter: 'Segnalatore',
        reporterSearch: 'Cerca un segnalatore',
        operator: 'Operatore (GA2)',
        operatorSearch: 'Cerca un operatore',
        operatorFilteredBySite: 'Solo gli operatori della sede selezionata.',
        operationalSite: 'Sede operativa',
        operationalSiteSearch: 'Cerca una sede',
        selectPlaceholder: 'Seleziona',
        selectEmpty: 'Nessun risultato',
        selectError: 'Impossibile caricare le opzioni.',
        rewards: {
          fieldLabel: 'Buoni assegnati',
          add: 'Aggiungi buono',
          remove: 'Rimuovi {{name}}',
          searchPlaceholder: 'Cerca una tipologia…',
          empty: 'Nessuna tipologia trovata.',
          error: 'Impossibile caricare le tipologie.',
          loadMore: 'Carica altri',
          reporterRequiredHint: 'Seleziona prima un segnalatore per assegnare un buono.',
        },
      },
      cancel: 'Annulla',
      save: 'Crea richiesta',
      saving: 'Creazione…',
      success: 'Richiesta creata.',
      validation: {
        productLinesRequired: 'Aggiungi almeno una linea di prodotto.',
        productLineIncomplete: 'Seleziona funzione aziendale e categoria prodotto per ogni riga.',
        // Spec 0077 INV-2: tutte le righe condividono la stessa Funzione
        // aziendale (creazione: nessun record storico da salvaguardare).
        businessFunctionMismatch: 'Tutte le righe devono condividere la stessa funzione aziendale.',
        sourceRequired: 'Seleziona una fonte.',
      },
      errors: {
        generic: 'Si è verificato un errore. Riprova.',
        identityIncomplete: "Completa i dati identificativi del cliente prima di salvare.",
        addressIncomplete: "Completa l'indirizzo (via e città) oppure svuotalo del tutto.",
        contactsInvalid: 'Uno dei contatti inseriti non è valido.',
      },
    },
  },
  /**
   * "Linee dell'offerta" (direttiva utente 2026-08-07): la sezione riusa le
   * chiavi `quotes.form.offerTab.*` (e' lo STESSO componente delle Offerte);
   * qui vive solo il caso che le Offerte non hanno — nessuna categoria scelta
   * ancora, perche' in questo modulo si sceglie nella stessa schermata.
   */
  offerLines: {
    hintNoCategory: 'Scegli prima una categoria prodotto: limita i prodotti selezionabili.',
  },
  workPanel: {
    loadError: 'Impossibile caricare il record.',
    saving: 'Salvataggio…',
    save: 'Salva',
    saved: 'Dati di lavorazione salvati.',
    genericError: 'Si è verificato un errore. Riprova.',
    generalNotes: {
      title: 'Note generali',
    },
    callback: {
      title: 'Prossimo richiamo',
      description: 'Pianifica la prossima chiamata di follow-up con il cliente.',
      label: 'Data del richiamo',
      timeLabel: 'Ora del richiamo (facoltativa)',
    },
    attribution: {
      title: 'Attribuzione',
      description: 'Da dove arriva la richiesta e chi la sta lavorando.',
      source: 'Fonte',
      sourceSearch: 'Cerca una fonte',
      reporter: 'Segnalatore',
      reporterSearch: 'Cerca un segnalatore',
      operator: 'Operatore (GA2)',
      operatorSearch: 'Cerca un operatore',
      operatorFilteredBySite: 'Solo gli operatori della sede selezionata.',
      operationalSite: 'Sede operativa',
      operationalSiteSearch: 'Cerca una sede',
      selectPlaceholder: 'Seleziona',
      selectEmpty: 'Nessun risultato',
      selectError: 'Impossibile caricare le opzioni.',
      rewards: {
        fieldLabel: 'Buoni assegnati',
        add: 'Aggiungi buono',
        remove: 'Rimuovi {{name}}',
        searchPlaceholder: 'Cerca una tipologia…',
        empty: 'Nessuna tipologia trovata.',
        error: 'Impossibile caricare le tipologie.',
        loadMore: 'Carica altri',
        reporterRequiredHint: 'Seleziona prima un segnalatore per assegnare un buono.',
      },
    },
    client: {
      title: 'Anagrafica',
      description: 'Dati identificativi, contatti e indirizzo del cliente.',
      identityGroup: 'Dati identificativi',
      contactsGroup: 'Contatti',
      addressGroup: 'Indirizzo',
    },
    header: {
      title: 'Informazioni preliminari',
      unsavedChanges: 'Modifiche non salvate',
      salesStatus: 'Commerciale',
      nextCallback: 'Prossimo richiamo',
    },
    summary: {
      title: 'Riepilogo richiesta',
      description: 'Contesto commerciale in sola lettura.',
      registry: 'Cliente',
      referent: 'Referente',
      commercial: 'Commerciale',
      expectedCloseDate: 'Chiusura prevista',
      estimatedValue: 'Valore stimato',
      successProbability: 'Probabilità di successo',
    },
    productLines: {
      title: 'Linee di prodotto',
      description: 'Funzione aziendale e categoria prodotto della richiesta.',
      fieldLabel: 'Linee di prodotto',
      hint: 'Le categorie scelte qui filtrano i prodotti di interesse e determinano i campi specifici della richiesta al salvataggio.',
    },
    collaboration: {
      notesTab: 'Note',
      documentsTab: 'Documenti',
      activityTab: 'Storico',
    },
    validation: {
      sourceRequired: 'Seleziona una fonte.',
      productLinesRequired: 'Aggiungi almeno una linea di prodotto.',
      productLineIncomplete: 'Seleziona funzione aziendale e categoria prodotto per ogni riga.',
      // Spec 0077 INV-2, D-5: applicato SOLO quando la collezione
      // `product_lines` è stata effettivamente modificata (grandfathering di
      // un record storico non conforme, vedi `request-work-schema.ts`).
      businessFunctionMismatch: 'Tutte le righe devono condividere la stessa funzione aziendale.',
      summary: 'Impossibile salvare: controlla questi campi — {{fields}}.',
    },
  },
}
