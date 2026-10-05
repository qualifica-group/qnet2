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
          text: 'In alto trovi una scheda per ogni categoria prodotto, più **Tutte**: scegline una per vedere solo le richieste di quella categoria. Le schede che non entrano nella riga finiscono in **Altre (N)**, a destra: aprilo, scrivi parte del nome per filtrare l\'elenco e scegli la categoria col clic o con le frecce e **Invio**. La categoria scelta resta sempre visibile tra le schede.',
        },
        {
          type: 'paragraph',
          text: 'Per avere sempre a portata le categorie che usi, segnale come preferite: nel menu (**Altre (N)**, oppure la stella a destra delle schede quando entrano tutte) tocca la stella accanto alla categoria. Le preferite compaiono per prime tra le schede e in cima al menu. Attiva **Mostra solo preferite** per vedere tra le schede solo quelle (più la categoria che stai guardando); le altre restano nel menu. Le preferite sono salvate sul tuo utente, anche cambiando computer, e sono separate per Gestione Richieste e Gestione Iscritti.',
        },
        {
          type: 'paragraph',
          text: "Finché non modifichi le preferite, quelle iniziali sono le categorie per cui sei abilitato (in base alla competenza del tuo profilo) e **Mostra solo preferite** è già attivo: tra le schede vedi **Tutte** e le tue categorie, le altre restano in **Altre (N)**. Nel menu un avviso ti ricorda che sono proposte automatiche. Appena tocchi una stella o l'interruttore, vale solo la tua scelta e non cambia più con la competenza. Se sei abilitato a tutte le categorie o non hai competenze, vedi tutte le schede.",
        },
        {
          type: 'paragraph',
          text: 'I numeri sulle schede si aggiornano entro pochi secondi dalle modifiche fatte dagli altri utenti; le tue modifiche le vedi subito, appena torni alla tabella.',
        },
        {
          type: 'paragraph',
          text: "Il campo **Cerca** trova le richieste per nome, cognome, codice fiscale, partita IVA, telefono o email principali del cliente. Parte dal terzo carattere e cerca le parole che **iniziano** con quanto scrivi: \"ros\" trova Rossi, \"ssi\" no. Con più parole (per esempio \"mario rossi\") devono esserci tutte. Le parole di una o due lettere vengono ignorate. Con termini molto generici l'elenco può non essere completo: aggiungi una parola per restringere.",
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
        {
          type: 'paragraph',
          text: "Clicca la cella **Categoria prodotto** per cambiarla senza aprire la richiesta: scegli la categoria genitore, poi la categoria prodotto. Se la categoria presente è gestita a riga singola (ad esempio Formazione), quella che scegli la sostituisce; altrimenti viene aggiunta alle altre, e le categorie a riga singola non sono selezionabili (vale anche per la seconda riga del form). Con la **X** rimuovi una categoria. Le righe dell'offerta con un prodotto di una categoria tolta vengono eliminate al salvataggio: un avviso le elenca prima.",
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
            'Premi **Crea richiesta**: torni alla tabella, sulla scheda categoria che avevi aperto.',
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
            "**Linee di prodotto**: decidono i prodotti selezionabili e i campi specifici. Se cambi o togli una linea, le righe offerta con un prodotto di una categoria non più scelta si svuotano: scegli di nuovo il prodotto.",
            '**Righe offerta**: prodotti, quantità, prezzi e IVA.',
            '**Stato**: alcuni stati richiedono una **Nota**.',
            '**Anagrafica**, **Attribuzione** e **Team** (**Supervisore** e **Gestori account**).',
            'Le **Informazioni aggiuntive**, che dipendono dalle categorie scelte.',
          ],
        },
        {
          type: 'paragraph',
          text: 'In fondo trovi le schede **Note**, **Documenti** e **Storico**. Premi **Salva**: torni alla tabella, sulla scheda categoria da cui eri partito (ad esempio **GOL Abruzzo**). Lo stesso vale per **Trasferisci contatto**, accanto a **Salva**: a trasferimento concluso torni alla tabella, perché il contatto passa alla nuova sede e al nuovo operatore.',
        },
        {
          type: 'warning',
          text: 'Per chiudere con esito positivo serve il codice fiscale oppure la partita IVA del cliente.',
        },
        {
          type: 'paragraph',
          text: 'Se apri il link di una richiesta che non esiste più vedi **Record non trovato**; se la richiesta non rientra nella tua visibilità vedi **Accesso negato**. In entrambi i casi torna alla tabella con **Indietro**.',
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
              '**Smistamento equo** mostra gli operatori raggruppati per Sede (tutti selezionati, deselezionabili per gruppo o singolarmente) e distribuisce tra chi resta selezionato; **Assegna a operatore** assegna tutto alla stessa persona.',
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
        {
          type: 'note',
          text: "Il nuovo **Operatore** di ogni richiesta riceve la notifica **Sei stato inserito come Gestore Account** con il titolo e i dati dell'offerta; il link apre l'offerta, oppure la richiesta qui se non ha accesso alle Offerte. Gli altri Gestori Account non ricevono notifiche.",
        },
      ],
    },
    {
      id: 'transfer-notifications',
      title: 'Chi riceve le notifiche di trasferimento',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quando un contatto viene trasferito, la notifica (campanella ed email) arriva solo a:',
        },
        {
          type: 'table',
          headers: ['Chi', 'Cosa riceve'],
          rows: [
            ['Chi aveva il contatto', 'Avviso che il contatto non è più suo e a chi è passato.'],
            ['Chi riceve il contatto', 'Avviso che il contatto gli è stato assegnato.'],
            [
              'Chi ha il permesso **Visualizza tutti**',
              'Riepilogo di ogni trasferimento del modulo, anche quando non è coinvolto.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'Gli altri utenti non ricevono nulla, anche se lavorano sulla stessa sede. Chi esegue il trasferimento non riceve mai la propria notifica, e nessuno la riceve due volte. Il link apre l\'offerta, oppure la richiesta qui se non ha accesso alle Offerte; senza nessuno dei due accessi la notifica arriva senza link.',
        },
      ],
    },
    {
      id: 'statistics-moved',
      title: 'Dove sono le statistiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le statistiche non sono più nella tabella di Gestione Richieste né in quella di Gestione Iscritti: hanno una pagina propria, **Opportunità e Commesse › Statistiche Gestione Richieste** (vedi la relativa guida). Gestione Iscritti non ha più statistiche.',
        },
      ],
    },
  ],
}

export default guide
