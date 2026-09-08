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
    operator: 'Operatore',
    managerGa3: 'Tutor',
    operationalSite: 'Sede operativa',
    firstName: 'Nome',
    lastName: 'Cognome',
    taxCode: 'Codice fiscale',
    phone: 'Telefono',
    createdAt: 'Caricato il',
    nextCallbackAt: 'Prossimo richiamo',
    transferred: 'Trasferito',
    quoteWorkflowStatus: 'Stato di lavorazione',
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
  assignManagerGa3: {
    tableButton: 'Assegna {{label}}',
    title: 'Assegna {{label}}',
    description: '{{count}} richieste selezionate.',
    placeholder: 'Nessun utente selezionato',
    searchPlaceholder: 'Cerca un utente',
    empty: 'Nessun risultato',
    selectError: 'Impossibile caricare gli utenti.',
    selectClear: 'Rimuovi selezione',
    retry: 'Riprova',
    hint: 'Tutte le richieste selezionate riceveranno questo {{label}}.',
    clearHint: 'Nessun utente selezionato: confermando, lo slot verra\' svuotato su tutte le richieste selezionate.',
    confirm: 'Assegna',
    assigning: 'Assegnazione in corso...',
    success: 'Assegnazione aggiornata su {{count}} richieste.',
    errors: {
      generic: 'Impossibile completare l\'assegnazione. Riprova.',
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
      team: {
        title: 'Team',
        description: "Supervisore e gestori account dell'offerta.",
        supervisor: 'Supervisore',
        supervisorSearch: 'Cerca supervisori…',
        managers: 'Gestori account',
        operatorFilteredBySite: 'Solo gli operatori della sede selezionata.',
        selectPlaceholder: 'Seleziona',
        selectEmpty: 'Nessun risultato',
        selectError: 'Impossibile caricare le opzioni.',
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
        identityIncomplete: 'Completa i dati identificativi del cliente: {{fields}}',
        addressIncomplete: "Completa l'indirizzo (oppure svuotalo del tutto): {{fields}}",
        contactsInvalid: 'Correggi i contatti: {{fields}}',
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
    editAction: 'Modifica le righe dell\'offerta',
    dialogTitle: 'Linee dell\'offerta',
    dialogDescription: 'Modifica prodotto, quantità, prezzo unitario e IVA delle righe di questa offerta.',
    validationSummary: 'Controlla le righe evidenziate prima di salvare.',
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
      description: 'Da dove arriva la richiesta e chi la segnala.',
      source: 'Fonte',
      sourceSearch: 'Cerca una fonte',
      reporter: 'Segnalatore',
      reporterSearch: 'Cerca un segnalatore',
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
    team: {
      title: 'Team',
      description: "Supervisore e gestori account dell'offerta.",
      supervisor: 'Supervisore',
      supervisorSearch: 'Cerca supervisori…',
      managers: 'Gestori account',
      operatorFilteredBySite: 'Solo gli operatori della sede selezionata.',
      selectPlaceholder: 'Seleziona',
      selectEmpty: 'Nessun risultato',
      selectError: 'Impossibile caricare le opzioni.',
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
  // Report CSV asincrono (spec 0106): dialog di selezione date, agganciato
  // al menu opzioni della toolbar via `importSlot`.
  report: {
    title: 'Report CSV',
    description: 'Genera un report aggregato per categoria e operatore in un intervallo di date; il file si scarica automaticamente al termine.',
    action: 'Genera report',
    fields: {
      dateFrom: 'Dal',
      dateTo: 'Al',
      // rev-2: selezione dei rami e delle righe da includere.
      categories: 'Categorie',
      // rev-2 AC-055: controllo tri-stato in testa al gruppo categorie.
      selectAllCategories: 'Seleziona tutto',
      rowMode: 'Righe da includere',
    },
    // rev-2 D-13: le tre opzioni mutuamente esclusive di `row_mode`.
    rowModes: {
      total_only: 'Solo totale',
      operators_only: 'Solo operatori',
      all: 'Tutto',
    },
    status: {
      processing: 'Generazione in corso…',
      completed: 'Report generato: il download è partito automaticamente.',
      loadingCategories: 'Caricamento categorie…',
    },
    buttons: {
      confirm: 'Genera',
      processing: 'Generazione…',
    },
    errors: {
      dateFromRequired: 'Seleziona la data di inizio.',
      dateToRequired: 'Seleziona la data di fine.',
      dateToBeforeDateFrom: 'La data di fine non può essere precedente alla data di inizio.',
      // rev-2 AC-051/AC-053: errori del gruppo categorie.
      categoriesRequired: 'Seleziona almeno una categoria.',
      categoriesEmpty: 'Nessuna categoria disponibile: non ci sono richieste visibili da includere nel report.',
      categoriesLoadFailed: 'Impossibile caricare le categorie. Riprova.',
      forbidden: 'Non hai il permesso di generare questo report.',
      validation: 'Le date inserite non sono valide.',
      generic: 'Impossibile generare il report. Riprova.',
      jobFailed: 'La generazione del report non è riuscita. Riprova.',
    },
  },
  // Dashboard grafici (spec 0107): pannello aperto dallo StatsToggleButton
  // esistente, i nomi di categoria/operatore restano valori di dominio.
  dashboard: {
    regionLabel: 'Dashboard di Gestione Richieste',
    loadError: 'Impossibile caricare la dashboard.',
    empty: 'Nessun grafico da mostrare per questa selezione.',
  },
}
