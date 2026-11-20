import type { HelpGuide } from '../../types'
import { recurringEmailSections } from './parts/work-orders-recurring-emails'
import { costsSections } from './parts/work-orders-costs'
import { taskSections } from './parts/work-orders-tasks'

const guide: HelpGuide = {
  key: 'work-orders',
  title: 'Commesse',
  summary: 'Il lavoro da svolgere su un contratto vinto.',
  sections: [
    {
      id: 'editing-a-work-order',
      title: 'Modificare una commessa',
      blocks: [
        {
          type: 'paragraph',
          text: "Il dettaglio della commessa è organizzato a schede come nel vecchio QNet. Sotto l'intestazione (titolo con sotto il nome dell'azienda cliente, stato, indicatori) c'è una riga di schede: **Info**, **Task**, **Documenti** (Documenti commessa e Documenti anagrafica), **Amministrazione** (Costi, Proforma attiva e Proforma passiva), **Note**, poi **Chat**, **Email** e **Attività** dove previste. Ogni scheda compare solo se hai il permesso di vederla; quando un gruppo ha una sola voce disponibile, la scheda mostra direttamente quella. Le schede si comportano come pagine: entrando nella commessa è attiva **Info**; ricaricando la pagina resti sulla scheda in cui eri, e un salvataggio dentro una scheda non riporta su Info. In fondo alla riga, un po' staccato, c'è il **titolo della commessa**: cliccandolo torni su Info.",
        },
        {
          type: 'paragraph',
          text: "La scheda **Info** è divisa in due colonne come nel vecchio QNet. A sinistra: **Info cliente** (**Azienda** con il link all'anagrafica, **Sede legale** con P.IVA e codice fiscale, **Tag**, **Email/PEC**, **Telefono**, **Referente 1** cioè il referente principale dell'anagrafica, **Referente 2** cioè il referente secondario dell'anagrafica (vuoto se non è indicato), **Commerciale**, **Partner commerciale**, **Settore** e **Commenti** cioè le note generali dell'anagrafica; email, PEC e telefoni si toccano per scrivere o chiamare), poi **Dati contrattuali commessa** (**Importo** cioè l'imponibile totale dei prodotti, visibile a chi vede i dati contrattuali, Data inizio, il link al Contratto, le **Linee prodotto**, poi ogni prodotto della commessa con i suoi campi di pagamento e i totali Totale Consulenza, Totale Ente e Totale Ricavi), **Stato lavorazione** e **Team** (Supervisori e Partecipanti). A destra: **Riferimenti Società e Funzioni Aziendali** (Società, Sede, Sede operativa, Funzione aziendale: le funzioni aziendali delle Linee prodotto), **Data richiamo**, **Descrizione commessa** (Commessa n., Tipo commessa e la descrizione) e sotto i campi flessibili (**Informazioni aggiuntive**), se le righe prodotto ne prevedono. Info cliente viene dall'anagrafica e si modifica lì; Riferimenti e Linee prodotto vengono dall'offerta, in sola lettura; in una commessa con righe di più offerte non compaiono. Indirizzo, P.IVA, codice fiscale e recapiti si vedono solo se hai il permesso di vedere anagrafiche e referenti. Nella commessa non ci sono le sezioni Contesto (Fonte), Buoni, Documento e pagamento né il Modello di Task.",
        },
        {
          type: 'note',
          text: "Lo **Stato pagamento** non è un campo della commessa ma di **ogni prodotto**: lo trovi in **Dati contrattuali commessa**, sotto il prodotto. Non è più un campo flessibile (non c'è più fra le Informazioni aggiuntive né fra i campi flessibili delle categorie): i valori che c'erano sono stati copiati sui prodotti della commessa che non avevano uno stato.",
        },
        {
          type: 'paragraph',
          text: 'Non esiste una pagina di modifica separata: la commessa si modifica **direttamente dal suo dettaglio**, un campo alla volta, comprese le **Informazioni aggiuntive** (ogni campo flessibile ha la sua riga). Una sezione legata a un campo sì/no compare solo con il campo attivo: spegnendolo, i valori della sezione vengono svuotati. Nel dettaglio le Informazioni aggiuntive restano sempre aperte. Nella creazione, invece, cliccando il titolo **Informazioni aggiuntive** la sezione si chiude o si riapre: la scelta resta salvata nel browser e vale per tutte le commesse.',
        },
        {
          type: 'steps',
          items: [
            'Apri la commessa dall\'elenco.',
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            'Premi **Salva** (o Invio nei campi di testo e data) per salvare solo quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) per chiuderlo lasciandolo com\'era, senza salvare.',
          ],
        },
        {
          type: 'tip',
          text: "Le **Note commessa** stanno nel riquadro giallo in cima al dettaglio, come le Note generali in Gestione Richieste: se sono vuote il riquadro invita a scriverle; con la matita (o un clic sul testo) le scrivi direttamente nel riquadro.",
        },
        {
          type: 'note',
          text: "Chi viene **aggiunto** come **Responsabile** o **Partecipante**, alla creazione della commessa o dopo (anche dall'elenco), riceve una **notifica** nella campanella e per email con il link alla commessa. Chi c'era già, chi cambia solo posizione tra i Partecipanti e chi viene tolto non riceve nulla; nessuno riceve notifiche per le modifiche che fa lui stesso. Chi è sia Responsabile sia Partecipante riceve una notifica per ciascun ruolo. Se la commessa nasce da un **Modello di Task**, ogni Responsabile riceve anche **una sola** notifica con il numero di attività che gli sono state assegnate (es. \"ti ha assegnato le 21 attività della commessa\"), non una per ogni attività.",
        },
        {
          type: 'note',
          text: "Quando aggiungi un **Responsabile** a una commessa (alla creazione, dal dettaglio o dall'elenco), il campo **Data di assegnazione** delle Informazioni aggiuntive prende la data di oggi, anche se ne aveva già una. Se nello stesso salvataggio scrivi tu la data, resta la tua. Togliere un Responsabile o aggiungere solo Partecipanti non cambia la data; le commesse le cui righe non prevedono il campo non cambiano.",
        },
        {
          type: 'note',
          text: 'Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure si sceglie solo in creazione (Commessa n., Offerta collegata). Il **Modello di Task** si cambia dalla scheda **Task** (vedi **Task: caricare un modello**). Contratto (codice e titolo) e le sezioni prese dall\'offerta derivano dall\'offerta, come la Scadenza contratto mostrata tra gli indicatori in alto (si modifica sul contratto); Stato e Avanzamento derivano dalle registrazioni di avanzamento (vedi la sezione dedicata).',
        },
        {
          type: 'note',
          text: 'Le **Righe prodotto** si scelgono alla creazione della commessa e poi non si modificano: nel dettaglio le vedi nella sezione **Dati contrattuali**. Le Informazioni aggiuntive che le righe portano con sé si compilano in creazione (quelle obbligatorie servono per salvare).',
        },
      ],
    },
    {
      id: 'row-actions',
      title: "Azioni della tabella",
      blocks: [
        {
          type: 'paragraph',
          text: "Nella colonna **Azioni** dell'elenco Commesse (anche nelle sezioni Commesse del Contratto e dell'Anagrafica) trovi le azioni del vecchio QNet: l'**occhio** apre la commessa, **Responsabili e Partecipanti** apre un popup con le persone della commessa, **Info** apre la commessa sulla scheda Info. Il menu con i tre puntini apre la commessa direttamente sulla scheda **Storico Note**, **Task** o **Documenti**; in fondo al menu c'è **Elimina** (vedi **Eliminare una commessa**).",
        },
        {
          type: 'paragraph',
          text: "Nel popup **Responsabili e Partecipanti** aggiungi o togli Responsabili e Partecipanti con la matita, con le stesse regole del dettaglio. In fondo, se puoi richiedere proforma, c'è **Richiedi proforma**; **Chiudi** chiude il popup.",
        },
        {
          type: 'note',
          text: "Task e Documenti compaiono nel menu solo se puoi vedere quelle schede, Elimina solo se hai il permesso di eliminare le commesse. Chiusura forzata, Avanzamento, Richiedi aggiornamento, Richiedi proforma e Attività non sono più azioni di riga: le trovi nel dettaglio della commessa. L'avanzamento si apre anche dalla cella **Avanzamento** della tabella.",
        },
        {
          type: 'paragraph',
          text: "Se puoi richiedere proforma, la tabella ha anche la colonna **€**, come nel vecchio QNet: l'icona dell'euro è **grigia** se la commessa non ha richieste proforma, **blu** se c'è una richiesta in attesa di emissione, **gialla** se le proforma richieste sono state emesse. Passando col mouse sull'icona leggi lo stato. Cliccando l'icona grigia o blu si apre la finestra **Richiesta emissione Proforma**; quella gialla non è cliccabile. La colonna si può ordinare (non richiesta, richiesta, emessa) e filtrare per **Non richiesta**, **Richiesta**, **Emessa**.",
        },
        {
          type: 'paragraph',
          text: "La colonna **Stato pagamento** mostra gli stati di pagamento dei prodotti della commessa raggruppati per tipologia: il badge **Consulenza** o **Ente** e, accanto, gli stati dei prodotti di quella tipologia (per esempio **Consulenza** Saldato, Nessuno stato · **Ente** Da pagare). Un prodotto senza stato conta come **Nessuno stato**; più prodotti con lo stesso stato lo mostrano una volta. Passando col mouse sulla cella vedi ogni prodotto, con codice e nome, e il suo stato. La colonna non si ordina. Il filtro elenca le coppie tipologia e stato presenti, per esempio **Ente: Da pagare** o **Consulenza: Nessuno stato**: tiene le commesse con almeno un prodotto in una delle coppie scelte, e la ricerca del filtro trova anche solo lo stato o la tipologia.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Modifica rapida dall'elenco",
      blocks: [
        {
          type: 'paragraph',
          text: "Un clic su una cella modificabile (Titolo, Tipo commessa, Data richiamo, Data inizio, Responsabili, Partecipanti) la modifica **direttamente nell'elenco**, con le **stesse regole del dettaglio**: per esempio il Titolo e la Data inizio sono obbligatori, mentre Responsabili e Partecipanti si possono anche togliere tutti; un valore non valido viene rifiutato e la cella torna al valore di prima.",
        },
        {
          type: 'note',
          text: "La **Chiusura forzata** si fa dal dettaglio della commessa (chiede il motivo), non da una cella. La colonna **Anagrafica** mostra il cliente della commessa, ripreso dall'offerta collegata: si può filtrare e ordinare ma non modificare. Lo stesso vale per la colonna **Scadenza contratto**, la data di scadenza del contratto da cui nasce la commessa (filtro per data, vuota se il contratto non ha una scadenza). La colonna **Periodo** indica in quale periodo del contratto è stata programmata la commessa: **Iniziale** o **Rinnovo N** (vedi la guida Contratti, Rinnovare il contratto). Restano in sola lettura Commessa n., Contratto n., Offerta collegata, Anagrafica, Scadenza contratto, Periodo, Stato, Avanzamento, Creata il e Aggiornata il.",
        },
      ],
    },
    {
      id: 'people-filters',
      title: "Filtrare per persona",
      blocks: [
        {
          type: 'paragraph',
          text: "Nel pannello **Filtri avanzati** (pulsante nella barra sopra l'elenco) i campi **Responsabili** e **Partecipanti** permettono di scegliere una o più persone: compaiono le commesse in cui almeno una di loro ha quel ruolo. Se compili entrambi i campi, la commessa deve soddisfarli tutti e due. Le colonne **Responsabili** e **Partecipanti** dell'elenco hanno anche il proprio filtro per nome, con la voce **(Vuoti)** per le commesse senza nessuno in quel ruolo.",
        },
      ],
    },
    {
      id: 'work-phase-tabs',
      title: "Fasi di lavorazione",
      blocks: [
        {
          type: 'paragraph',
          text: "In alto a destra nella pagina **Commesse** trovi i tab **Tutte**, **Da pianificare**, **In lavorazione**, **Concluse**, **Annullate** e **Disdette**. La fase è lo **Stato** della commessa (colonna **Stato** della tabella e badge in alto nel dettaglio), è distinta dallo **Stato lavorazione** e dall'avanzamento, e si muove da sola come nel vecchio QNet: una commessa nuova è **Da pianificare**; passa **In lavorazione** quando le aggiungi un **Responsabile**; con la chiusura forzata va in **Concluse** (stato di chiusura positivo) o **Annullate** (negativo); se la riapri torna **In lavorazione**, o **Da pianificare** se non ha Responsabili. Dal contratto: **Annullato** porta tutte le sue commesse in **Annullate**; **Disdetto** porta in **Disdette** solo quelle da pianificare o in lavorazione, e riattivando il contratto tornano dov'erano. **Tutte** toglie il filtro. Accanto a ogni tab, tranne **Tutte**, c'è il numero di commesse (solo se diverso da zero): tiene conto di ricerca e filtri attivi, quindi corrisponde a quello che vedi aprendo il tab. Il tab si combina con gli altri filtri, compresa la **Categoria madre**: se non vedi una commessa, controlla anche quelli.",
        },
      ],
    },
    {
      id: 'list-filters',
      title: "Filtri predefiniti, categoria e anagrafica",
      blocks: [
        {
          type: 'paragraph',
          text: "All'apertura la pagina **Commesse** mostra tutti i record che il tuo profilo ti permette di vedere, senza un filtro **Assegnazione**: con il permesso di vedere tutto vedi tutto, altrimenti vedi le commesse dove sei **Responsabile** o **Partecipante** (e quelle della tua sede o della tua competenza, se il profilo lo prevede). Nel pannello **Filtri avanzati** la **Categoria madre** parte vuota, e vuota vale per tutte le categorie madri in cui sei abilitato: se sei abilitato a tutte vedi le commesse di ogni categoria, altrimenti le commesse delle tue categorie (con tutte le loro sottocategorie) più quelle, di qualunque categoria, dove sei **Responsabile** o **Partecipante**: una commessa che ti assegnano fuori dalla tua competenza resta in elenco. Aprendo l'elenco del campo trovi solo le categorie madri che puoi scegliere: tutte se sei abilitato a tutto, altrimenti solo le tue. Scegline una o più e premi **Applica** per restringere: vedi solo le commesse di quelle categorie (anche quelle dove sei Responsabile o Partecipante di altre categorie escono dall'elenco) e la scelta resta salvata. Il campo **Cerca** trova le commesse anche per nome del cliente (l'anagrafica): basta una parte del nome, per esempio \"napoli\". Digitando solo numeri trova anche la commessa con quell'**ID** esatto (la colonna nascosta **ID**), oltre a quelle che hanno il numero nel codice; l'id del vecchio gestionale non viene cercato.",
        },
        {
          type: 'paragraph',
          text: "Con il permesso di vedere tutto (per esempio il ruolo admin) sei abilitato a tutte le categorie madri, qualunque sia la tua competenza. Altrimenti le categorie in cui sei abilitato sono le categorie di competenza del tuo profilo (l'ambito di assegnazione); se non hai categorie di competenza **Categoria madre** non limita nulla. I filtri non allargano mai ciò che il tuo profilo può vedere.",
        },
        {
          type: 'paragraph',
          text: "Nello stesso pannello puoi scegliere una o più voci anche in **Categoria prodotto** (solo quella categoria) e **Anagrafica** (il cliente). I filtri non allargano ciò che puoi vedere: restano valide le regole di visibilità del tuo profilo. Una commessa rientra in una categoria se la categoria è tra i prodotti delle **righe di ricavo delle offerte da cui nasce**: l'offerta collegata e, per una commessa multi-offerta, le offerte delle righe che contiene. Le righe di costo non contano. Per una commessa multi-offerta vale anche il cliente di ciascuna offerta.",
        },
        {
          type: 'paragraph',
          text: "Le colonne dei campi flessibili compaiono in fondo all'elenco quando il filtro di categoria indica una sola categoria: una sola voce in **Categoria prodotto** oppure, se quella è vuota, una sola categoria madre attuale in **Categoria madre** (per esempio ISO). Scegliendo una categoria madre attuale entrano nell'elenco anche i record delle categorie vecchie collegate (per esempio [OLD] ISO), che nel filtro non compaiono come voci a parte, e le colonne sono prima quelle della categoria attuale, poi quelle delle vecchie. Con due categorie madri attuali diverse vedi i record di entrambe ma nessuna colonna; lo stesso con la **Categoria madre** vuota. Per vedere solo i record di una categoria vecchia o solo di una nuova usa **Categoria prodotto**. Un campo con lo stesso nome in più categorie può comparire due volte. Sono i campi della commessa: si ordinano, si filtrano e si esportano; se hai il permesso di modificare le commesse si modificano nella cella, tranne quando il campo non riguarda quella commessa (la cella resta in sola lettura). I valori restano leggibili anche nel dettaglio. Le **Verifiche ispettive** compaiono come colonna **Data verifica ispettiva**: la data della verifica segnata come attiva, vuota se nessuna riga è attiva; le verifiche si gestiscono nel dettaglio della commessa.",
        },
        {
          type: 'note',
          text: "Il limite alle tue categorie con la **Categoria madre** vuota vale solo nella pagina **Commesse**: in **Commesse di Progetto** il campo vuoto non filtra (il suo elenco mostra comunque solo le categorie madri in cui sei abilitato), e nelle sezioni **Commesse** di un contratto o di un'anagrafica il campo non compare. L'esportazione segue i filtri attivi.",
        },
      ],
    },
    {
      id: 'force-close',
      title: 'Chiusura forzata e riapertura',
      blocks: [
        {
          type: 'paragraph',
          text: 'La chiusura forzata è un\'**azione**, non un campo: la trovi in alto nel dettaglio della commessa. Compare solo se puoi modificare la commessa.',
        },
        {
          type: 'steps',
          items: [
            'Premi **Chiusura forzata**.',
            'Scrivi il **motivo** (obbligatorio). Se la commessa ha task ancora aperti, il dialog ti avvisa quanti verranno chiusi con esito negativo.',
            'Scegli lo **Stato di chiusura** (obbligatorio): l\'elenco mostra gli stati **Chiusa con esito positivo** e **Chiusa con esito negativo** del set della commessa ed è già impostato su quello negativo principale. Con uno stato positivo l\'avanzamento della commessa sale al **100%** (viene aggiunta una registrazione con data di oggi, se non era già al 100%).',
            'Premi **Chiudi commessa**: lo stato diventa Chiusa, lo Stato lavorazione diventa lo stato di chiusura scelto e il motivo compare tra i dati della commessa.',
          ],
        },
        {
          type: 'note',
          text: 'Su una commessa chiusa forzatamente l\'azione diventa **Riapri**: dopo la conferma la chiusura viene annullata e il motivo cancellato; se lo Stato lavorazione è uno stato **Chiusa con esito negativo** torna su **Aperta**. I task chiusi dalla chiusura forzata restano chiusi.',
        },
      ],
    },
    {
      id: 'progress',
      title: 'Avanzamento commessa',
      blocks: [
        {
          type: 'paragraph',
          text: "L'avanzamento si registra a mano ed è la **somma** delle percentuali di tutte le registrazioni (nel popup, la colonna Percentuale è l'incremento di ogni riga). La colonna **Avanzamento** della tabella e l'indicatore in alto nel dettaglio mostrano il totale e la data dell'ultima registrazione; senza registrazioni la cella della tabella mostra un'icona **+** e l'avanzamento è 0%.",
        },
        {
          type: 'steps',
          items: [
            "Apri **Lista avanzamenti commessa**: clicca la cella **Avanzamento** della tabella, oppure premi **Avanzamento commessa** in alto nel dettaglio della commessa (accanto a Chiusura forzata).",
            'Il campo **Totale % raggiunto** mostra la somma già registrata. Scrivi in **% Stato attuale** il nuovo totale (da 1 a 100): deve essere maggiore del totale raggiunto. Indica la **Data** (obbligatoria).',
            'Premi **Conferma**: la registrazione viene salvata (come differenza rispetto al totale) e il popup si chiude.',
            "Per eliminare una riga premi il cestino nella colonna Azioni, poi conferma con il cestino o annulla con la X: il totale scende dell'incremento di quella riga.",
          ],
        },
        {
          type: 'note',
          text: "Registri ed elimini le registrazioni se puoi modificare la commessa, altrimenti vedi solo l'elenco. L'avanzamento non cambia lo stato della commessa (vedi **Fasi di lavorazione**), e nemmeno lo stato dei task; la percentuale dei task resta visibile nel task board.",
        },
      ],
    },
    {
      id: 'request-update',
      title: 'Richiesta di aggiornamento',
      blocks: [
        {
          type: 'paragraph',
          text: 'Con **Richiedi aggiornamento** chiedi notizie sulla commessa: i destinatari ricevono una mail e una notifica con il tuo messaggio e il link alla commessa. Il pulsante è in alto nel dettaglio della commessa; compare solo se hai il permesso **Richiedere aggiornamenti** e solo su una commessa **Da pianificare** o **In lavorazione** (non su una Conclusa, Annullata o Disdetta). La commessa non viene modificata.',
        },
        {
          type: 'table',
          headers: ['Destinatari', 'Chi riceve la richiesta'],
          rows: [
            ['Partecipanti', 'Tutti i partecipanti; i responsabili ricevono comunque una copia, contrassegnata **In copia**.'],
            ['Responsabili', 'Tutti i responsabili della commessa.'],
            ['Partecipanti e responsabili', 'Entrambi i gruppi, come destinatari diretti.'],
          ],
        },
        {
          type: 'note',
          text: 'Il messaggio è **obbligatorio** (da 3 a 2000 caratteri). Un gruppo senza persone non si può scegliere; il numero accanto a ogni gruppo indica quante persone lo ricevono.',
        },
      ],
    },
    {
      id: 'processing-status',
      title: 'Stato lavorazione',
      blocks: [
        {
          type: 'paragraph',
          text: "Oltre allo **Stato** (la fase: Da pianificare, In lavorazione, Conclusa, Annullata, Disdetta, vedi **Fasi di lavorazione**), ogni commessa ha il campo **Stato lavorazione**: lo stato di avanzamento operativo scelto da te. Lo trovi nel dettaglio e come colonna della tabella, con badge colorato e nome.",
        },
        {
          type: 'paragraph',
          text: "Le opzioni arrivano dal **set di stati** che corrisponde alla commessa per funzione aziendale e categoria delle sue righe programmate (vedi **Configuratore Stati Commessa**); senza corrispondenza si usano gli stati di default. Una nuova commessa parte da **Aperta**. Nel dettaglio e nella tabella puoi scegliere solo gli stati del suo set, se puoi modificare il campo.",
        },
        {
          type: 'note',
          text: "Scegliendo a mano uno **Stato lavorazione** con una **% avanzamento** maggiore di zero, l'avanzamento della commessa sale a quella percentuale (viene aggiunta una registrazione con data di oggi). Se la percentuale dello stato non supera l'avanzamento già raggiunto il cambio non è accettato e il campo mostra l'errore; gli stati senza percentuale cambiano solo lo stato. Lo stesso avviene in automatico quando si chiude con esito positivo un task creato da un modello che ha la riga collegata a uno stato (vedi **Modelli di Task**): in quel caso lo stato viene applicato anche se è precedente a quello attuale. Su una commessa chiusa forzatamente l'avanzamento non cambia. Se i dati della commessa cambiano e il set applicabile è diverso, lo stato passa a quello equivalente (Aperta, Chiusa con esito positivo, Chiusa con esito negativo, anche per gli altri stati di chiusura dello stesso gruppo) oppure ad Aperta. La chiusura forzata porta lo Stato lavorazione sullo **Stato di chiusura** scelto (uno stato **Chiusa con esito positivo**, che porta l'avanzamento al 100%, o **Chiusa con esito negativo**); riaprendo la commessa torna su **Aperta** se è ancora su uno stato negativo.",
        },
      ],
    },
    {
      id: 'creating-a-work-order',
      title: 'Creare una commessa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo di creazione ha lo stesso aspetto del dettaglio: le stesse sezioni e righe, **chiuse**. Apri una riga con la matita, compila il campo e premi **Fatto** per tenerlo (o **Ripristina** per riportarlo com\'era). Il campo **Commessa n.** è già proposto. Il numero proposto è indicativo: se lo lasci così e intanto un altro utente lo usa, al salvataggio ricevi il primo numero libero.',
        },
        {
          type: 'note',
          text: 'Nella sezione **Offerta e righe prodotto** scegli prima l\'offerta, poi le sue righe: le Informazioni aggiuntive compaiono in fondo appena le righe le prevedono. **Salva** in alto (o in fondo) controlla tutti i campi e crea la commessa; gli errori compaiono sotto le righe da correggere. Uscendo senza salvare ti viene chiesta conferma.',
        },
      ],
    },
    {
      id: 'automatic-title',
      title: 'Titolo automatico',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il **Titolo** della commessa è facoltativo. Se lo lasci vuoto, la commessa prende il titolo automatico **codice - prodotti delle sue righe** (es. COM-0042 - ISO 9001 + SOA), che segue le righe quando cambiano.',
        },
        {
          type: 'note',
          text: 'Se scrivi un titolo tuo, resta il tuo e non viene più ricalcolato. Per tornare all\'automatico **svuota il campo** e salva. Le commesse già esistenti mantengono il titolo che hanno. Lo stesso vale per il dialogo **Programma** del contratto.',
        },
      ],
    },
    ...taskSections,
    ...costsSections,
    {
      id: 'contract-data',
      title: 'Dati contrattuali commessa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella scheda **Info** del dettaglio Commessa, nella colonna di sinistra, la sezione **Dati contrattuali commessa** mostra **ogni prodotto della commessa** uno sotto l\'altro, con gli stessi campi del vecchio QNet. Il nome del prodotto è un **link** al prodotto.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Significato'],
          rows: [
            ['Titolo', 'Codice e nome del prodotto; in una commessa con più offerte anche il cliente della riga.'],
            ['Importo', 'L\'imponibile della riga, come salvato sull\'offerta. Il tooltip mostra il calcolo, ad esempio "2 × 500,00 = 1.000,00".'],
            ['Stato Pagamento', 'Accanto alla dicitura c\'è il badge della tipologia del prodotto (**Consulenza** o **Ente**), così sai di quale pagamento si tratta. Il valore è lo stato di pagamento del prodotto (badge del colore dello stato), oppure **Nessuno stato**. Con il permesso di gestire i pagamenti accanto c\'è la matita.'],
            ['Documento', 'La proforma o fattura della commessa che fattura il prodotto, con numero e stato del pagamento (Pagata, Non scaduta, Scaduta, Gravemente scaduta); se ce ne sono più, la più recente. Se puoi vedere le fatture, il **numero** è un link che apre il documento in un pannello laterale. **Nessuna proforma emessa** se il prodotto non è ancora stato fatturato.'],
            ['Accordo sui Pagamenti', 'Il testo dell\'accordo sui pagamenti del prodotto.'],
            ['Insoluti', '**Sì** se il prodotto ha insoluti, altrimenti **No**.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Chi non ha il permesso di vedere i **costi** o i **dati contrattuali** della commessa vede per ogni prodotto solo **Titolo** e **Stato Pagamento** (con il badge Consulenza o Ente), senza importi, documento, accordo o insoluti, e non può modificare i pagamenti. Sotto i prodotti ci sono i totali, come nel vecchio QNet: **Totale Consulenza**, **Totale Ente** (uno per tipologia: l\'imponibile, con accanto il ricavo quando è diverso; quelli a 0,00 sono attenuati), poi, se visibili, Commissioni e Netto commissioni, infine **Totale Ricavi**, ciascuno col suo tooltip che spiega il calcolo.',
        },
        {
          type: 'warning',
          text: 'Un\'icona di avviso accanto al prodotto (con il testo nel tooltip) compare se a una riga con commissione Fornitore ricevuta manca la commissione (ricavo 0,00) oppure se la commissione è stata calcolata su una base precedente: salva di nuovo l\'offerta per aggiornarla.',
        },
        {
          type: 'steps',
          items: [
            'Premi la matita accanto allo **Stato Pagamento** del prodotto (disponibile solo con il permesso di gestire i pagamenti): si apre il popup **Pagamento riga**.',
            'Scegli lo **stato di pagamento**, scrivi l\'**accordo sui pagamenti** e indica se ci sono **insoluti**.',
            'Premi **Salva** per salvare il prodotto e chiudere il popup, oppure **Annulla** per scartare le modifiche.',
          ],
        },
        {
          type: 'paragraph',
          text: '**Tutti i prodotti insieme**: con il permesso di gestire i pagamenti e più di un prodotto, sopra i prodotti c\'è il pulsante **Imposta pagamento su tutte le righe**. Serve per segnare in un colpo solo tutti i prodotti, ad esempio come **Saldato** o come **Insoluto**.',
        },
        {
          type: 'steps',
          items: [
            'Premi **Imposta pagamento su tutte le righe**: nel popup scegli lo **stato di pagamento** (anche **Nessuno stato**) e/o gli **insoluti** (Sì/No). Un campo lasciato su **Non modificare** resta com\'è su ogni prodotto.',
            'Premi **Applica**: i valori vengono salvati su tutti i prodotti insieme (se uno non può essere salvato non cambia nessuno).',
          ],
        },
        {
          type: 'note',
          text: 'L\'accordo sui pagamenti è un testo diverso per ogni riga: si modifica solo dal popup **Pagamento riga**. Se più righe diventano consegnabili con un\'azione massiva, supervisori e partecipanti ricevono **una sola notifica** con l\'elenco dei prodotti.',
        },
        {
          type: 'note',
          text: 'Gli stati di pagamento si configurano in **Stati pagamento commessa**. Quando una riga passa a uno stato con **Si può consegnare**, supervisori e partecipanti della commessa (tranne chi ha fatto la modifica) ricevono una notifica nella campanella e una email **Stato pagamento ok** con il nome dell\'azienda cliente. I dati di pagamento si modificano anche su una commessa completata o chiusa.',
        },
      ],
    },
    {
      id: 'invoices',
      title: 'Proforma attiva e passiva',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio della commessa, nella scheda **Amministrazione** accanto a **Costi**, le schede **Proforma attiva** e **Proforma passiva** elencano i documenti legati alla commessa. Compaiono solo se puoi vedere proforma e fatture (permesso "Vedere proforma e fatture"). Sono di **sola consultazione**: da qui non si crea, emette, incassa o paga nulla.',
        },
        {
          type: 'table',
          headers: ['Scheda', 'Cosa mostra'],
          rows: [
            ['Proforma attiva', 'Le proforma e fatture emesse al cliente per la commessa: tipo, numero, data, numero esterno, cliente, totale, incassato, residuo e stato incasso, con una riga **Totali** in fondo.'],
            ['Proforma passiva', 'Le proforma e fatture dei fornitori con **almeno una riga sulla commessa**: tipo, numero, data, fornitore, totale documento, **Quota commessa** (la somma delle righe del documento imputate a questa commessa), pagato e residuo, con una riga **Totali**.'],
          ],
        },
        {
          type: 'note',
          text: 'Una proforma che viene registrata resta in tabella, mostrata ora come **Fattura**. Un documento passivo compare appena una sua riga è sulla commessa, anche se le altre righe sono di altre commesse: per questo il totale documento e la quota commessa possono differire.',
        },
        {
          type: 'paragraph',
          text: 'Il **numero** del documento è un collegamento: se puoi vedere le fatture attive (o passive) apre il documento in un pannello laterale, dove le azioni abituali restano soggette ai loro permessi; altrimenti è testo semplice.',
        },
      ],
    },
    {
      id: 'multi-quote-work-order',
      title: 'Commessa multi-offerta',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una commessa **multi-offerta** raccoglie righe di più offerte (e quindi di più clienti). Si crea dalla tabella **Contratti** con **Programma commessa unica** (vedi la guida Contratti) e si riconosce dal badge **Multi-offerta**.',
        },
        {
          type: 'list',
          items: [
            'Nel dettaglio non ci sono le sezioni cliente, contratto, azienda e sede: ogni riga prodotto mostra sotto il nome l\'**anagrafica** a cui si riferisce, con il collegamento alla sua scheda.',
            'Le righe, dopo la creazione, si possono solo **togliere** (almeno una deve restare): non se ne possono aggiungere di altre offerte.',
            'Nell\'elenco le colonne Cliente e Offerta mostrano più valori separati da " · "; filtrando per uno dei clienti la commessa viene trovata. La scadenza contratto resta vuota.',
            'La commessa compare nel tab **Commesse** del contratto di ciascuna offerta di origine.',
            'Il budget dei costi è dato dalle righe di costo di **tutte** le offerte di origine.',
            'I task generati dal modello non hanno un cliente.',
            'Nella scheda Email non ci sono variabili di cliente o offerta e i destinatari non sono precompilati.',
          ],
        },
        {
          type: 'note',
          text: 'Le richieste proforma di una commessa multi-offerta sono una per offerta: vedi la guida Richieste Proforma. Un\'offerta con righe in una commessa multi-offerta non si può eliminare.',
        },
      ],
    },
    {
      id: 'copy-quote-attributes',
      title: "Dati copiati dall'offerta",
      blocks: [
        {
          type: 'paragraph',
          text: "Quando la commessa nasce da un contratto (**Programma**, creazione multipla o commessa multi-offerta), le **Informazioni aggiuntive** con lo stesso codice di quelle dell'offerta partono già compilate; nella commessa multi-offerta vale il primo valore non vuoto. Per le commesse esistenti usa **Copia dati dall'offerta** nell'intestazione (serve **Modifica**): riempie solo i campi vuoti, non sovrascrive mai, e ti dice quanti campi ha copiato.",
        },
      ],
    },
    {
      id: 'proforma-request',
      title: 'Richiesta di proforma',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se hai il permesso di creare richieste proforma, trovi il pulsante **Richiedi proforma** in alto nel dettaglio della commessa, accanto ad **Avanzamento commessa**, in fondo al popup **Responsabili e Partecipanti** dell\'elenco Commesse e nell\'icona della colonna **€** della tabella (vedi **Azioni della tabella**): aprono tutti la stessa finestra.',
        },
        {
          type: 'steps',
          items: [
            'Premi **Richiedi proforma**: si apre la finestra **Richiesta emissione Proforma: Commessa #numero**.',
            'Controlla la **Modalità di pagamento** presa dall\'offerta (se manca compare **Non indicata**).',
            'Scrivi le **Note per la Contabilità** (obbligatorie, massimo 5000 caratteri): il testo è precompilato con il titolo della finestra.',
            'Premi **Invia richiesta**. Il sistema crea una richiesta per le righe di tipo Consulenza e una per ciascun fornitore delle righe di tipo Ente (il fornitore scelto sulla riga dell’offerta).',
          ],
        },
        {
          type: 'note',
          text: 'Se c\'è già una richiesta non evasa, la finestra mostra **Ultima richiesta del** e la data: non se ne può inviare un\'altra e **Invia richiesta** resta disattivato. Una commessa genera le richieste una sola volta: con le richieste già evase non se ne possono generare di nuove.',
        },
        {
          type: 'tip',
          text: 'Le richieste inviate si consultano in **Contabilità › Attiva › Richieste Proforma**.',
        },
      ],
    },
    {
      id: 'deleting-a-work-order',
      title: 'Eliminare una commessa',
      blocks: [
        {
          type: 'warning',
          text: 'Una commessa si elimina in tre modi, tutti con il permesso di eliminare le commesse: dal menu con i tre puntini della sua riga (**Elimina**), dal pulsante **Elimina** in alto nel suo dettaglio, oppure selezionandone una o più nell\'elenco e usando **Elimina selezionati**. Prima di eliminare ti viene chiesta una conferma; eliminata dal dettaglio, si torna all\'elenco. **Non si può eliminare** se ha richieste proforma, fatture attive, ordini di acquisto (ODA) o fatture passive collegati: nel dettaglio il pulsante Elimina non compare, dalla riga compare un messaggio che spiega il motivo, con Elimina selezionati il messaggio la conta tra quelle non eliminate. Task, ore e costi inseriti a mano non bloccano l\'eliminazione.',
        },
      ],
    },
    {
      id: 'project-work-orders',
      title: 'Commesse di progetto e Commesse',
      blocks: [
        {
          type: 'paragraph',
          text: "La pagina **Commesse** mostra le commesse di tutti i tipi, **Lavorazione** e **Progetto**: la colonna **Tipo commessa** le distingue e il suo filtro restringe l'elenco a un tipo solo. Le commesse di progetto hanno anche una pagina propria, **Commesse di Progetto**, con la colonna **Non letti** e la tab **Chat** nel dettaglio; per ora quella pagina non compare nel menu. Anche le sezioni **Commesse** di un **Contratto** e di un'**Anagrafica** mostrano le commesse di tutti i tipi.",
        },
        {
          type: 'note',
          text: "La tab **Chat** esiste solo sulle commesse di progetto: nelle commesse di lavorazione non c'è.",
        },
      ],
    },
    {
      id: 'visibility',
      title: "Quali commesse vedi",
      blocks: [
        { type: 'paragraph', text: "Cosa vedi dipende dal tuo ruolo. Serve sempre il permesso **Visualizza**; i livelli si sommano:" },
        { type: 'table', headers: ["Permesso", "Commesse visibili"], rows: [["**Visualizza**", "Quelle di cui sei **Responsabile** o **Partecipante**."], ["**Visualizza per sede**", "Le tue e quelle la cui offerta è di una tua sede. Per una commessa con più offerte basta che lo sia una."], ["**Visualizza per competenza**", "Le tue e quelle in cui almeno una offerta di origine ha una categoria nel tuo ambito di assegnazione, in qualunque sede. Con **Ambito su tutte le categorie** tutte."], ["**Visualizza tutti**", "Tutte."]] },
        { type: 'paragraph', text: "Il filtro vale per elenco, scheda, filtri, esportazioni, statistiche e dashboard, selettori e documenti. Aprire il link di una commessa che non puoi vedere mostra un errore di accesso. Vedere una commessa non permette di modificarla o eliminarla: servono i permessi **Modifica** ed **Elimina**, e si modifica solo ciò che si vede." },
        { type: 'paragraph', text: "Chi vede una commessa vede anche i suoi task nel task board e nella pagina **Task**, se ha il permesso di vedere i Task, anche quando non ha un ruolo sul task; restano esclusi i task privati. Per modificarli serve un ruolo sul task o il permesso **Gestisci tutto** dei Task. Eccezione: un **Responsabile** o un **Partecipante** può completare a suo nome un task della commessa, anche se è già assegnato a un collega, e completandolo ne diventa uno degli assegnatari." },
        { type: 'note', text: "Se crei una commessa senza esserne Responsabile o Partecipante, non la vedrai più a meno di avere **Visualizza tutti**, **Visualizza per sede** o **Visualizza per competenza**." },
        { type: 'tip', text: "Chi può modificare la commessa può coinvolgere un collega che non la vede menzionandolo in una nota con **Menziona chiunque**: al salvataggio diventa **Partecipante** (vedi **Note e documenti** nella guida generale)." },
      ],
    },
    ...recurringEmailSections,
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. La guida verrà pubblicata quando il modulo sarà completato.',
        },
      ],
    },
  ],
}

export default guide
