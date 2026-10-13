import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'migrations',
  title: 'Migrazioni',
  summary: 'Il modulo Migrazioni importa nel gestionale i dati di un sistema esterno, di solito all’avvio: ruoli, utenti, società, sedi, referenti, prodotti, anagrafiche, opportunità, offerte e commesse.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Develop › Migrazioni** ed è riservato al ruolo **super-admin**. Ogni **Sorgente** rappresenta un tipo di dato da importare: Ruoli, Utenti, Funzioni aziendali, Società aziendali, Società sedi, Sedi operative, Tipi di referente, Referenti, Fonti, Tag, Settori, Modelli di task, Aliquote IVA, Modalità di pagamento, Attributi, Categorie prodotto, Layout attributi, Prodotti, Prodotti di costo, Modelli email, Modelli documenti, Anagrafiche, Opportunità, Offerte, Commesse e Configuratore commissioni.' },
        { type: 'note', text: 'Le sorgenti con **— collega** nel nome non creano nuovi record: collegano tra loro record già importati, quindi vanno eseguite dopo le sorgenti di quei record. **Funzioni aziendali — collega responsabile e operatori** assegna responsabile e operatori alle funzioni aziendali (dopo **Utenti**). **Categorie prodotto — collega attributi** associa gli attributi alle categorie (dopo **Attributi** e **Categorie prodotto**). **Prodotti — collega fornitori** imposta il fornitore, cioè un\'anagrafica, sui prodotti importati (dopo **Prodotti** e **Anagrafiche**); un fornitore già scelto in QNet non viene sovrascritto. **Commesse — collega dati di pagamento alle righe** copia stato pagamento, accordo di pagamento e insoluti della commessa legacy su ogni riga della commessa (dopo **Commesse**).' },
        { type: 'note', text: 'La sorgente **Prodotti di costo** importa i costi del QNet legacy: articoli di magazzino, veicoli, attrezzature e note spese. Ogni voce diventa un prodotto **Utilizzabile come costo** (solo tab Costi dell\'offerta) sotto la categoria **Costi**, nelle sotto-categorie **Articoli**, **Veicoli**, **Attrezzature** e **Note spese**; un articolo finisce in una sotto-categoria di Articoli col nome della sua categoria legacy. Targa, matricola, marca, modello, barcode, codice ministeriale e posizione di magazzino diventano attributi del prodotto. Per gli articoli il costo è il prezzo di acquisto legacy. Esegui prima **Aliquote IVA**, altrimenti l\'aliquota resta vuota con un avviso.' },
        { type: 'note', text: 'Le sorgenti **Modelli email** e **Modelli documenti** importano i Modelli email e i Modelli documenti del QNet legacy, questi ultimi con tutti i loro file. Un nome già usato riceve un suffisso numerico; un file troppo grande o con un formato non ammesso viene saltato con un avviso, senza far fallire la riga.' },
        { type: 'note', text: 'La sorgente **Layout attributi** importa le sezioni della Commessa di tutte le card legacy (ISO, SOA, Sicurezza, Avvalimenti, GDPR, R&S, PAL, Finanza agevolata, Gare, Partnership sedi, Istruzione, Formazione GOL) e, per l\'Offerta, le "Info preliminari" dell\'opportunità legacy (ISO con i gruppi della sola norma della categoria e la tabella Sedi, SOA, Avvalimenti, GDPR, R&S, Gare, Partnership, Progetti, Istruzione). Il campo **Stato Lavorazione** riceve gli stati della famiglia (radice) della categoria. Una categoria riceve i campi (Commessa e Offerta) solo dalle card dei propri prodotti. Esegui prima **Attributi**, **Categorie prodotto** e **Categorie prodotto — collega attributi**: un layout che usa un campo non collegato alla categoria viene scartato. Un layout già presente non viene sovrascritto. I valori dei campi delle singole commesse non vengono importati, né i campi Formazione GOL dell\'Offerta; le note libere delle Info preliminari vanno nelle note generali dell\'opportunità. I campi e i layout del ramo Formazione configurati in QNet non vengono modificati.' },
        { type: 'note', text: 'Un prodotto che nel legacy non ha categoria viene importato nella categoria **No categoria**, creata all’installazione con la funzione aziendale **No funzione aziendale** e non selezionabile per i nuovi prodotti: da lì lo sposti nella categoria giusta. Grazie a quella funzione le offerte legacy che contengono questi prodotti si importano comunque. Nel legacy sono soprattutto sconti, prove e voci di servizio.' },
        { type: 'note', text: 'La sorgente **Utenti** riconosce gli utenti già presenti in QNet, ad esempio quelli creati all’installazione: prima per email, poi per nome e cognome tra gli utenti non ancora collegati al legacy. L’utente riconosciuto riceve l’id legacy, così le sorgenti successive lo collegano; si completano solo società, sede e responsabile vuoti, mentre ruolo, password, email e contatti restano quelli di QNet. Se più utenti hanno lo stesso nome la riga fallisce con i nomi trovati; gli utenti non riconosciuti vengono creati.' },
        { type: 'note', text: 'La sorgente **Modelli di task** importa modello, fasi, attività e sotto-attività. Un modello col titolo che contiene "non attivo" arriva disattivato. Un titolo già usato da un altro modello riceve il suffisso "(old_id N)". Una sotto-attività con un problema (padre inesistente, padre in un altro modello, ciclo) diventa un\'attività principale, con un avviso nel report.' },
      ],
    },
    {
      id: 'status-and-requirements',
      title: 'Cosa è migrato e cosa serve',
      blocks: [
        { type: 'paragraph', text: 'Il riquadro **Stato migrazione** elenca tutte le sorgenti divise per fase, nell’ordine in cui importarle, e indica quante sono già migrate. Ogni sorgente mostra il suo stato: **Migrata**, **Migrata con righe fallite**, **Ultimo import fallito**, **In corso** o **Non migrata**, con la data dell’import. Fai clic su una sorgente per selezionarla.' },
        { type: 'paragraph', text: 'Il riquadro **Cosa serve per migrare bene** spiega cosa richiede la sorgente selezionata ed elenca le sorgenti da importare prima, ciascuna con il suo stato. Una sorgente **Obbligatoria** che manca impedisce di importare le righe; una **Consigliata** che manca lascia vuoto il collegamento, con un avviso nel report. Se manca una sorgente obbligatoria, il riquadro lo segnala in rosso.' },
        { type: 'tip', text: 'Una sorgente resta **Migrata** anche se un import successivo fallisce: la data indica l’ultimo import completato.' },
      ],
    },
    {
      id: 'preview-step',
      title: 'Fase 1: controllo e anteprima',
      blocks: [
        { type: 'steps', items: ['Apri **Develop › Migrazioni**.', 'Nel campo **Sorgente** scegli il tipo di dati da importare, ad esempio **Utenti** o **Prodotti**.', 'Il riquadro **Template atteso** elenca i campi che QNet si aspetta dal sistema esterno.', 'Fai clic su **Mostra anteprima dati esterni**. Usa **Precedente** e **Successiva** per sfogliare i dati.'] },
        { type: 'note', text: 'In questa fase non viene creato nulla.' },
      ],
    },
    {
      id: 'import-step',
      title: 'Fase 2: importazione',
      blocks: [
        { type: 'steps', items: ['Dopo aver controllato l’anteprima, fai clic su **Importa questa sorgente**.', 'Leggi la finestra di conferma e fai clic su **Avvia import**.', 'Segui l’avanzamento. Alla fine vedi il riepilogo: **Righe totali**, **Creati**, **Saltati** e **Falliti**.', 'Controlla **Avvisi ed errori** per le righe con problemi.'] },
        { type: 'tip', text: 'L’import continua anche se chiudi la finestra. Le schede già importate vengono saltate, quindi puoi ripetere l’import senza creare doppioni.' },
      ],
    },
    {
      id: 'operational-records',
      title: 'Anagrafiche, opportunità, offerte e commesse',
      blocks: [
        { type: 'paragraph', text: 'Le sorgenti **Anagrafiche**, **Opportunità**, **Offerte** e **Commesse** importano i dati operativi del QNet legacy. Vanno eseguite in quest’ordine e dopo **Utenti**, **Referenti**, **Fonti**, **Tag**, **Settori**, **Sedi operative**, **Categorie prodotto**, **Prodotti**, **Aliquote IVA**, **Modalità di pagamento** e **Società sedi**: ogni sorgente collega i record già importati dalle precedenti.' },
        { type: 'list', items: [
          '**Anagrafiche**: un cliente privato diventa una scheda persona fisica, gli altri una scheda azienda. La sede legale è l’indirizzo principale, le altre sedi legacy diventano indirizzi aggiuntivi. Le partite IVA segnaposto (0, 1, punto) non vengono importate. I tag legacy finiscono nel campo personalizzato **Tag** dell\'anagrafica: esegui prima la sorgente **Tag**; se il campo non è definito i tag non vengono importati e il report lo segnala.',
          '**Opportunità**: arrivano solo quelle con un cliente. Le richieste di corso GOL senza cliente non vengono importate. Un cliente può avere più opportunità aperte. Un’opportunità prende le categorie dei prodotti presenti nelle righe delle sue offerte; solo se le offerte non ne hanno, prende le categorie prodotto dell’opportunità legacy. Se rilanci l’import, le opportunità già importate senza categorie vengono completate (avviso "Missing product categories back-filled."); quelle che hanno già categorie non vengono toccate. Nelle **Note generali** arrivano le note legacy, le note riservate e il campo **Note** di ogni card delle Info preliminari, sotto il titolo della card. Se rilanci l’import, le opportunità già importate ricevono le note delle Info preliminari che mancano (avviso "Missing preliminary info notes back-filled.").',
          '**Offerte**: arrivano le offerte delle opportunità importate, con codice **QUO-** seguito dall’id legacy, le righe di ricavo, l’IVA e le provvigioni con percentuale o importo. Lo stato segue quello legacy: rifiutata diventa chiusa negativa; accettata o contrattualizzata diventa chiusa positiva e crea il contratto con lo stato e le date del legacy; accettata dal cliente va nel primo stato in attesa del workflow (o nel primo validato, se non ce ne sono). Un’offerta presentata segue la fase dell’opportunità legacy: Offerta inviata va in **In trattativa**, Da richiamare in **Da Richiamare**, Stand-by in **Rimandata** o **In Standby**, Persa in chiusa negativa; Attesa pro-forma e Accettato vanno in chiusa negativa se un’altra offerta della stessa opportunità è vinta, altrimenti in **In trattativa**; le altre restano aperte. Se il workflow dell’offerta non ha quello stato, l’offerta resta aperta con un avviso. Un’offerta chiusa nel legacy si chiude anche se nessuna sua riga è stata importata, con un avviso. Arrivano anche la **data offerta**, il **prossimo richiamo** (la data di richiamo dell’opportunità legacy) e i **costi**: ogni costo legacy diventa una riga della tab Costi con il suo prodotto di costo (esegui prima **Prodotti di costo**), collegata alla riga di ricavo con lo stesso prodotto. Se il fornitore indicato nel legacy è diverso da quello del prodotto, o il prodotto non ne ha, ricevi un avviso e il fornitore non viene scritto. Un’offerta non può avere un cliente diverso da quello della sua opportunità: se nel legacy è diverso ricevi un avviso e l’offerta resta sul cliente dell’opportunità. Arrivano anche le **Informazioni aggiuntive**: i valori delle Info preliminari dell’opportunità legacy vanno su ogni sua offerta, solo nei campi previsti dalle categorie dei prodotti dell’offerta (esegui prima **Categorie prodotto — collega attributi**). Un campo non previsto, un valore non valido o un utente non importato vengono saltati con un avviso e l’offerta si importa comunque.',
          '**Commesse**: arrivano con codice **COM-** seguito dall’id legacy, le righe dell’offerta collegate, supervisori e partecipanti. Le commesse chiuse, annullate o disdette nel legacy arrivano chiuse forzatamente con il motivo.',
        ] },
        { type: 'note', text: 'Durante questo import non partono notifiche né email e non vengono scritte voci nel registro attività. Note, documenti, valori dei campi flessibili delle commesse, fasi e task delle commesse non vengono importati. Una riga d’offerta senza prodotto importato (ad esempio uno sconto) viene saltata con un avviso; una riga già collegata a un’altra commessa resta sulla prima e per ogni commessa successiva viene aggiunta all’offerta una copia della riga, con le sue provvigioni: il totale dell’offerta cresce di conseguenza.' },
      ],
    },
    {
      id: 'mass-import',
      title: 'Importare tutto insieme',
      blocks: [
        { type: 'steps', items: ['Fai clic su **Configura ordine**.', 'Scegli le sorgenti da includere, trascinale nell’ordine giusto e fai clic su **Salva ordine**.', 'Fai clic su **Importa tutto** e poi su **Avvia import**.'] },
        { type: 'paragraph', text: 'Le sorgenti vengono importate una dopo l’altra. Se una fallisce, l’import si ferma e le successive risultano **Non eseguita**.' },
        { type: 'tip', text: 'Metti prima i dati da cui dipendono gli altri: i ruoli prima degli utenti, le società prima delle sedi.' },
      ],
    },
  ],
}

export default guide
