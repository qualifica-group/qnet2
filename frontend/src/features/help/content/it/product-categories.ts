import type { HelpGuide } from '../../types'
import { attributeSections } from './parts/product-categories-attributes'

const guide: HelpGuide = {
  key: 'product-categories',
  title: 'Categorie Prodotto',
  summary: 'Le Categorie Prodotto sono il cuore di QNet: da loro dipendono i campi che compili, le regole che il gestionale applica e i numeri che leggi nei report.',
  sections: [
    {
      id: 'why-it-is-central',
      title: 'Perché è il modulo centrale',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni prodotto appartiene a una categoria. Le categorie formano un albero: in cima le categorie radice, sotto le sottocategorie. Una radice con tutte le sue discendenti si chiama ramo. Una regola impostata su una categoria vale in molti altri moduli.',
        },
        {
          type: 'table',
          headers: ['Impostazione della categoria', 'Dove ha effetto'],
          rows: [
            ["Categoria padre (posizione nell'albero)", 'Decide cosa la categoria eredita. Nelle righe prodotto di progetti, campagne, opportunità e lead scegli prima la Categoria genitore (una radice) e poi una sua discendente; con Filtro, facoltativo, restringi la scelta a una figlia diretta che raggruppa altre categorie (es. Consulenza › ISO › ISO 9001).'],
            ['Funzione aziendale', "Ricavata in automatico nelle righe prodotto; serve per gli ambiti di assegnazione degli utenti e l'assegnazione di lead e richieste. Il prodotto la mostra in sola lettura."],
            ['Attributi Prodotto', 'Campi aggiuntivi nella scheda Prodotto.'],
            ['Attributi Offerta', "Campi nelle Informazioni aggiuntive dell'Offerta e colonne nelle schede per categoria di Gestione Richieste."],
            ['Attributi Commessa', 'Campi nelle Informazioni aggiuntive della Commessa.'],
            ['Layout attributi', 'Come questi campi sono disposti a video: sezioni, righe e larghezze.'],
            ['Modalità di gestione', 'Quante righe Categoria Prodotto può avere una scheda, in Opportunità e in Gestione Richieste.'],
            ['Offerta unica per opportunità', "Quante offerte può avere un'opportunità."],
            ['Prevede un contratto', "Se un'offerta chiusa con esito positivo apre un contratto."],
            ['Semplificazione riga offerta', 'Se in Gestione Richieste la riga offerta si compila da sola.'],
            ['Selezionabile', 'Se la categoria compare negli elenchi di scelta di prodotti, righe prodotto, progetti e campagne. Le regole provvigionali possono usare qualsiasi categoria attiva, anche una madre: valgono per tutto il ramo.'],
            ['Attiva', 'Se la categoria, con tutto il suo ramo e i suoi prodotti, compare nei campi di inserimento. Se la spegni sparisce da lì ma resta nello storico e nei filtri delle griglie.'],
            ['Visibile nei report e Colonne report', 'Righe e colonne del report e della dashboard di Gestione Richieste e Gestione Iscritti.'],
            ['Gestori Account', 'Il nome di ogni livello di Gestore Account (per esempio "Tutor" invece di "Gestore account 1").'],
            ['La categoria stessa', 'Criterio dei workflow stati offerta (Categoria prodotto o Categoria prodotto (ramo)) e ambito delle regole provvigionali.'],
          ],
        },
        {
          type: 'warning',
          text: 'Una modifica a una categoria radice può cambiare il comportamento di tutto il ramo. Ricontrolla le regole della radice prima di confermare.',
        },
      ],
    },
    {
      id: 'list-page',
      title: 'La pagina elenco',
      blocks: [
        {
          type: 'paragraph',
          text: 'Apri Configurazione › Prodotti › Categorie Prodotto. Le categorie compaiono in una tabella, non ad albero: la colonna Padre dice dove si trova ciascuna.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Cosa mostra'],
          rows: [
            ['Nome', 'Il nome; la ricerca rapida cerca qui.'],
            ['Padre', 'La categoria superiore; vuota per le radici.'],
            ['Descrizione', 'Testo libero.'],
            ['Funzione aziendale', 'La funzione valida, propria o ereditata.'],
            ['Prevede preventivo, Selezionabile, Attiva, Prevede un contratto', 'Sì o no.'],
            ['Visibile nei report', 'Il valore effettivo, proprio o ereditato.'],
            ['Modalità di gestione', 'Singola o Multipla.'],
            ['Offerta unica per opportunità, Semplificazione riga offerta', "Nascoste all'inizio; si mostrano dalla scelta delle colonne."],
            ['Attributi', 'Quanti attributi sono assegnati direttamente (non conta quelli ereditati).'],
            ['Prodotti', 'Quanti prodotti appartengono alla categoria.'],
            ['Creato il', "Data di creazione; l'elenco parte dalle più recenti."],
          ],
        },
        {
          type: 'tip',
          text: 'Nel filtro della colonna Padre scegli il valore vuoto per vedere solo le categorie radice.',
        },
        {
          type: 'paragraph',
          text: 'Il pulsante Statistiche mostra Categorie, Categorie radice, Con prodotti, Ereditano attributi e Prodotti per categoria.',
        },
        {
          type: 'table',
          headers: ['Azione sulla riga', 'Cosa fa'],
          rows: [
            ['Visualizza', 'La scheda con regole, attributi, gestori account e anteprima del layout: ogni riga si modifica sul posto (vedi Modificare una categoria).'],
            ['Layout attributi', "Apre l'editor del layout."],
            ['Duplica', 'Apre il form di creazione già compilato con i dati della categoria (vedi Duplicare una categoria).'],
            ['Elimina', 'Elimina dopo conferma (vedi i vincoli più avanti).'],
            ['Attività', 'Storico delle modifiche.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Spuntando più righe, il menu Azioni (n) offre Sposta sotto… per cambiare padre a tutte in un colpo solo.',
        },
        {
          type: 'warning',
          text: 'Non c\'è un\'azione "crea sottocategoria" sulla riga: usa Nuova categoria e scegli la Categoria padre.',
        },
      ],
    },
    {
      id: 'create-root-category',
      title: 'Creare una categoria radice',
      blocks: [
        {
          type: 'steps',
          items: [
            'Clicca Nuova categoria: si apre la scheda della nuova categoria, uguale a quella di una categoria esistente, con le righe chiuse.',
            'Per compilare una riga premi la matita (o il valore), scrivi o scegli e premi Fatto; Ripristina riporta la riga com\'era. Nulla viene salvato finché non premi Salva.',
            'In Dettagli scrivi il Nome, lasciando Categoria padre su Nessun padre (categoria radice).',
            'Imposta le Regole di gestione: sulla radice sono tutte modificabili.',
            'Assegna gli attributi nelle tre sezioni: l\'editor è già aperto.',
            'Se serve, dai un nome ai livelli di Gestori Account: anche questo editor è già aperto.',
            'Premi Salva (in alto o in fondo alla pagina). Compare "Categoria creata con successo."; Annulla chiede conferma prima di scartare quanto inserito.',
          ],
        },
        {
          type: 'table',
          headers: ['Campo (Dettagli)', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, massimo 191 caratteri. Comparirà in tutti gli elenchi: sceglilo chiaro.'],
            ['Categoria padre', 'Per una radice: Nessun padre (categoria radice).'],
            ['Descrizione', 'Facoltativa.'],
            ['Funzione aziendale', 'La funzione del ramo. Bloccata se un antenato ne ha già una.'],
          ],
        },
        {
          type: 'paragraph',
          text: "Regole di gestione: ogni regola è una riga; aprendola compare il riquadro con interruttore, e l'icona (i) ne spiega l'effetto.",
        },
        {
          type: 'table',
          headers: ['Regola', 'Cosa indicare', 'Valore iniziale'],
          rows: [
            ['Prevede preventivo', 'Se il ramo lavora con le offerte.', 'No'],
            ['Modalità di gestione', 'Singola (una riga per scheda) o Multipla (più righe per scheda).', 'Multipla'],
            ['Offerta unica per opportunità', 'Se ogni opportunità può avere una sola offerta.', 'Sì'],
            ['Prevede un contratto', 'Se una chiusura positiva apre un contratto.', 'Sì'],
            ['Semplificazione riga offerta', 'Se in Gestione Richieste la riga offerta si compila da sola.', 'No'],
            ['Selezionabile', 'Se la categoria si può scegliere negli elenchi. Spenta, resta visibile come contenitore.', 'Sì'],
            ['Attiva', 'Se spenta, la categoria sparisce con tutte le sottocategorie e i prodotti del ramo dai campi di inserimento; i collegamenti esistenti restano validi e i filtri la vedono ancora.', 'Sì'],
            ['Visibile nei report', 'Se la categoria è una riga dei report.', 'No'],
            ['Colonne report', 'Compare solo se la categoria è visibile nei report.', '—'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Gestori Account: clicca Aggiungi livello per ogni livello usato e scrivi il nome da mostrare (massimo 60 caratteri), per esempio "Operatore". Un campo vuoto mantiene "Gestore account N". L\'icona di ripristino torna al nome predefinito senza togliere i gestori già assegnati.',
        },
        {
          type: 'note',
          text: "La sezione Altri campi compare solo se l'amministratore ha creato campi personalizzati per le categorie.",
        },
      ],
    },
    {
      id: 'create-subcategory',
      title: 'Creare una sottocategoria',
      blocks: [
        {
          type: 'steps',
          items: [
            'Clicca Nuova categoria e scrivi il Nome.',
            'In Categoria padre cerca e scegli la categoria superiore, poi premi Fatto: le righe delle regole del ramo mostrano subito il valore ereditato.',
            'Imposta ciò che vale solo qui: Selezionabile, Attiva, Visibile nei report, Colonne report, Semplificazione riga offerta, i tuoi attributi e le etichette dei gestori.',
            'Clicca Salva.',
          ],
        },
        {
          type: 'list',
          items: [
            'Le righe delle quattro regole decise dalla radice mostrano il badge Ereditata da "Nome radice" e non hanno la matita; Semplificazione riga offerta mostra Ereditata da … ma si può forzare.',
            'La riga Funzione aziendale non ha la matita se un antenato ne ha una.',
            "Ogni blocco di attributi mostra Eredita dal padre e l'elenco Ereditati dalle categorie antenate.",
          ],
        },
      ],
    },
    {
      id: 'duplicate-category',
      title: 'Duplicare una categoria',
      blocks: [
        {
          type: 'steps',
          items: [
            'Nella riga della categoria da copiare scegli Duplica (serve il permesso di creare categorie).',
            'Si apre la scheda di creazione con tutti i dati della categoria: il Nome ha il suffisso " (copia)", padre, regole, attributi, gestori account e altri campi sono gli stessi.',
            'Cambia ciò che serve (almeno il Nome) con la matita di ogni riga e clicca Salva.',
          ],
        },
        {
          type: 'list',
          items: [
            'Anche il layout attributi della categoria viene copiato. Se nella copia togli un attributo, il campo sparisce dal layout copiato.',
            "Non vengono copiati le sottocategorie, i prodotti e lo storico: la copia è una categoria nuova e vuota.",
            "La categoria originale non cambia.",
          ],
        },
      ],
    },
    {
      id: 'editing-a-category',
      title: 'Modificare una categoria',
      blocks: [
        {
          type: 'paragraph',
          text: "La scheda di una categoria non ha un modulo di modifica: ogni riga si modifica sul posto, una alla volta. Premi la matita (o il valore) su Nome, Categoria padre, Descrizione, Funzione aziendale, su una regola di gestione o su un campo personalizzato, cambia il valore e premi Salva; Annulla o Esc lasciano la riga com'era. Ogni Salva invia solo ciò che hai cambiato in quella riga.",
        },
        {
          type: 'paragraph',
          text: "Attributi e Gestori Account sono ciascuno un'unica riga a tutta larghezza: la matita apre lo stesso editor della creazione (con i tre Eredita dal padre per gli attributi e quello dei gestori) e Salva li salva tutti insieme.",
        },
        {
          type: 'list',
          items: [
            'Le quattro regole decise dalla radice (Prevede preventivo, Modalità di gestione, Offerta unica per opportunità, Prevede un contratto) e la Funzione aziendale ereditata non hanno la matita sulle sottocategorie: mostrano il valore con il badge Ereditata da …; per cambiarle apri la categoria indicata.',
            'La riga Colonne report compare solo se la categoria è visibile nei report.',
            'Se assegni una funzione aziendale a una categoria con sottocategorie che ne hanno una propria, prima di salvare compare la richiesta di conferma.',
            'Senza il permesso di modifica le righe non mostrano la matita.',
          ],
        },
      ],
    },
    {
      id: 'inheritance',
      title: 'Ereditarietà',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le impostazioni si ereditano in quattro modi diversi.',
        },
        {
          type: 'table',
          headers: ['Tipo', 'Impostazioni', 'Come funziona'],
          rows: [
            ['Decide solo la radice', 'Prevede preventivo, Modalità di gestione, Offerta unica per opportunità, Prevede un contratto', 'Tutto il ramo le segue; sulle sottocategorie la riga mostra il valore senza matita. Cambiandole sulla radice cambiano subito per tutto il ramo.'],
            ["Dall'antenato più vicino", 'Funzione aziendale', "Vale quella dell'antenato più vicino che ne ha una; se nessuno ce l'ha, la scegli tu e da lì in giù si eredita."],
            ['Ereditata ma forzabile', 'Visibile nei report, Colonne report, Semplificazione riga offerta', 'La sottocategoria prende il valore del padre (Ereditata da …); se lo cambi compare Forzato. Riportandolo al valore del padre torna a ereditare.'],
            ['Solo per la categoria', 'Selezionabile', 'Non si eredita mai: un padre non selezionabile può avere figli selezionabili.'],
            ['Si propaga al ramo', 'Attiva', 'Il flag è della sola categoria, ma una categoria non attiva rende non attivo tutto il suo ramo senza cambiare il flag delle sottocategorie; sotto lo switch compare "Non attiva perché lo è «…»".'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Se assegni una funzione a una categoria con sottocategorie che ne hanno una propria, compare "Le sottocategorie perderanno la loro funzione aziendale" con l\'elenco di quelle coinvolte. Assegna e azzera conferma: le sottocategorie ereditano la nuova funzione.',
        },
        {
          type: 'paragraph',
          text: 'Attributi ed "Eredita dal padre": una categoria usa i propri attributi più quelli di tutti gli antenati. Ogni blocco (Prodotto, Offerta, Commessa) ha il suo Eredita dal padre: spegnendolo, la categoria e le sue sottocategorie ignorano gli attributi superiori solo in quel blocco.',
        },
        {
          type: 'steps',
          items: [
            'Formazione (radice): Prevede un contratto no, Modalità di gestione Multipla, Attributi Offerta "Ore complessive".',
            'GOL, figlia di Formazione: non selezionabile, Visibile nei report.',
            'Autofinanziato, figlia di Formazione: aggiunge l\'attributo "Modalità di svolgimento".',
            'GOL - Lombardia, figlia di GOL: eredita regole, report e "Ore complessive".',
            'GOL - Lazio, figlia di GOL: Visibile nei report forzato a no.',
            'DIL, figlia di Formazione: Eredita dal padre spento nel blocco Attributi Offerta.',
            'DIL - Lombardia, figlia di DIL: non riceve "Ore complessive".',
          ],
        },
        {
          type: 'paragraph',
          text: "L'esempio mostra un ramo: le regole scendono dalla radice, la visibilità nei report si può forzare e un blocco di attributi si può staccare dagli antenati.",
        },
      ],
    },
    {
      id: 'management-rules-in-practice',
      title: 'Le regole di gestione nella pratica',
      blocks: [
        {
          type: 'table',
          headers: ['Regola', 'Effetto concreto'],
          rows: [
            ['Prevede preventivo', "Dice se il ramo lavora con le offerte e compare come colonna nell'elenco. Oggi il pannello Offerte è comunque disponibile su ogni opportunità."],
            ['Modalità di gestione: Singola', "Opportunità o richiesta con una sola riga Categoria Prodotto; Aggiungi riga prodotto non ne permette una seconda e, se c'è già un'altra riga, la categoria non è selezionabile. Nella cella Categoria prodotto di Gestione Richieste la categoria scelta sostituisce quella presente. Anche l'offerta ha una sola riga prodotto e un prodotto di un'altra categoria viene rifiutato."],
            ['Modalità di gestione: Multipla', 'Nessun limite di righe.'],
            ['Offerta unica per opportunità', 'Una seconda offerta sulla stessa opportunità viene rifiutata. Le opportunità che ne hanno già più di una le conservano.'],
            ['Prevede un contratto', 'Attiva: la chiusura positiva apre un contratto. Spenta: nessun contratto, ma la chiusura positiva resta possibile. I contratti già aperti restano. Se una scheda copre più categorie, basta una senza contratto per non crearlo.'],
            ['Semplificazione riga offerta', "In Gestione Richieste l'operatore sceglie solo il prodotto: quantità 1, prezzo e IVA dal prodotto. Nel modulo Offerte restano modificabili a mano. Una sottocategoria può forzarla: spenta, l'operatore modifica quantità, prezzo e IVA."],
            ['Selezionabile spento', 'La categoria diventa un contenitore e sparisce dagli elenchi di scelta; le associazioni già fatte restano. Usandola comunque compare "Questa categoria prodotto non è selezionabile."'],
            ['Attiva spenta', 'La categoria, le sue sottocategorie e i prodotti del ramo spariscono dai campi di inserimento e modifica; lo storico resta valido (i record già collegati si risalvano) e i filtri delle griglie la vedono ancora. Usandola comunque compare "Questa categoria prodotto non è attiva."'],
            ['Visibile nei report', 'La categoria diventa una riga del report e della dashboard di Gestione Richieste e Gestione Iscritti; sceglierla nel report include le sottocategorie. Una categoria esclusa non entra nemmeno nei totali del padre.'],
          ],
        },
      ],
    },
    {
      id: 'multi-quote-work-order',
      title: 'Commessa multi-offerta',
      blocks: [
        {
          type: 'paragraph',
          text: 'L\'impostazione **Commessa multi-offerta** si trova nel form della categoria radice ed è **spenta** per default. Se la accendi, per tutto il ramo si può programmare **una sola commessa** che raccoglie le righe di più offerte (anche di clienti diversi): l\'azione si usa dalla tabella **Contratti**.',
        },
        {
          type: 'paragraph',
          text: 'Come le altre regole decise dalla radice, sulle sottocategorie il valore è **ereditato** e in sola lettura (badge **Ereditata da …**): per cambiarlo apri la radice. Cambiandolo sulla radice, cambia subito per tutto il ramo.',
        },
        {
          type: 'paragraph',
          text: 'La **Commessa multi-offerta** richiede **Prevede un contratto** acceso: finché quest\'ultimo è spento non puoi accenderla, e finché è accesa non puoi spegnere **Prevede un contratto** (spegni prima la Commessa multi-offerta). Il salvataggio di una combinazione incoerente viene rifiutato con un messaggio sul campo.',
        },
        {
          type: 'note',
          text: 'L\'impostazione vale per le righe di ricavo dei prodotti del ramo. Le commesse già create non cambiano se la spegni; le righe di rami diversi non si possono mai unire nella stessa commessa.',
        },
      ],
    },
    ...attributeSections,
    {
      id: 'report-columns',
      title: 'Colonne report',
      blocks: [
        {
          type: 'paragraph',
          text: 'Decidono quali numeri della statistica di Gestione Richieste si calcolano per la categoria, sia nel report CSV/Excel sia nella dashboard.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Nota'],
          rows: [
            ['N. Telefonate Effettuate', ''],
            ['N. Richiami non gestiti, N. Nuovi contatti non gestiti, N. Potenziali associati', 'Ignorano il periodo e guardano alla situazione di oggi. Scelte di default nelle categorie predefinite.'],
            ['N. Richiami non gestiti (nel periodo selezionato), N. Nuovi contatti non gestiti (nel periodo selezionato), N. Potenziali associati (nel periodo selezionato)', 'Stesso dato calcolato sul periodo scelto nel report. Non scelte di default: spuntale se ti servono.'],
            ['Aziende inserite', ''],
            ['Associati, Trattative Concluse, Invio Presa in carico', 'Contano le richieste create nel periodo che oggi sono in uno stato chiuso con esito positivo (dettaglio in Gestione Richieste › Statistiche e report).'],
            ['Presa Appuntamenti', 'Conta i passaggi diretti da "OK App. Fissato" ad "Assegnato" avvenuti nel periodo: ha senso nelle categorie il cui workflow ha questi due stati.'],
            ['Aule in gestione, Aule in partenza', 'Oggi non hanno un calcolo e mostrano sempre 0.'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Attiva Visibile nei report: compare il riquadro Colonne report.',
            'Sulla riga Colonne report premi la matita e spunta le colonne che ti servono; il contatore mostra quante ne hai scelte (per esempio "7/14"). Tutte e Nessuna selezionano o svuotano.',
            'Premi Salva (Fatto nella creazione).',
          ],
        },
        {
          type: 'paragraph',
          text: 'Una sottocategoria usa le colonne dell\'antenato più vicino che le ha impostate (badge Ereditata da …). Scegliendone di diverse compare Proprie ("Sovrascrivono quelle ereditate da …"); Torna alle ereditate annulla.',
        },
        {
          type: 'paragraph',
          text: 'Una colonna non scelta resta vuota nel file e non compare in dashboard. Il file contiene l\'unione delle colonne delle categorie selezionate. Senza colonne la dashboard mostra "Nessuna colonna configurata per questa categoria."',
        },
      ],
    },
    {
      id: 'move-reorganize-delete',
      title: 'Spostare, riorganizzare, eliminare',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per spostare una categoria aprila, premi la matita su Categoria padre, scegli il nuovo padre e premi Salva (non puoi scegliere la categoria stessa né una sua discendente). Per spostarne molte:',
        },
        {
          type: 'steps',
          items: [
            'Seleziona le righe e scegli Azioni (n) › Sposta sotto….',
            'Nella finestra Sposta categorie scegli la Destinazione, anche Nessun padre (categoria radice).',
            'Clicca Sposta.',
          ],
        },
        {
          type: 'paragraph',
          text: "Le categorie spostate portano con sé le sottocategorie; l'operazione è tutto o niente. Dopo lo spostamento:",
        },
        {
          type: 'list',
          items: [
            'Sotto un nuovo ramo, la categoria adotta le cinque regole della nuova radice.',
            'Se diventa radice, tiene i valori attuali e da allora li decide lei.',
            'Attributi, layout, visibilità nei report, colonne e nomi dei gestori seguono i nuovi antenati.',
            'I prodotti restano nella loro categoria e la seguono, mostrando i campi dei nuovi antenati.',
          ],
        },
        {
          type: 'warning',
          text: 'Elimina funziona solo se la categoria non ha sottocategorie, prodotti o opportunità collegate; altrimenti compare "Questa categoria ha sottocategorie o prodotti." Sposta prima sottocategorie e prodotti. Per nasconderla soltanto, spegni Selezionabile.',
        },
      ],
    },
    {
      id: 'training-branch-example',
      title: 'Esempio completo: il ramo "Formazione"',
      blocks: [
        {
          type: 'steps',
          items: [
            'Radice: Nome "Formazione", Nessun padre, Funzione aziendale "Formazione". Spegni Prevede un contratto, lascia Multipla, spegni Selezionabile (è solo un contenitore).',
            'Attributi comuni: in Attributi Offerta aggiungi "Ore complessive" come Obbligatorio: tutto il ramo lo eredita.',
            '"GOL": padre "Formazione"; regole e funzione sono già bloccate. Spegni Selezionabile, attiva Visibile nei report e scegli le Colonne report utili.',
            'Regionali: "GOL - Lombardia" e "GOL - Lazio", padre "GOL", selezionabili: ereditano report, colonne e "Ore complessive". Su "GOL - Lazio" spegni Visibile nei report (compare Forzato).',
            '"Autofinanziato": padre "Formazione", non selezionabile, con in più "Modalità di svolgimento" in Attributi Offerta. Sotto, una figlia selezionabile per regione ("Autofinanziato - Campania", "Autofinanziato - Lazio", "Autofinanziato - Lombardia", "Autofinanziato - Sicilia") con i corsi di quella regione.',
            '"DIL": se ha campi tutti suoi, spegni Eredita dal padre nel blocco Attributi Offerta.',
            'Layout: su "Formazione" apri Layout attributi, contesto Offerta, Tutte le modalità: sezione "Dati corso" a 2 colonne con "Ore complessive" a Metà. Tutte le sottocategorie lo useranno.',
            'Gestori: su "Formazione" imposta G.A. 1 = "Tutor" e G.A. 2 = "Operatore".',
          ],
        },
        {
          type: 'paragraph',
          text: 'Risultato: un\'offerta su "GOL - Lombardia" chiede "Ore complessive", chiusa positivamente non apre un contratto, compare nel report sotto GOL e può essere assegnata agli utenti competenti per la funzione "Formazione".',
        },
      ],
    },
    {
      id: 'common-messages-and-solutions',
      title: 'Messaggi comuni e soluzioni',
      blocks: [
        {
          type: 'table',
          headers: ['Messaggio', 'Causa', 'Cosa fare'],
          rows: [
            ['"Il nome è obbligatorio."', 'Nome vuoto.', 'Scrivi il nome.'],
            ['"Questa categoria ha sottocategorie o prodotti."', 'Eliminazione di una categoria in uso.', 'Sposta sottocategorie e prodotti, oppure spegni Attiva.'],
            ['"La destinazione è una delle categorie selezionate…"', 'Destinazione uguale a una categoria da spostare.', "Scegli un'altra destinazione."],
            ['"La selezione contiene categorie annidate…"', 'Hai selezionato un padre e un suo figlio.', 'Deseleziona il figlio: segue il padre.'],
            ['"La destinazione si trova dentro una delle categorie selezionate…"', 'Sposteresti una categoria sotto una sua discendente.', 'Scegli una destinazione fuori dal ramo.'],
            ['"Alcune categorie sovrascriverebbero la funzione aziendale ereditata…"', 'Le categorie spostate hanno una funzione propria e la destinazione ne eredita già una.', 'Togli prima la loro funzione, poi sposta.'],
            ['"Ereditata da …. Per modificarla, agisci su quella categoria."', 'Regola o funzione decisa più in alto.', 'Modifica la categoria indicata.'],
            ['"Questa categoria prodotto non è selezionabile."', 'Scelta una categoria contenitore in un altro modulo.', 'Scegli una sottocategoria o attiva Selezionabile.'],
            ['"Questa categoria prodotto non è attiva."', 'Scelta una categoria non attiva (o di un ramo non attivo) in un altro modulo.', 'Scegli un\'altra categoria o attiva Attiva.'],
            ['"Questo prodotto appartiene a una categoria non attiva."', 'Scelto un prodotto di un ramo non attivo.', 'Scegli un altro prodotto o riattiva la categoria.'],
            ['"Questa opportunità è gestita su una singola categoria prodotto…"', 'Ramo in modalità Singola.', 'Usa una sola riga o passa la radice a Multipla.'],
            ['"La categoria prodotto di questa opportunità ammette una sola offerta: ne esiste già una."', 'Offerta unica per opportunità attiva.', "Modifica l'offerta esistente."],
            ['"Il layout non è valido…"', 'Sezione senza titolo, campo posizionato due volte o sezione legata a un campo non ammesso.', 'Correggi e salva di nuovo.'],
          ],
        },
      ],
    },
    {
      id: 'best-practices',
      title: 'Buone pratiche',
      blocks: [
        {
          type: 'list',
          items: [
            "Progetta l'albero prima di caricare i prodotti: radici per area di business, contenitori al secondo livello, categorie selezionabili in fondo.",
            'Decidi le regole sulla radice e ricontrollale prima di salvare.',
            'Assegna la funzione aziendale una sola volta, il più in alto possibile.',
            'Metti gli attributi comuni in alto e quelli specifici in basso.',
            'Disegna il layout sulla radice e personalizza solo dove serve.',
            'Per ritirare una categoria spegni Attiva invece di eliminarla: sparisce con il suo ramo dai campi di inserimento e lo storico resta valido.',
            'Dopo ogni modifica importante apri Visualizza e controlla i badge Ereditata da … e Forzato.',
          ],
        },
      ],
    },
  ],
}

export default guide
