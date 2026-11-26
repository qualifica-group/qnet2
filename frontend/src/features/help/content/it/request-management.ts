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
          text: 'Si trova in **Commerciale › Gestione Richieste**. Ogni riga dell\'elenco corrisponde a un\'offerta collegata a un\'opportunità.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi una scheda per ogni categoria prodotto, più **Tutte**: scegline una per vedere solo le richieste di quella categoria. Le schede che non entrano nella riga finiscono in **Altre (N)**, a destra: aprilo, scrivi parte del nome per filtrare l\'elenco e scegli la categoria col clic o con le frecce e **Invio**. La categoria scelta resta sempre visibile tra le schede.',
        },
        {
          type: 'paragraph',
          text: "Le categorie la cui impostazione **Visibile in** (nella scheda della categoria) è **Opportunità / Offerte** non fanno parte di Gestione Richieste: non hanno una scheda, non si possono scegliere (né nel form né nella cella **Categoria prodotto**) e non compaiono nei filtri e nelle statistiche. Una richiesta che ha **solo** categorie di questo tipo non compare nell'elenco, nei conteggi e nella ricerca, e il suo link risponde **Accesso negato**; se ha anche una categoria usata qui resta visibile. Rimettendo **Tutti i moduli** sulla categoria tutto torna come prima: nessun dato viene cancellato.",
        },
        {
          type: 'paragraph',
          text: 'Cosa vedi dipende dai permessi. Senza altri permessi vedi le richieste del cui **Team** fai parte: come operatore, Supervisore o Gestore account. **Visualizza per sede** aggiunge quelle delle tue sedi; **Visualizza per competenza** quelle con un prodotto in una categoria del tuo ambito di assegnazione, in qualunque sede (con **Ambito su tutte le categorie**, tutte); **Visualizza tutti** tutte. Se crei una richiesta fuori dal tuo ambito, non la vedrai più a meno di avere uno di questi permessi.',
        },
        {
          type: 'paragraph',
          text: 'Per avere sempre a portata le categorie che usi, segnale come preferite: nel menu (**Altre (N)**, oppure la stella a destra delle schede quando entrano tutte) tocca la stella accanto alla categoria. Le preferite compaiono per prime tra le schede e in cima al menu. Attiva **Mostra solo preferite** per vedere tra le schede solo quelle (più la categoria che stai guardando); le altre restano nel menu. Con **Seleziona tutte** e **Deseleziona tutte**, nel menu, aggiungi o togli in un colpo solo tutte le categorie dalle preferite; i due pulsanti non compaiono mentre stai cercando. Le preferite sono salvate sul tuo utente, anche cambiando computer, e sono separate per Gestione Richieste e Gestione Iscritti.',
        },
        {
          type: 'paragraph',
          text: "Nel menu **Altre (N)** l'interruttore **Categorie madri** limita le schede alle sole categorie di primo livello: la scheda di una categoria madre raccoglie anche le richieste delle sottocategorie (una richiesta conta una volta sola) e aggiunge le colonne **Informazioni aggiuntive** assegnate alla categoria madre (quelle delle sole sottocategorie compaiono solo nella loro scheda). A differenza di una singola categoria, non rinomina i campi dei gestori. La scelta resta salvata nel browser per questo elenco, ogni modalità ricorda la propria scheda e le preferite sono separate tra le due modalità. L'interruttore è acceso di default: spegnendolo vedi tutte le categorie.",
        },
        {
          type: 'paragraph',
          text: "Finché non modifichi le preferite, quelle iniziali sono le categorie per cui sei abilitato (in base all’ambito di assegnazione del tuo profilo) e **Mostra solo preferite** è già attivo: tra le schede vedi **Tutte** e le tue categorie, le altre restano in **Altre (N)**. Nel menu un avviso ti ricorda che sono proposte automatiche. Appena tocchi una stella o l'interruttore, vale solo la tua scelta e non cambia più con l’ambito di assegnazione. Se sei abilitato a tutte le categorie o non hai ambiti di assegnazione, vedi tutte le schede.",
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
          type: 'paragraph',
          text: "Tutte le colonne si ordinano con un clic sull'intestazione e si filtrano dal menu dell'intestazione. **Categoria prodotto** e **Linee di prodotto**, che possono contenere più voci, si ordinano per la prima voce in ordine alfabetico. **Richieste di modifica** si filtra per numero (ad esempio maggiore di 0 per vedere solo le richieste con proposte in attesa) e **Caricato il**, nascosta all'inizio, per data.",
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['Ragione sociale', "Denominazione del cliente azienda, dalla sua scheda anagrafica. Si ordina e si filtra; si modifica dal pannello, non dalla cella, e il campo **Cerca** non la considera."],
            ['Partita IVA', 'Partita IVA del cliente. Si ordina, si filtra e si modifica dalla cella.'],
            ['Fonte', "Canale da cui arriva la richiesta (è un campo dell'offerta)."],
            ['Richieste di modifica', 'Proposte di modifica ancora in attesa.'],
            ['Linee di prodotto', "Prodotti presenti nell'offerta; la cella apre l'editor con il catalogo prodotti."],
            ['Gestori account', "L'unica colonna dei gestori: mostra tutte le persone del team della richiesta (operatore, tutor e ogni altro gestore), uguale in **Tutte** e in ogni scheda categoria. Si filtra per nome, anche con **(Vuoti)** per le richieste senza gestori, e non si ordina. Dalla cella aggiungi o togli persone: chi resta mantiene il suo ruolo, chi aggiungi prende il primo posto libero e riceve la notifica. Per scegliere il ruolo preciso di ciascuno (ad esempio **Operatore** o **Tutor**) apri la richiesta con **Visualizza** (sezione **Team**)."],
            ['Sede operativa', 'Sede a cui è assegnata la richiesta.'],
            ['Prossimo richiamo', 'Data della prossima chiamata al cliente.'],
            ['Trasferito', "Se il contatto è stato trasferito da un'altra sede."],
            ['Stato di lavorazione', "Stato operativo dell'offerta."],
            ['Valore stimato, Probabilità di successo, Data chiusura prevista', "Previsione commerciale dell'opportunità. Nascoste all'inizio: attivale dalla scelta colonne. Si ordinano e si filtrano, non si modificano dalla cella."],
          ],
        },
        {
          type: 'paragraph',
          text: "Clicca la cella **Categoria prodotto** per cambiarla senza aprire la richiesta: scegli la categoria genitore, poi la categoria prodotto (con **Filtro** puoi prima restringere l'elenco a un gruppo intermedio, ad esempio Consulenza › ISO). Se la categoria presente è gestita a riga singola (ad esempio Formazione), quella che scegli la sostituisce; altrimenti viene aggiunta alle altre, e le categorie a riga singola non sono selezionabili (vale anche per la seconda riga del form). Con la **X** rimuovi una categoria. Le righe dell'offerta con un prodotto di una categoria tolta vengono eliminate al salvataggio: un avviso le elenca prima.",
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
            'Se le conosci, compila la **Previsione commerciale**: **Data chiusura prevista**, **Valore stimato** e **Probabilità di successo** (lo slider a 0 vale "non indicata").',
            "Se serve, indica il **Prossimo richiamo** (l'ora è facoltativa).",
            "In **Linee di prodotto** aggiungi una riga per ogni categoria genitore: scegli la categoria genitore, poi una o più sue categorie prodotto. Nell'elenco **Seleziona tutte** le prende tutte in un clic e un clic su un gruppo prende tutte le sue sottocategorie: le categorie scelte restano nella stessa riga (ad esempio ISO › ISO 9001, ISO 14001), non una riga ciascuna. Una categoria genitore gestita a riga singola (ad esempio Formazione) ammette una sola categoria; una categoria genitore già usata in un'altra riga non si può scegliere di nuovo. Se la categoria genitore ha dei gruppi (ad esempio Consulenza › ISO), con **Filtro** restringi l'elenco a un gruppo; il filtro non viene salvato, solo le categorie.",
            "La sezione **Offerta** si apre dopo la scelta della prima linea di prodotto. Nel **Catalogo prodotti** spunta i prodotti e premi **Aggiungi N selezionati**, poi controlla quantità, prezzo unitario e IVA delle righe; nelle categorie semplificate c'è solo il campo **Prodotto** da scegliere (vedi **Righe dell'offerta e catalogo prodotti**).",
            "In **Anagrafica cliente** scegli un'**Anagrafica esistente** oppure inserisci un nuovo cliente. La scheda del nuovo cliente parte come **Persona fisica** o **Azienda** secondo la regola **Anagrafica in Gestione Richieste** della categoria scelta nella prima riga di **Linee di prodotto**; finché non hai scritto nulla segue la categoria, poi puoi sempre cambiare il tipo a mano.",
            'In **Attribuzione** scegli la **Fonte** (obbligatoria) e, se serve, **Segnalatore**, **Commerciale**, **Partner commerciale** e **Sede operativa**. Commerciale e Partner commerciale scelti qui valgono sia per la richiesta sia per la sua opportunità. Se stai inserendo un nuovo cliente, Fonte, Segnalatore, Commerciale e Partner commerciale vengono salvati anche sulla sua anagrafica.',
            'Compila le **Informazioni aggiuntive**, se presenti.',
            'Premi **Crea richiesta**: torni alla tabella, sulla scheda categoria che avevi aperto.',
          ],
        },
        {
          type: 'note',
          text: "Se le **Linee di prodotto** appartengono a più categorie madri (ad esempio una riga ISO e una APL), **Crea richiesta** crea una richiesta per ogni categoria madre, tutte sulla stessa opportunità (prima di salvare, un avviso sotto le **Linee di prodotto** dice quante richieste verranno create e per quali categorie madri): ciascuna compare solo nella scheda della sua categoria, ha solo le righe offerta e le **Informazioni aggiuntive** della sua categoria, mentre i **Buoni** vanno solo sulla prima. Righe di più categorie della stessa madre (ad esempio ISO 9001 e ISO 14001) restano in un'unica richiesta. Un prodotto di una categoria madre non scelta nelle linee non si può aggiungere.",
        },
        {
          type: 'tip',
          text: "Scegliendo un'anagrafica esistente, i campi di identità, contatti e indirizzo si nascondono e la richiesta viene collegata a quel cliente.",
        },
        {
          type: 'tip',
          text: "Se il nuovo cliente che stai inserendo esiste già, sotto la scelta dell'anagrafica compare **Possibile duplicato**: scegli quell'anagrafica invece di crearne un'altra. Il pulsante **Usa** accanto al duplicato la seleziona subito; se il telefono o l'email appartengono a un referente, il pulsante seleziona l'azienda a cui il referente è collegato. Codice fiscale, partita IVA o telefono già presenti su un'altra scheda bloccano il salvataggio (vedi la guida **Anagrafiche**).",
        },
        {
          type: 'warning',
          text: 'I **Buoni assegnati** si aggiungono solo dopo aver scelto un **Segnalatore**.',
        },
        {
          type: 'paragraph',
          text: "In fondo al form, nella sezione **Documenti**, allega i file della richiesta: vengono caricati al salvataggio. Se le **Linee di prodotto** creano più richieste (una per categoria madre), accanto a ogni file scegli la richiesta in cui metterlo (ad esempio **Richiesta ISO** o **Richiesta APL**); se togli dalle linee la categoria madre scelta, il file va sulla prima. Se un file non si carica, la richiesta viene creata comunque e un avviso indica quale file allegare dal pannello **Lavora**. La sezione compare solo a chi può vedere e caricare documenti.",
        },
      ],
    },
    {
      id: 'offer-rows-editor',
      title: "Righe dell'offerta e catalogo prodotti",
      blocks: [
        {
          type: 'paragraph',
          text: "Lo stesso editor si trova nella cella **Linee di prodotto** della tabella (si apre in una finestra), nella scheda della richiesta e nel form **Nuova richiesta**. A sinistra c'è il **Catalogo prodotti**, a destra le **Righe dell'offerta**; su schermi stretti sono uno sotto l'altro.",
        },
        {
          type: 'steps',
          items: [
            'Nel **Catalogo prodotti** cerca per nome o descrizione, oppure filtra con le chip: **Tutte** e una chip per ogni categoria prodotto scelta nella richiesta.',
            'Ogni prodotto mostra nome, codice, tipologia (badge colorato), prezzo con unità di misura, percorso Categoria genitore › Categoria e descrizione.',
            'Spunta uno o più prodotti con le caselle e premi **Aggiungi N selezionati**: viene creata una riga per prodotto, con quantità 1 e con prezzo e IVA del prodotto.',
            "Su ogni riga modifica quantità, prezzo unitario e IVA: vedi imponibile, IVA e totale. Il pulsante di rimozione elimina la riga. In fondo c'è il **Totale offerta**.",
          ],
        },
        {
          type: 'list',
          items: [
            "Se il prodotto ha dei fornitori, sulla riga compare **Fornitore** con i fornitori del prodotto: il predefinito è già scelto e il campo è obbligatorio. Il prezzo della riga è quello del fornitore scelto e cambia se cambi fornitore. Se il fornitore ha una commissione Fornitore su quel prodotto, accanto al fornitore compare l'avviso (per esempio 'Commissione 10% pagata'). I prodotti proposti automaticamente arrivano con il fornitore predefinito.",
            'Un prodotto già nelle righe mostra **Già aggiunto** e non è selezionabile: cambia la quantità sulla riga.',
            'Nelle categorie a riga singola (ad esempio Formazione) non ci sono caselle: il clic su un prodotto sostituisce quello della riga unica.',
            'Senza categorie prodotto scelte il catalogo è disattivato: scegli prima la categoria.',
            "Nelle categorie semplificate l'offerta è un solo campo **Prodotto**: scegli il prodotto dall'elenco (più prodotti, se la categoria lo consente; nelle categorie a riga singola uno solo) e toglilo con la X. Quantità, prezzo e IVA li imposta il sistema (quantità 1, prezzo e IVA del prodotto); sotto il campo compare il **Valore indicativo (IVA inclusa)** dei prodotti scelti.",
            "Se tutte le categorie delle **Linee di prodotto** hanno la regola **Nascondi offerta in Gestione Richieste**, la sezione **Offerta** non compare (in Nuova richiesta e nel pannello Lavora; dalla tabella, la modifica delle righe mostra un avviso). Con categorie miste il catalogo propone solo i prodotti delle categorie non nascoste. Le righe già salvate restano come sono.",
            'Se la categoria ha un solo prodotto, la riga si crea da sola.',
            'Una riga **assegnata a una commessa** mostra "Assegnata a COM-..." ed è bloccata: non si modifica né si rimuove finché la commessa non viene eliminata. Se cambi la categoria di una richiesta che ha una riga bloccata, il salvataggio viene rifiutato.',
          ],
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
            "**Linee di prodotto**: decidono i prodotti selezionabili e i campi specifici. Se cambi o togli una linea, le righe offerta con un prodotto di una categoria non più scelta si svuotano: scegli di nuovo il prodotto. Una richiesta creata per una categoria madre mostra solo le sue linee e accetta solo categorie e prodotti di quella madre: per un'altra categoria madre crea una nuova richiesta. Una richiesta con linee del catalogo vecchio ([OLD]) accetta solo categorie delle categorie madri che ha già: non puoi aggiungere né sostituire con una categoria madre diversa.",
            '**Righe offerta**: prodotti scelti dal **Catalogo prodotti**, con quantità, prezzi e IVA.',
            "**Previsione commerciale**: data chiusura prevista, valore stimato e probabilità di successo. Sono i valori dell'opportunità: se cambiano qui, cambiano anche in **Opportunità** e nelle altre richieste della stessa opportunità.",
            "**Stato**: alcuni stati richiedono una **Nota**. Per uno stato validato serve almeno una riga offerta. Per chiudere non serve, né con esito positivo né con esito negativo: la riga prodotto diventa obbligatoria quando si valida il contratto.",
            "**Anagrafica**: il campo in alto mostra il cliente della richiesta e permette di sceglierne un altro. Il cambio vale per tutte le offerte dell'opportunità. Scelto un altro cliente, i suoi dati compaiono dopo il salvataggio e le modifiche fatte ai dati del cliente precedente non vengono salvate. Se c'è già un contratto, o il cliente arriva dal lead, il campo non è modificabile.",
            '**Attribuzione** (**Fonte**, **Segnalatore**, **Commerciale**, **Partner commerciale**) e **Team** (**Supervisore** e **Gestori account**). **Commerciale** e **Partner commerciale** modificati qui valgono solo per questa richiesta (l\'offerta): quelli dell\'opportunità non cambiano. Chi aggiungi tra i **Gestori account**, in qualsiasi posizione (anche dalle colonne **Gestore account** della tabella), riceve la notifica **Sei stato inserito come Gestore Account** nella campanella e per email; non la ricevono chi viene solo spostato di posizione e chi inserisce se stesso.',
            'Le **Informazioni aggiuntive**, che dipendono dalle categorie scelte.',
          ],
        },
        {
          type: 'paragraph',
          text: 'In fondo trovi le schede **Note**, **Documenti**, **Documenti opportunità** e **Storico**. **Documenti** sono i file di questa richiesta (li carichi ed elimini qui); **Documenti opportunità** sono quelli già presenti sull\'opportunità, in sola lettura. Premi **Salva**: torni alla tabella, sulla scheda categoria da cui eri partito (ad esempio **GOL Abruzzo**). Lo stesso vale per **Trasferisci contatto**, accanto a **Salva**: a trasferimento concluso torni alla tabella, perché il contatto passa alla nuova sede e al nuovo operatore. Con **Trasferisci ad altra anagrafica** (permesso omonimo) sposti invece l\'offerta su un altro cliente: scegli la nuova anagrafica, puoi cambiare il **Titolo** dell\'offerta e controlli le **Informazioni aggiuntive**; l\'offerta va sull\'opportunità aperta del cliente o su una nuova, come spiegato nella guida **Offerte**. Se una categoria ha **Trasferimento contatto prima del contratto** (ad esempio **Orientamento specialistico**), la richiesta non passa a uno stato **Vinto** finché non hai usato **Trasferisci contatto**.',
        },
        {
          type: 'warning',
          text: 'Per chiudere con esito positivo serve il codice fiscale oppure la partita IVA del cliente.',
        },
        {
          type: 'warning',
          text: "Se modifichi i dati del cliente, dal pannello o direttamente nella cella della tabella, non puoi inserire un codice fiscale, una partita IVA o un telefono che appartengono già a un'altra scheda: il salvataggio viene rifiutato. Puoi invece inserire il telefono di un referente collegato a quel cliente. I valori già presenti sul cliente restano modificabili.",
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
          text: 'Dal menu della singola riga: **Documenti** (i file di quella richiesta), **Note** (in alto nella finestra c\'è la **Data richiamo** della riga, così la vedi mentre scrivi), **Trasferisci contatto**, **Elimina**, **Attività**. Selezionando più righe, il menu **Azioni** offre:',
        },
        {
          type: 'table',
          headers: ['Azione', 'Cosa fa'],
          rows: [
            [
              'Assegna operatori',
              '**Smistamento equo** mostra gli operatori raggruppati per Sede (tutti selezionati, deselezionabili per gruppo o singolarmente) e distribuisce tra chi resta selezionato — con **Tieni conto del carico attuale** acceso favorisce chi ha meno offerte in carico, spento divide in parti uguali; **Assegna a operatore** assegna tutto alla stessa persona.',
            ],
            [
              'Assegna Tutor',
              'Assegna lo stesso utente a tutte le richieste scelte (il nome del ruolo può cambiare per categoria).',
            ],
            ['Trasferisci contatto', "Sposta le richieste su un'altra sede e un altro operatore. Su una richiesta con contratto è disponibile solo finché il contratto non è stato lavorato (o, se la categoria richiede il trasferimento, finché non è validato); il contratto segue la richiesta."],
            ['Elimina selezionati', 'Elimina le righe selezionate.'],
          ],
        },
        {
          type: 'tip',
          text: 'Se nessun operatore è abilitato su tutte le richieste scelte, **Assegna a operatore** non è disponibile: usa **Smistamento equo**.',
        },
        {
          type: 'note',
          text: "Con le azioni di assegnazione, chi entra tra i **Gestori account** di una richiesta riceve la notifica **Sei stato inserito come Gestore Account**, nella campanella e per email, con il titolo e i dati dell'offerta, compresi tutti i Gestori account; il link apre l'offerta, oppure la richiesta qui se non ha accesso alle Offerte. Vale allo stesso modo per ogni Gestore account. Non la ricevono chi era già nel team, chi viene solo spostato e chi assegna se stesso.",
        },
      ],
    },
    {
      id: 'quote-under-contract',
      title: "Offerta a contratto",
      blocks: [
        {
          type: 'paragraph',
          text: "Se l'offerta ha un contratto è **congelata**: nel pannello **Lavora** compare l'avviso **Offerta a contratto** e, senza il permesso **Modifica offerte a contratto**, i campi sono in sola lettura (anche le celle della lista). Con il permesso, il salvataggio chiede prima conferma in un popup.",
        },
        {
          type: 'note',
          text: "Restano sempre modificabili i campi operativi: **Gestori account** (team), **Data prossimo richiamo**, **Sede operativa** e **Note generali**.",
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
              'Chi ha il ruolo **notifiche-trasferimenti-contatti**',
              'Riepilogo di ogni trasferimento, anche quando non è coinvolto. Il permesso **Visualizza tutti** fa vedere tutte le richieste ma non fa ricevere il riepilogo.',
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
          text: 'Le statistiche non sono più nella tabella di Gestione Richieste né in quella di Gestione Iscritti: hanno una pagina propria, **Statistiche › Statistiche Gestione Richieste** (vedi la relativa guida). Gestione Iscritti non ha più statistiche.',
        },
      ],
    },
  ],
}

export default guide
