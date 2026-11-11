import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'opportunities',
  title: 'Opportunità',
  summary: "L'opportunità è una trattativa commerciale con un'anagrafica, nata da un lead o creata a mano.",
  sections: [
    {
      id: 'create-an-opportunity',
      title: "Creare un'opportunità",
      blocks: [
        {
          type: 'paragraph',
          text: "L'opportunità è una trattativa con un'**Anagrafica**. Nasce da un lead oppure viene creata a mano.",
        },
        {
          type: 'steps',
          items: [
            'Apri **Commerciale › Opportunità**.',
            'Clicca **Nuova opportunità**.',
            'Compila le sezioni (vedi tabella): il modulo ha lo stesso aspetto del dettaglio, con le righe **chiuse**. Apri una riga con la matita, compila il campo e premi **Fatto** per tenerlo (o **Ripristina** per riportarlo com\'era).',
            'Clicca **Salva** (in alto o in fondo): controlla tutti i campi e crea l\'opportunità; gli errori compaiono sotto le righe da correggere. Uscendo senza salvare ti viene chiesta conferma.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi'],
          rows: [
            ['Lead di origine', '**Lead** (facoltativo, precompila e blocca i campi derivati; visibile solo ai ruoli abilitati, di norma il super-admin)'],
            ['Note generali', 'Testo libero'],
            ['Dettagli', '**Titolo** (facoltativo), **Data inizio**, **Data chiusura prevista**, **Valore stimato**, **Probabilità di successo (%)**'],
            ['Anagrafica e contatti', '**Anagrafica** (obbligatoria), **Referente**, **Commerciale**, **Segnalatore** con i **Buoni assegnati**'],
            ['Classificazione', '**Linee prodotto** (almeno una riga: categoria genitore, **Filtro** intermedio facoltativo, categoria prodotto; non compaiono le categorie con **Visibile in** su **Gestione Richieste / Iscritti**), **Prodotti di interesse**'],
            ['Team', '**Supervisore**, **Gestori account**'],
          ],
        },
        {
          type: 'tip',
          text: "Il **Titolo** è proposto in automatico: il codice dell'opportunità seguito dai prodotti delle righe di ricavo delle offerte, non dai prodotti di interesse (per esempio OPP_12 - ISO 9001 + SOA); finché non ci sono prodotti vale solo il codice. Nel dettaglio il campo mostra il titolo attuale: se scrivi un titolo tuo resta quello, anche quando cambiano le offerte; svuota il campo per tornare al titolo automatico.",
        },
        {
          type: 'tip',
          text: "Scegliendo l'**Anagrafica**, **Commerciale**, **Segnalatore**, **Supervisore** e **Gestori account** vengono compilati con quelli dell'anagrafica. Se li hai già inseriti tu e sono diversi, QNet chiede se **Sostituire** i tuoi valori o **Mantenere i tuoi**: nulla di ciò che hai scelto viene cancellato senza conferma.",
        },
        {
          type: 'paragraph',
          text: "Chi inserisci come **Supervisore** o tra i **Gestori account** riceve la notifica **Sei stato inserito come Supervisore** o **Sei stato inserito come Gestore Account** (campanella ed email). Il link apre l'opportunità; chi non ha accesso alle Opportunità riceve la notifica senza link. Chi è anche Gestore account di un'offerta dell'opportunità, per esempio perché viene copiato nell'offerta collegata di un lead convertito o di una categoria con gestori sincronizzati, riceve solo la notifica dell'offerta.",
        },
      ],
    },
    {
      id: 'editing-an-opportunity',
      title: "Modificare un'opportunità",
      blocks: [
        {
          type: 'paragraph',
          text: "Le schede di Opportunità, Offerte, Contratti e Commesse hanno le stesse sezioni nello stesso posto. Qui trovi **Dati opportunità** e **Contesto** (Linee prodotto, Prodotti di interesse), poi **Anagrafica e contatti** e **Buoni**, infine **Team**. La sezione Buoni resta visibile anche vuota (\"Nessun buono\").",
        },
        {
          type: 'paragraph',
          text: "Non esiste una pagina di modifica separata: l'opportunità si modifica **direttamente dal suo dettaglio**, un campo alla volta.",
        },
        {
          type: 'steps',
          items: [
            "Apri l'opportunità dall'elenco.",
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            "Premi **Salva** (o Invio nei campi di testo e data) per salvare solo quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) per chiuderlo lasciandolo com'era, senza salvare.",
          ],
        },
        {
          type: 'tip',
          text: "Le **Note generali** stanno nel riquadro giallo in cima al dettaglio, come in Gestione Richieste: se sono vuote il riquadro invita a scriverle; con la matita (o un clic sul testo) le scrivi direttamente nel riquadro.",
        },
        {
          type: 'note',
          text: "Alcuni campi si portano dietro altri valori nello stesso salvataggio: cambiando l'**Anagrafica** il Referente prende il referente principale della nuova anagrafica (resta vuoto se non ne ha; vale anche scegliendo un **Lead**, quando l'anagrafica cambia o il Referente è vuoto) e Commerciale, Segnalatore, Supervisore e Gestori account vengono proposti da quelli dell'anagrafica (con conferma se sono diversi); cambiando le **Righe di classificazione** i Prodotti di interesse non più coperti vengono tolti; i **Buoni assegnati** si modificano dal campo **Segnalatore**, di cui sono il premio.",
        },
        {
          type: 'note',
          text: "Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure è ereditato e bloccato dal lead di origine (Anagrafica), oppure cambiarlo modificherebbe un campo che non puoi toccare. Il **Lead di origine** non si cambia e, se il tuo ruolo non lo vede, non compare né in creazione né nel dettaglio; lo **Stato** è calcolato dalle offerte.",
        },
      ],
    },
    {
      id: 'people-filters',
      title: "Filtrare per persona",
      blocks: [
        {
          type: 'paragraph',
          text: "Nel pannello **Filtri avanzati** (pulsante nella barra sopra l'elenco) i campi **Supervisore** e **Gestori account** permettono di scegliere una o più persone: compaiono le opportunità in cui almeno una di loro ha quel ruolo. Se compili entrambi i campi, l'opportunità deve soddisfarli tutti e due.",
        },
      ],
    },
    {
      id: 'list-filters',
      title: "Filtri predefiniti e categoria",
      blocks: [
        {
          type: 'paragraph',
          text: "All'apertura la pagina **Opportunità** mostra solo il tuo lavoro. Nel pannello **Filtri avanzati** due campi sono già compilati, come **Assegnazione** nei Task: **Assegnazione** su **Supervisore** e **Gestore account** (le opportunità dove hai almeno uno dei due ruoli) e **Categoria madre** sulle categorie madri di tua competenza (con tutte le loro sottocategorie). Valgono insieme. Per allargare l'elenco scegli **Tutti i visibili** in **Assegnazione** e, in **Categoria madre**, altre categorie oppure **Tutte** (la trovi anche scrivendo \"tutti\" nella ricerca), poi premi **Applica**: la scelta resta salvata. Il campo **Cerca** trova le opportunità anche per nome del cliente (l'anagrafica): basta una parte del nome, per esempio \"napoli\".",
        },
        {
          type: 'paragraph',
          text: "Se non hai categorie di competenza, oppure le hai tutte, **Categoria madre** parte da **Tutte**. Se il tuo profilo ti fa vedere solo i record dove hai un ruolo, la voce **Tutti i visibili** non compare: non aggiungerebbe nulla. I filtri non allargano ciò che il tuo profilo può vedere.",
        },
        {
          type: 'paragraph',
          text: "Nello stesso pannello c'è anche **Categoria prodotto** (solo quella categoria). Rientra in una categoria un'opportunità che la ha tra le **linee di prodotto** dell'opportunità.",
        },
        {
          type: 'paragraph',
          text: "Le categorie con **Visibile in** su **Gestione Richieste / Iscritti** non fanno parte di questa pagina: non compaiono in **Categoria madre** e **Categoria prodotto**, e le opportunità che hanno **solo** categorie di questo tipo non sono nell'elenco né nelle statistiche (il dettaglio resta apribile, per esempio da un contratto). Se un'opportunità ha anche una categoria usata qui resta visibile.",
        },
        {
          type: 'paragraph',
          text: "Scegliendo in **Categoria madre** una categoria attuale (per esempio ISO) entrano nell'elenco anche le opportunità delle categorie vecchie collegate (per esempio [OLD] ISO), che nel filtro non compaiono come voci a parte. Per vedere solo quelle di una categoria vecchia o solo di una nuova usa **Categoria prodotto**.",
        },
        {
          type: 'note',
          text: "**Assegnazione** e **Categoria madre** precompilate valgono solo nella pagina **Opportunità**: non compaiono dentro una scheda e nella sezione Opportunità di un'anagrafica. L'esportazione segue i filtri attivi. I campi flessibili si leggono e si modificano dal dettaglio.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Modifica rapida dall'elenco",
      blocks: [
        {
          type: 'paragraph',
          text: "Un clic su una cella modificabile (Nome, Anagrafica, Referente, Commerciale, Supervisore, Gestori account, Categoria prodotto, Prodotti di interesse, Valore stimato, Probabilità, Data inizio, Data chiusura prevista) la modifica **direttamente nell'elenco**, con le **stesse regole del dettaglio**: un valore non valido o un campo che non puoi modificare viene rifiutato con un messaggio e la cella torna al valore di prima.",
        },
        {
          type: 'note',
          text: "Cambiando l'**Anagrafica** dall'elenco il Referente prende il referente principale della nuova anagrafica (vuoto se non ne ha; se non puoi modificare il Referente e la riga ne ha già uno, il cambio di anagrafica non è ammesso) e i ruoli **vuoti** (Commerciale, Segnalatore, Supervisore, Gestori account) prendono quelli della nuova anagrafica; i ruoli già compilati restano com'erano (dall'elenco non c'è la richiesta di conferma). Il Referente si sceglie tra quelli dell'anagrafica della riga; un Nome vuoto torna al titolo automatico.",
        },
        {
          type: 'paragraph',
          text: "Togliendo o aggiungendo persone nei **Gestori account** gli altri restano nella loro posizione; i nuovi occupano il primo posto libero. Restano in sola lettura Sede operativa, Stato (calcolato dalle offerte), Funzione aziendale (deriva dalla categoria) e Creato il.",
        },
      ],
    },
    {
      id: 'from-a-lead',
      title: 'Ereditarietà da un lead',
      blocks: [
        {
          type: 'paragraph',
          text: "Se l'opportunità nasce da un lead, alcuni campi sono ereditati e bloccati: un avviso in alto lo segnala. I **Gestori account** sono sincronizzati con l'offerta collegata. L'opportunità non ha più la **Fonte**: è un campo dell'offerta e, creando un'offerta da un'opportunità nata da un lead, si precompila con la Fonte del lead.",
        },
        {
          type: 'warning',
          text: "Un'anagrafica può avere una sola opportunità aperta alla volta. Se ne esiste già una, QNet propone **Aggiungi l'offerta a questa opportunità**. Fanno eccezione le categorie con la regola **Più opportunità aperte per anagrafica**: le loro opportunità non bloccano le altre e si creano anche se l'anagrafica ne ha già una aperta, e con la modalità Multipla l'offerta può avere più righe. Non bloccano nemmeno le opportunità aperte con tutte le linee su categorie storiche (il catalogo migrato dal vecchio gestionale, oggi con il prefisso [OLD]).",
        },
      ],
    },
    {
      id: 'status-and-constraints',
      title: 'Stato e vincoli',
      blocks: [
        {
          type: 'paragraph',
          text: 'Lo **Stato** dell\'opportunità non si imposta a mano: è calcolato dagli stati delle sue offerte (per esempio "2 stati" se sono diversi). Nella scheda trovi l\'elenco delle **Offerte** e il pulsante **Nuova offerta**.',
        },
        {
          type: 'warning',
          text: "Alcune categorie prodotto ammettono una sola offerta per opportunità.",
        },
      ],
    },
    {
      id: 'visibility',
      title: "Quali opportunità vedi",
      blocks: [
        { type: 'paragraph', text: "Cosa vedi dipende dal tuo ruolo. Serve sempre il permesso **Visualizza**; poi:" },
        { type: 'list', items: ["Con **Visualizza** vedi solo le opportunità in cui sei **Supervisore** o tra i **Gestori account**.", "Con **Visualizza per competenza** vedi anche quelle con almeno un prodotto in una categoria del tuo ambito di assegnazione, in qualunque sede. Con **Ambito su tutte le categorie** le vedi tutte.", "Con **Visualizza tutti** le vedi tutte."] },
        { type: 'paragraph', text: "Il filtro vale per elenco, scheda, filtri, esportazioni, statistiche e dashboard, selettori e documenti. Aprire il link di un'opportunità che non puoi vedere mostra un errore di accesso. Vedere un'opportunità non permette di modificarla o eliminarla: servono i permessi **Modifica** ed **Elimina**, e si modifica solo ciò che si vede." },
        { type: 'note', text: "Se crei un'opportunità senza far parte del suo team, non la vedrai più a meno di avere **Visualizza tutti** o **Visualizza per competenza** (con la categoria giusta nel tuo ambito). Nel riquadro **Offerte** di un'opportunità compaiono solo le offerte che puoi vedere." },
      ],
    },
  ],
}

export default guide
