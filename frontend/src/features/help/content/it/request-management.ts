import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'request-management',
  title: 'Gestione Richieste',
  summary:
    'Gestione Richieste è il banco di lavoro quotidiano sulle richieste dei clienti: cosa hanno chiesto, a quali prodotti sono interessati e chi le segue.',
  sections: [
    {
      id: 'overview',
      title: "Cos'è Gestione Richieste",
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Opportunità e Commesse › Gestione Richieste**. Ogni riga dell\'elenco corrisponde a un\'offerta collegata a un\'opportunità.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi una scheda per ogni categoria prodotto, più **Tutte**: scegline una per vedere solo le richieste di quella categoria.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['Fonte', 'Canale da cui arriva la richiesta.'],
            ['Richieste di modifica', 'Proposte di modifica ancora in attesa.'],
            ['Linee di prodotto', "Prodotti presenti nell'offerta."],
            ['Operatore', 'Persona che lavora la richiesta.'],
            ['Sede operativa', 'Sede a cui è assegnata la richiesta.'],
            ['Prossimo richiamo', 'Data della prossima chiamata al cliente.'],
            ['Trasferito', "Se il contatto è stato trasferito da un'altra sede."],
            ['Stato di lavorazione', "Stato operativo dell'offerta."],
          ],
        },
      ],
    },
    {
      id: 'creating-a-request',
      title: 'Creare una nuova richiesta',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova richiesta**.',
            'Scrivi nelle **Note generali** cosa ha chiesto il cliente, con le sue parole.',
            "Se serve, indica il **Prossimo richiamo** (l'ora è facoltativa).",
            'In **Linee di prodotto** aggiungi almeno una riga: categoria genitore, poi categoria prodotto.',
            "Compila le righe dell'offerta: prodotto, quantità, prezzo unitario e IVA.",
            "In **Anagrafica cliente** scegli un'**Anagrafica esistente** oppure inserisci un nuovo cliente.",
            'In **Attribuzione** scegli la **Fonte** (obbligatoria) e, se serve, **Segnalatore** e **Sede operativa**.',
            'Compila le **Informazioni aggiuntive**, se presenti.',
            'Premi **Crea richiesta**.',
          ],
        },
        {
          type: 'tip',
          text: "Scegliendo un'anagrafica esistente, i campi di identità, contatti e indirizzo si nascondono e la richiesta viene collegata a quel cliente.",
        },
        {
          type: 'warning',
          text: 'I **Buoni assegnati** si aggiungono solo dopo aver scelto un **Segnalatore**.',
        },
      ],
    },
    {
      id: 'working-a-request',
      title: 'Lavorare una richiesta',
      blocks: [
        {
          type: 'paragraph',
          text: 'Premi **Visualizza** sulla riga per aprire **Informazioni preliminari**. In alto vedi lo stato commerciale, il prossimo richiamo e un eventuale avviso di trasferimento. Puoi aggiornare:',
        },
        {
          type: 'list',
          items: [
            '**Linee di prodotto**: decidono i prodotti selezionabili e i campi specifici.',
            '**Righe offerta**: prodotti, quantità, prezzi e IVA.',
            '**Stato**: alcuni stati richiedono una **Nota**.',
            '**Anagrafica**, **Attribuzione** e **Team** (**Supervisore** e **Gestori account**).',
            'Le **Informazioni aggiuntive**, che dipendono dalle categorie scelte.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Premi **Salva**. In fondo trovi le schede **Note**, **Documenti** e **Storico**.',
        },
        {
          type: 'warning',
          text: 'Per chiudere con esito positivo serve il codice fiscale oppure la partita IVA del cliente.',
        },
      ],
    },
    {
      id: 'row-actions',
      title: 'Azioni sulle righe',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dal menu della singola riga: **Documenti**, **Note**, **Trasferisci contatto**, **Elimina**, **Attività**. Selezionando più righe, il menu **Azioni** offre:',
        },
        {
          type: 'table',
          headers: ['Azione', 'Cosa fa'],
          rows: [
            [
              'Assegna operatori',
              '**Smistamento equo** distribuisce tra gli operatori della sede; **Assegna a operatore** assegna tutto alla stessa persona.',
            ],
            [
              'Assegna Tutor',
              'Assegna lo stesso utente a tutte le richieste scelte (il nome del ruolo può cambiare per categoria).',
            ],
            ['Trasferisci contatto', "Sposta le richieste su un'altra sede e un altro operatore."],
            ['Elimina selezionati', 'Elimina le righe selezionate.'],
          ],
        },
        {
          type: 'tip',
          text: 'Se nessun operatore è abilitato su tutte le richieste scelte, **Assegna a operatore** non è disponibile: usa **Smistamento equo**.',
        },
      ],
    },
    {
      id: 'statistics-access',
      title: 'Chi può usare le statistiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le statistiche mostrano l\'andamento del lavoro sulle richieste in un periodo scelto, a schermo o in un file CSV/Excel. Pannello e file usano gli stessi filtri e gli stessi calcoli, quindi i numeri coincidono.',
        },
        {
          type: 'paragraph',
          text: "**GA2** è il Gestore account di livello 2, cioè l'operatore che lavora la richiesta. Nelle statistiche ogni valore per operatore si riferisce al GA2 attuale della richiesta, non a chi ha materialmente eseguito l'operazione.",
        },
        {
          type: 'list',
          items: [
            'Serve il permesso **Genera report** del modulo; senza, il pulsante delle statistiche non compare. Gestione Richieste e Gestione Iscritti hanno ciascuna il proprio permesso.',
            "Ognuno vede solo le richieste che vede già nell'elenco: con **Visualizza tutti** tutte; altrimenti quelle di cui è GA2, più quelle delle proprie sedi con **Visualizza per sede**.",
            'I filtri possono solo restringere questa visibilità, mai allargarla.',
          ],
        },
      ],
    },
    {
      id: 'statistics-panel',
      title: 'Aprire e leggere il pannello statistiche',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Opportunità e Commesse › Gestione Richieste**.',
            "In alto a destra, accanto a **Nuova richiesta**, clicca l'icona a forma di grafico (**Mostra statistiche**).",
            "Il pannello si apre sopra le schede delle categorie. Clicca di nuovo l'icona per chiuderlo (**Nascondi statistiche**).",
          ],
        },
        {
          type: 'note',
          text: 'Il browser ricorda se il pannello era aperto e gli ultimi filtri applicati.',
        },
        {
          type: 'table',
          headers: ['Parte', 'Contenuto'],
          rows: [
            [
              'Filtri applicati',
              'Un riquadro per Periodo, Categorie, Sedi, Operatori, Righe: colorato se restringe i dati, neutro se vale "tutto". A destra **Genera report** e **Filtri**.',
            ],
            [
              'Totale complessivo',
              'Una casella per colonna, su tutte le categorie selezionate insieme. Una richiesta presente in più categorie conta una volta sola.',
            ],
            [
              'Riepilogo per categoria',
              'Una casella per ogni colonna configurata per la categoria, con il valore totale (zeri compresi).',
            ],
            [
              'Grafici per categoria',
              '**Indicatori** confronta i totali delle colonne; poi un grafico per colonna con una barra per operatore, dal valore più alto.',
            ],
          ],
        },
        {
          type: 'paragraph',
          text: 'Ogni sezione si chiude e riapre con la freccia accanto al titolo. Il pulsante con la doppia freccia, accanto a **Filtri**, apre tutto in un colpo (**Espandi tutto**, grafici compresi) oppure, se è già tutto aperto, chiude tutte le sezioni (**Comprimi tutto**).',
        },
        {
          type: 'table',
          headers: ['Pannello', 'File CSV/Excel'],
          rows: [
            ['Si aggiorna subito', 'Viene preparato e poi scaricato'],
            ['Ha il Totale complessivo', 'Non ha una riga di totale generale'],
            ['Valori per operatore solo nei grafici', 'Una riga per ogni operatore'],
            [
              'Solo le colonne configurate di ogni categoria',
              'Tutte le colonne usate da almeno una categoria selezionata',
            ],
          ],
        },
      ],
    },
    {
      id: 'statistics-filters',
      title: 'I filtri delle statistiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Clicca **Filtri** per aprire il pannello **Filtri report e statistiche**: modifica i valori e premi **Applica** (o **Annulla**). **Azzera filtri** riporta i valori iniziali (settimana corrente, tutte le categorie, sedi e operatori, modalità Tutto): la modifica vale solo dopo **Applica**. Gli stessi filtri valgono per grafici e file.',
        },
        {
          type: 'table',
          headers: ['Filtro', 'Cosa fa', 'Valore predefinito'],
          rows: [
            ['Dal / Al', 'Periodo considerato, giorni inclusi per intero.', 'Da lunedì a venerdì della settimana corrente'],
            ['Categorie', 'Quali categorie includere.', 'Tutte'],
            ['Sedi', 'Limita agli operatori di quelle sedi.', 'Tutte'],
            ['Operatori', 'Limita a certi operatori GA2.', 'Tutti'],
            ['Righe da includere', 'Solo totale, Solo operatori o Tutto.', 'Tutto'],
          ],
        },
        {
          type: 'note',
          text: 'Al non può precedere Dal. La data usata cambia da colonna a colonna (vedi la tabella delle colonne). N. Richiami non gestiti, N. Nuovi contatti non gestiti e N. Potenziali associati ignorano il periodo e guardano alla situazione di oggi; le loro versioni "(nel periodo selezionato)" lo usano.',
        },
        {
          type: 'list',
          items: [
            'Primo clic su una categoria con sottocategorie: selezioni solo la categoria.',
            'Secondo clic: aggiungi anche tutte le sottocategorie.',
            'Terzo clic: togli categoria e sottocategorie.',
          ],
        },
        {
          type: 'note',
          text: "Anche selezionando solo la categoria superiore, la sua riga conta già le richieste delle sottocategorie. Serve almeno una categoria; **Seleziona tutto** seleziona o toglie l'intero elenco.",
        },
        {
          type: 'paragraph',
          text: 'Sedi e Operatori compaiono solo con Solo operatori o Tutto. La sede di una richiesta, qui, è la sede del suo operatore GA2, non la sede operativa della richiesta; filtrando per sede le richieste senza operatore restano escluse.',
        },
        {
          type: 'warning',
          text: 'Con Solo totale i filtri Sedi e Operatori non si applicano: il totale comprende tutti. Le scelte restano memorizzate per quando cambi modalità.',
        },
      ],
    },
    {
      id: 'statistics-report-structure',
      title: 'Struttura del report',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quali categorie compaiono si decide in **Prodotti › Categorie Prodotto**: **Visibile nei report** mette la categoria nel report (le sottocategorie ereditano e possono forzarlo a no, escludendo anche le proprie); **Colonne report** sceglie le colonne calcolate. Una categoria esclusa non è contata nemmeno nei totali della categoria superiore.',
        },
        {
          type: 'paragraph',
          text: 'Una richiesta appartiene a ogni categoria di almeno uno dei suoi prodotti, sottocategorie incluse: una richiesta con prodotti di due categorie conta in entrambe.',
        },
        {
          type: 'list',
          items: [
            "Le righe del file: le categorie compaiono in ordine alfabetico, ognuna seguita dalle sue sottocategorie selezionate (anch'esse alfabetiche).",
            'Per ogni categoria: riga TOTALE, poi operatori in ordine alfabetico, infine Non assegnato.',
          ],
        },
        {
          type: 'note',
          text: 'Un operatore compare solo se ha almeno un valore diverso da zero; Non assegnato solo se ci sono richieste senza operatore che contribuiscono ai conteggi.',
        },
        {
          type: 'paragraph',
          text: 'Le colonne del file: prima le fisse Categoria e GA2 (TOTALE, nome operatore o Non assegnato), poi le colonne statistiche usate da almeno una categoria selezionata. Se una colonna non è configurata per la categoria della riga la cella resta vuota; uno 0 indica una colonna configurata ma senza risultati.',
        },
      ],
    },
    {
      id: 'statistics-columns',
      title: 'Le colonne statistiche',
      blocks: [
        {
          type: 'table',
          headers: ['Colonna', 'Cosa conta', 'Periodo e note'],
          rows: [
            [
              'N. Telefonate Effettuate',
              'Le note collegate alla richiesta e scritte dal suo GA2: ogni nota vale una telefonata.',
              'Data di creazione della nota. Escluse le richieste ancora Aperte, le note cancellate, generali o scritte da altri; per Non assegnato vale sempre 0.',
            ],
            [
              'N. Richiami non gestiti',
              'Le richieste con data di richiamo di oggi o già passata, non ancora chiuse.',
              'Ignora il periodo: guarda sempre alla data di oggi.',
            ],
            [
              'N. Richiami non gestiti (nel periodo selezionato)',
              'Le richieste non ancora chiuse con data di richiamo compresa nel periodo.',
              'Data di richiamo; conta anche i richiami futuri se cadono nel periodo.',
            ],
            [
              'N. Nuovi contatti non gestiti',
              'Le richieste ancora nello stato Aperto.',
              'Ignora il periodo: conta anche le richieste create prima.',
            ],
            [
              'N. Nuovi contatti non gestiti (nel periodo selezionato)',
              'Le richieste create nel periodo e ancora nello stato Aperto.',
              'Data di creazione della richiesta.',
            ],
            [
              'N. Potenziali associati',
              'Le richieste che oggi si trovano in uno stato del gruppo In attesa o Validato.',
              'Ignora il periodo: guarda lo stato attuale.',
            ],
            [
              'N. Potenziali associati (nel periodo selezionato)',
              'Le richieste passate nel periodo a uno stato del gruppo In attesa o Validato.',
              'Data del cambio di stato; ogni richiesta conta una volta.',
            ],
            [
              'Associati',
              'Le richieste passate nel periodo a uno stato del gruppo Chiuso con esito positivo.',
              'Data del cambio di stato; conta anche se la richiesta è poi tornata indietro.',
            ],
            ['Trattative Concluse', 'Stesso calcolo di Associati.', 'Cambia solo il nome, secondo le categorie che lo usano.'],
            ['Invio Presa in carico', 'Stesso calcolo di Associati.', 'Cambia solo il nome, secondo le categorie che lo usano.'],
            [
              'Aziende inserite',
              'Le anagrafiche di tipo azienda create nel periodo e collegate come cliente a una richiesta.',
              "Data di creazione dell'anagrafica; nel TOTALE ogni azienda conta una volta.",
            ],
            ['Aule in gestione', 'Non ancora calcolata.', 'Vale sempre 0.'],
            ['Aule in partenza', 'Non ancora calcolata.', 'Vale sempre 0.'],
            ['Presa Appuntamenti', 'Non ancora calcolata.', 'Vale sempre 0.'],
          ],
        },
        {
          type: 'note',
          text: 'In Aziende inserite la somma delle righe degli operatori può superare il TOTALE, se la stessa azienda è collegata a richieste di operatori diversi. Nelle altre colonne il TOTALE è uguale alla somma delle righe.',
        },
        {
          type: 'tip',
          text: 'I cambi di stato contano sia se fatti da Gestione Richieste sia dal modulo Offerte.',
        },
      ],
    },
    {
      id: 'statistics-export',
      title: 'Generare il file',
      blocks: [
        {
          type: 'steps',
          items: [
            'Controlla i filtri in **Filtri applicati**.',
            'Clicca **Genera report** e scegli **CSV** oppure **Excel (XLSX)**.',
            'Attendi il messaggio "Generazione in corso…": resta sulla pagina.',
            'Al termine compare "Report generato: il download è partito automaticamente."',
          ],
        },
        {
          type: 'paragraph',
          text: 'Non arriva una notifica separata. Il file si chiama, per esempio, request-management-report-2026-09-14_2026-09-18.xlsx (date Dal e Al).',
        },
        {
          type: 'warning',
          text: 'Se la preparazione non riesce compare "La generazione del report non è riuscita. Riprova."',
        },
      ],
    },
    {
      id: 'statistics-faq',
      title: 'Domande frequenti sulle statistiche',
      blocks: [
        {
          type: 'list',
          items: [
            '**Perché vedo 0?** La colonna è tra quelle non ancora calcolate; nel periodo non è successo nulla; le note sono di un utente diverso dal GA2; la richiesta è ancora in Aperto; sede e operatore scelti non hanno nulla in comune.',
            '**Perché una cella è vuota e non 0?** La colonna non è configurata per quella categoria: controlla Colonne report in Categorie Prodotto.',
            '**Perché un operatore non compare?** Non ha valori diversi da zero nel periodo, hai scelto Solo totale, è escluso dai filtri Operatori o Sedi, oppure non vedi le sue richieste con i tuoi permessi.',
            '**Perché manca "Non assegnato"?** Non ci sono richieste senza operatore che contribuiscono ai conteggi, oppure hai filtrato per sede.',
            '**Perché i totali del padre non includono una sottocategoria?** La sottocategoria ha Visibile nei report a no, direttamente o per eredità.',
            "**Perché i numeri di un operatore sono cambiati dopo un trasferimento?** I conteggi guardano l'operatore attuale: le note del vecchio operatore non contano più come telefonate.",
            '**Perché alcune colonne non cambiano con il periodo?** N. Richiami non gestiti, N. Nuovi contatti non gestiti e N. Potenziali associati guardano sempre alla situazione di oggi. Per il dato del periodo usa la loro versione "(nel periodo selezionato)", da attivare in Colonne report della categoria.',
            '**Perché il Totale complessivo è più basso della somma delle categorie?** Una richiesta presente in più categorie selezionate conta una volta sola nel totale complessivo.',
          ],
        },
      ],
    },
  ],
}

export default guide
