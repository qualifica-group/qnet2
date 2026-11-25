import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'project-work-orders',
  title: 'Commesse di Progetto',
  summary: 'Le commesse di tipo Progetto, con la chat del team su ogni commessa.',
  sections: [
    {
      id: 'overview',
      title: "Cos'è questa pagina",
      blocks: [
        {
          type: 'paragraph',
          text: 'In **Lavorazioni › Commesse di Progetto** trovi le commesse di tipo **Progetto**. Queste commesse **non compaiono in Commesse**, che mostra solo le commesse di lavorazione. La tabella funziona come quella delle Commesse: stesse colonne, filtri, modifica rapida ed esportazione; aprendo una riga vedi il dettaglio della commessa, descritto nella guida **Commesse**.',
        },
        {
          type: 'note',
          text: "Nelle schede **Commesse** di un Contratto o di un'Anagrafica vedi le commesse di tutti i tipi che puoi vedere, Progetto compreso.",
        },
        {
          type: 'note',
          text: "Nel dettaglio di una commessa di progetto la scheda **Task** si apre sulla vista **Roadmap**, la linea del tempo dei task; con i pulsanti nella barra dei filtri passi a **Lista** o **Board** (vedi **Task: vista Roadmap** nella guida **Commesse**).",
        },
      ],
    },
    {
      id: 'create-without-offer',
      title: 'Nuova commessa di progetto senza offerta',
      blocks: [
        {
          type: 'paragraph',
          text: "Con **Nuova commessa di progetto** in alto nella pagina crei una commessa di progetto che **non nasce da un'offerta**: per esempio un progetto interno. Il pulsante compare a chi ha il permesso di **creare le Commesse**. Le commesse nate da un'offerta si creano come prima, dal Contratto o dall'Anagrafica.",
        },
        {
          type: 'steps',
          items: [
            'Premi **Nuova commessa di progetto**.',
            'Scrivi il **Titolo** e la **Data inizio**.',
            'Scegli il **Cliente**, la **Società** e, se servono, la **Società sede** e la **Sede operativa**.',
            "In **Linee prodotto** scegli almeno una linea (categoria madre, poi categoria), come in Gestione Richieste: la **Funzione aziendale** si ricava dalla categoria scelta e la vedi accanto, senza doverla indicare.",
            "In **Prodotti**, se vuoi, abbini uno o più prodotti, come le righe di un'offerta: puoi scegliere solo prodotti delle categorie delle tue linee, quindi il campo si apre dopo aver scelto una linea. Le **Informazioni aggiuntive** delle categorie compaiono subito più sotto e le compili già prima di salvare.",
            'Se vuoi, aggiungi Responsabili, Partecipanti e un Modello di Task, poi premi **Salva**.',
          ],
        },
        {
          type: 'paragraph',
          text: "Il dettaglio è quello di ogni commessa. Cliente, Società e sedi si modificano dalla sezione **Riferimenti Società e Funzioni Aziendali**, dove sotto la Sede operativa vedi anche la **Funzione aziendale** letta dalle linee; Linee prodotto e Prodotti stanno in una sezione propria, **Linee prodotto e prodotti**, prima dei **Dati contrattuali commessa** (che qui non mostrano né l'importo né le righe d'offerta). Il riquadro **Info cliente** mostra i dati del cliente scelto. Il **Tipo** resta sempre **Progetto**.",
        },
        {
          type: 'list',
          items: [
            "I **Prodotti** abbinati non hanno prezzo né contratto: non si chiedono **proforma** e l'importo resta vuoto. Le **Informazioni aggiuntive** vengono dalle categorie dei prodotti e delle funzioni aziendali.",
            'Costi, ordini di acquisto, fatture passive, task, chat e documenti funzionano come nelle altre commesse.',
            "Con **più linee prodotto** succede come in una commessa normale con righe di categorie diverse: la Funzione aziendale elenca tutte le funzioni delle linee, le **Informazioni aggiuntive** sono quelle di tutte le categorie insieme, lo **Stato lavorazione** segue la regola più specifica che le riguarda e i prodotti possono stare in una qualsiasi delle linee. Se togli una linea, i prodotti di quella categoria vengono tolti insieme.",
            "Chi vede le commesse della propria **sede** la vede tramite la sua **Sede operativa**; chi le vede per **competenza**, tramite le categorie delle sue **Linee prodotto** e dei suoi **Prodotti**.",
            "Nel **Controllo di gestione** conta con la sua società e le sue sedi; i costi si dividono **in parti uguali** fra le sue funzioni aziendali.",
          ],
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi e indirizzo',
      blocks: [
        {
          type: 'paragraph',
          text: "**Commesse di Progetto** è un modulo distinto da **Commesse**, con i suoi permessi di visualizzazione. Nel form dei **Ruoli** lo trovi sotto **Lavorazioni**: **Visualizza elenco** apre la pagina, **Visualizza** apre il dettaglio di una commessa di progetto. Senza questi permessi le commesse di progetto non si vedono da nessuna parte, nemmeno nelle schede di un Contratto o di un'Anagrafica. Tutto il resto (modificare, eliminare, chat, costi, documenti...) segue i permessi delle **Commesse**.",
        },
        {
          type: 'paragraph',
          text: "Una commessa di progetto si apre all'indirizzo **/project-work-orders/…**. Se la apri con l'indirizzo delle Commesse (**/work-orders/…**), per esempio da un vecchio link, la pagina passa da sola a quello giusto.",
        },
      ],
    },
    {
      id: 'unread-column',
      title: 'La colonna Non letti',
      blocks: [
        {
          type: 'paragraph',
          text: 'La colonna **Non letti** mostra, per ogni commessa, quanti messaggi della chat non hai ancora letto. Resta vuota se non ci sono messaggi nuovi o se la chat di quella commessa non è visibile per te.',
        },
      ],
    },
    {
      id: 'chat-access',
      title: 'La chat: chi la vede e cosa si scrive',
      blocks: [
        {
          type: 'paragraph',
          text: "Ogni commessa di progetto ha una **chat** per scambiare messaggi con il team. Non è una scheda del dettaglio: sta nella barra **Chat** in basso a destra, presente in ogni pagina quando hai qualcuno con cui parlare: almeno un progetto in cui sei Responsabile o Partecipante, oppure l'Assistente AI. Se non hai né l'uno né l'altro la barra non compare. L'amministratore può anche spegnere la chat per tutta l'installazione: allora la barra mostra solo l'Assistente AI (se attivo) e la colonna **Non letti** sparisce; gli **Appunti** restano. Aprendola vedi in cima l'**Assistente AI** e poi i **canali**: le commesse di progetto in cui sei Responsabile o Partecipante, con il cliente, l'ultimo messaggio e i non letti, la più recente in alto. La vedono e ci scrivono i **Responsabili** e i **Partecipanti** della commessa e gli **amministratori**. Chi vede la commessa per altri motivi (per esempio perché ha il permesso di vedere tutte le commesse) **non** vede la chat.",
        },
        {
          type: 'list',
          items: [
            'I messaggi sono **solo testo**, fino a **2.000 caratteri**.',
            'I messaggi nuovi compaiono **in tempo reale**, senza ricaricare la pagina.',
            'Se i messaggi sono tanti, **Carica messaggi precedenti** mostra quelli più vecchi.',
            "In cima all'elenco puoi **cercare** un canale per titolo, codice o cliente e scegliere **Non letti** per vedere solo quelli con messaggi nuovi.",
            "Puoi tenere aperte fino a **3** conversazioni affiancate: aprendone una quarta si chiude la più vecchia. Cliccando il titolo la riduci o la riapri, la **X** la chiude e l'icona accanto apre la commessa. Su telefono la finestra aperta occupa tutto lo schermo.",
          ],
        },
        {
          type: 'steps',
          items: [
            'Clicca la barra **Chat** in basso a destra e poi il canale della commessa, oppure, dal dettaglio della commessa, la voce **Chat** in fondo alla riga delle schede (con il pallino dei messaggi non letti): si apre la sua conversazione.',
            'Scrivi nel campo **Scrivi un messaggio**.',
            'Premi **Invio** oppure il pulsante **Invia** (la freccia). Con **Maiusc+Invio** vai a capo.',
          ],
        },
        {
          type: 'note',
          text: 'La chat è separata dalle **Note** della commessa, che restano come prima.',
        },
      ],
    },
    {
      id: 'edit-delete',
      title: 'Modificare e cancellare i messaggi',
      blocks: [
        {
          type: 'paragraph',
          text: "Puoi usare **Modifica** ed **Elimina** sui tuoi messaggi entro **15 minuti** dall'invio. Quando lo modifichi, accanto al messaggio compare **modificato**. Dopo i 15 minuti il messaggio non si cambia più.",
        },
        {
          type: 'paragraph',
          text: 'Prima di cancellare ti viene chiesta una conferma: scegli **Elimina** per procedere o **Annulla** per tenere il messaggio. Un messaggio cancellato non sparisce: resta nella conversazione come **Messaggio eliminato**, senza il testo, così il filo del discorso non si perde.',
        },
        {
          type: 'paragraph',
          text: 'Gli **amministratori** possono modificare o cancellare **qualsiasi** messaggio, anche di altri e senza il limite dei 15 minuti.',
        },
      ],
    },
    {
      id: 'closed-work-orders',
      title: 'Commessa conclusa, annullata o disdetta',
      blocks: [
        {
          type: 'paragraph',
          text: "Quando lo stato della commessa è **Chiusa**, **Annullata** o **Disdetta**, la chat diventa in **sola lettura**: rivedi i messaggi ma non puoi inviarne, modificarli o cancellarli, e vedi l'avviso **Chat in sola lettura: non puoi inviare messaggi.** Vale anche per gli amministratori.",
        },
      ],
    },
    {
      id: 'chat-badge',
      title: 'I numeri dei messaggi non letti',
      blocks: [
        {
          type: 'paragraph',
          text: "Sulla barra **Chat** compare il totale dei messaggi non letti di tutti i tuoi canali, nell'elenco ogni canale ha il suo numero e nel dettaglio della commessa il pallino sulla voce **Chat**: si aggiornano in tempo reale, in qualunque pagina tu sia. Il numero di un canale si **azzera** quando apri la sua conversazione con la pagina in primo piano: i messaggi risultano letti e la colonna **Non letti** si aggiorna di conseguenza. I tuoi messaggi non contano come non letti.",
        },
      ],
    },
    {
      id: 'workspace-notes',
      title: 'La scheda Appunti',
      blocks: [
        {
          type: 'paragraph',
          text: "Ogni commessa di progetto ha anche una scheda **Appunti**, subito dopo la scheda **Note**: una **pagina condivisa dal team**, fatta di blocchi, per tenere insieme testo, elenchi, task e sondaggi. La vedono e la modificano le stesse persone che usano la chat (Responsabili, Partecipanti e amministratori). Non è la scheda **Note** della commessa, che resta com'era.",
        },
        {
          type: 'list',
          items: [
            '**Blocchi di testo**: paragrafi, titoli da H1 a H4, elenchi puntati, numerati e **checklist**, citazioni, divisori, immagini e **date** (badge).',
            '**Tabelle**, **callout** (info, successo, attenzione, errore) e **blocchi comprimibili**: anche i titoli possono essere comprimibili, e chiudendoli nascondi tutto fino al titolo successivo dello stesso livello o superiore. Aprire e chiudere vale solo per te e non si salva.',
            '**Task**: crea un task della commessa direttamente dalla pagina (titolo e scadenza; richiedente e assegnatario sei tu). Il blocco mostra stato, assegnatari e scadenza; cliccandolo si apre il dettaglio del task, dove lo completi o lo modifichi. Se un task non è più visibile per te o è stato eliminato vedi **Task non disponibile**.',
            "**Sondaggi**: una domanda con da 2 a 10 opzioni. Ognuno ha **un voto**, che può cambiare o ritirare cliccando di nuovo l'opzione scelta; i voti sono **nominativi** e li vede chi legge la pagina.",
            "**Come si inserisce e si formatta**: selezionando del testo compare una **barra** con grassetto, corsivo, sottolineato, barrato, evidenziatore, colore, link e il menu **Stili** (paragrafo, titoli, elenchi, citazione, callout); digitando **/** si apre l'elenco di tutti i blocchi; la **barra in fondo** inserisce tabella, elenchi, task e sondaggi.",
            "**Spostare i blocchi**: passando con il mouse su un blocco compare a sinistra la **maniglia**. Trascinala per spostare il blocco, oppure cliccala per **Trasforma in**, **Duplica**, **Sposta su/giù** ed **Elimina**. Su telefono e tablet la maniglia apre solo il menu.",
          ],
        },
        {
          type: 'table',
          headers: ['Scorciatoia', 'Effetto'],
          rows: [
            ['Ctrl+Alt+0', 'Paragrafo'],
            ['Ctrl+Alt+1 … Ctrl+Alt+4', 'Titolo H1 … H4'],
            ['Ctrl+Shift+8', 'Elenco puntato'],
            ['Ctrl+Shift+7', 'Elenco numerato'],
            ['Ctrl+Shift+9', 'Checklist'],
            ['Ctrl+K', 'Link'],
            ['Alt+Shift+Freccia su / giù', 'Sposta il blocco'],
            ['/', 'Menu dei blocchi (a inizio riga)'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Il **salvataggio è automatico**: poco dopo aver smesso di scrivere compare **Salvataggio...** e poi **Salvato**. Se il salvataggio non riesce vedi **Errore, riprova** e il testo non va perso.',
        },
        {
          type: 'warning',
          text: "Non c'è la modifica simultanea: se un collega salva prima di te compare **Modificata da ...: ricarica**. Il salvataggio si ferma; con **Ricarica** vedi la sua versione e le tue modifiche non salvate vanno perse, quindi copiale prima se ti servono.",
        },
        {
          type: 'note',
          text: 'Quando la commessa è **Completata** o **Chiusa** (o se non hai il permesso di modifica) la pagina è in **sola lettura**: niente barre, niente voti.',
        },
      ],
    },
    {
      id: 'no-notifications',
      title: 'Nessuna notifica',
      blocks: [
        {
          type: 'note',
          text: 'I messaggi della chat **non generano notifiche** (nessuna campanella) **né email**. Per sapere se ci sono novità guarda la colonna **Non letti** e i numeri sulla barra **Chat**.',
        },
      ],
    },
  ],
}

export default guide
