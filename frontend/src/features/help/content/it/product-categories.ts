import type { HelpGuide } from '../../types'

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
            ["Categoria padre (posizione nell'albero)", 'Decide cosa la categoria eredita. Nelle righe prodotto di progetti, campagne, opportunità e lead scegli prima la Categoria genitore (una radice) e poi una sua discendente.'],
            ['Funzione aziendale', "Ricavata in automatico nelle righe prodotto; serve per le competenze degli utenti e l'assegnazione di lead e richieste. Il prodotto la mostra in sola lettura."],
            ['Attributi Prodotto', 'Campi aggiuntivi nella scheda Prodotto.'],
            ['Attributi Offerta', "Campi nelle Informazioni aggiuntive dell'Offerta e colonne nelle schede per categoria di Gestione Richieste."],
            ['Attributi Commessa', 'Campi nelle Informazioni aggiuntive della Commessa.'],
            ['Layout attributi', 'Come questi campi sono disposti a video: sezioni, righe e larghezze.'],
            ['Modalità di gestione', 'Quante righe Categoria Prodotto può avere una scheda, in Opportunità e in Gestione Richieste.'],
            ['Offerta unica per opportunità', "Quante offerte può avere un'opportunità."],
            ['Prevede un contratto', "Se un'offerta chiusa con esito positivo apre un contratto."],
            ['Semplificazione riga offerta', 'Se in Gestione Richieste la riga offerta si compila da sola.'],
            ['Selezionabile', 'Se la categoria compare negli elenchi di scelta di prodotti, righe prodotto, progetti, campagne e regole provvigionali.'],
            ['Visibile nei report e Colonne report', 'Righe e colonne del report e della dashboard di Gestione Richieste e Gestione Iscritti.'],
            ['Gestori Account', 'Il nome di ogni livello di Gestore Account (per esempio "Tutor" invece di "Gestore account 1").'],
            ['La categoria stessa', 'Criterio dei workflow stati offerta (Categoria prodotto o Categoria prodotto (ramo)) e ambito delle regole provvigionali.'],
          ],
        },
        {
          type: 'warning',
          text: 'Una modifica a una categoria radice può cambiare il comportamento di tutto il ramo. Prima di salvare controlla il pannello Riepilogo a lato del form.',
        },
      ],
    },
    {
      id: 'list-page',
      title: 'La pagina elenco',
      blocks: [
        {
          type: 'paragraph',
          text: 'Apri Prodotti › Categorie Prodotto. Le categorie compaiono in una tabella, non ad albero: la colonna Padre dice dove si trova ciascuna.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Cosa mostra'],
          rows: [
            ['Nome', 'Il nome; la ricerca rapida cerca qui.'],
            ['Padre', 'La categoria superiore; vuota per le radici.'],
            ['Descrizione', 'Testo libero.'],
            ['Funzione aziendale', 'La funzione valida, propria o ereditata.'],
            ['Prevede preventivo, Selezionabile, Prevede un contratto', 'Sì o no.'],
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
            ['Visualizza', 'Scheda in sola lettura, con regole, attributi, gestori account e anteprima del layout.'],
            ['Modifica', 'Apre il form.'],
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
            'Clicca Nuova categoria.',
            'Compila Dettagli, lasciando Categoria padre su Nessun padre (categoria radice).',
            'Imposta le Regole di gestione: sulla radice sono tutte modificabili.',
            'Assegna gli attributi nelle tre sezioni.',
            'Se serve, dai un nome ai livelli di Gestori Account.',
            'Controlla il Riepilogo e clicca Salva. Compare "Categoria creata con successo."',
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
          text: "Regole di gestione: ogni regola è un riquadro con interruttore; l'icona (i) ne spiega l'effetto.",
        },
        {
          type: 'table',
          headers: ['Regola', 'Cosa indicare', 'Valore iniziale'],
          rows: [
            ['Prevede preventivo', 'Se il ramo lavora con le offerte.', 'No'],
            ['Modalità di gestione', 'Singola (una riga per scheda) o Multipla (più righe per scheda).', 'Multipla'],
            ['Offerta unica per opportunità', 'Se ogni opportunità può avere una sola offerta.', 'No'],
            ['Prevede un contratto', 'Se una chiusura positiva apre un contratto.', 'Sì'],
            ['Semplificazione riga offerta', 'Se in Gestione Richieste la riga offerta si compila da sola.', 'No'],
            ['Selezionabile', 'Se la categoria si può scegliere negli elenchi.', 'Sì'],
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
            'In Categoria padre cerca e scegli la categoria superiore: il form si aggiorna subito.',
            'Imposta ciò che vale solo qui: Selezionabile, Visibile nei report, Colonne report, i tuoi attributi e le etichette dei gestori.',
            'Clicca Salva.',
          ],
        },
        {
          type: 'list',
          items: [
            'Le cinque regole del ramo si bloccano con il badge Ereditata da "Nome radice".',
            'La Funzione aziendale si blocca se un antenato ne ha una.',
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
            'Si apre il form di creazione con tutti i dati della categoria: il Nome ha il suffisso " (copia)", padre, regole, attributi, gestori account e altri campi sono gli stessi.',
            'Cambia ciò che serve (almeno il Nome) e clicca Salva.',
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
            ['Decide solo la radice', 'Prevede preventivo, Modalità di gestione, Offerta unica per opportunità, Prevede un contratto, Semplificazione riga offerta', 'Tutto il ramo le segue; sulle sottocategorie sono bloccate. Cambiandole sulla radice cambiano subito per tutto il ramo.'],
            ["Dall'antenato più vicino", 'Funzione aziendale', "Vale quella dell'antenato più vicino che ne ha una; se nessuno ce l'ha, la scegli tu e da lì in giù si eredita."],
            ['Ereditata ma forzabile', 'Visibile nei report, Colonne report', 'La sottocategoria prende il valore del padre (Ereditata da …); se lo cambi compare Forzato. Riportandolo al valore del padre torna a ereditare.'],
            ['Solo per la categoria', 'Selezionabile', 'Non si eredita mai: un padre non selezionabile può avere figli selezionabili.'],
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
            ['Modalità di gestione: Singola', "Opportunità o richiesta con una sola riga Categoria Prodotto; Aggiungi riga prodotto non ne permette una seconda. Anche l'offerta ha una sola riga prodotto e un prodotto di un'altra categoria viene rifiutato."],
            ['Modalità di gestione: Multipla', 'Nessun limite di righe.'],
            ['Offerta unica per opportunità', 'Una seconda offerta sulla stessa opportunità viene rifiutata. Le opportunità che ne hanno già più di una le conservano.'],
            ['Prevede un contratto', 'Attiva: la chiusura positiva apre un contratto. Spenta: nessun contratto, ma la chiusura positiva resta possibile. I contratti già aperti restano. Se una scheda copre più categorie, basta una senza contratto per non crearlo.'],
            ['Semplificazione riga offerta', "In Gestione Richieste l'operatore sceglie solo il prodotto: quantità 1, prezzo e IVA dal prodotto. Nel modulo Offerte restano modificabili a mano."],
            ['Selezionabile spento', 'La categoria diventa un contenitore e sparisce dagli elenchi di scelta; le associazioni già fatte restano. Usandola comunque compare "Questa categoria prodotto non è selezionabile."'],
            ['Visibile nei report', 'La categoria diventa una riga del report e della dashboard di Gestione Richieste e Gestione Iscritti; sceglierla nel report include le sottocategorie. Una categoria esclusa non entra nemmeno nei totali del padre.'],
          ],
        },
      ],
    },
    {
      id: 'attributes',
      title: 'Attributi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli attributi si creano nel modulo Attributi; nella categoria decidi dove usarli. Lo stesso attributo può stare in uno, due o tutti e tre i blocchi.',
        },
        {
          type: 'table',
          headers: ['Blocco', 'Dove compaiono i campi'],
          rows: [
            ['Attributi Prodotto', 'Nella scheda Prodotto, per i prodotti della categoria.'],
            ['Attributi Offerta', "Nelle Informazioni aggiuntive dell'Offerta, quando una riga usa un prodotto della categoria; anche come colonne della scheda categoria in Gestione Richieste."],
            ['Attributi Commessa', 'Nelle Informazioni aggiuntive della Commessa, quando una riga usa un prodotto della categoria.'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Nel blocco scelto apri Assegna un attributo….',
            "Cerca per nome e selezionalo: compare nell'elenco con il suo tipo.",
            'Attiva Obbligatorio se il campo va sempre compilato.',
            'In Ordine scrivi un numero: i più bassi vengono prima.',
            'Per togliere un attributo usa Rimuovi attributo.',
            'Clicca Salva.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Sotto il tuo elenco vedi, in sola lettura, gli attributi Ereditati dalle categorie antenate.',
        },
        {
          type: 'tip',
          text: "Assegna un attributo alla categoria più alta che ne ha bisogno: tutte le sottocategorie lo riceveranno. Puoi riassegnarlo su una sottocategoria per renderlo obbligatorio solo lì; vale l'impostazione della categoria più vicina.",
        },
        {
          type: 'paragraph',
          text: 'Se togli un attributo che ha già valori, i valori nei prodotti non vengono cancellati, ma il campo non compare più e non è più obbligatorio. Lo stesso accade spegnendo Eredita dal padre.',
        },
      ],
    },
    {
      id: 'attribute-layout',
      title: 'Layout attributi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il layout decide come sono disposti i campi; senza layout compaiono in un semplice elenco.',
        },
        {
          type: 'steps',
          items: [
            'Nella riga della categoria scegli Layout attributi.',
            'Scegli il Contesto: Prodotto, Offerta o Commessa.',
            'Scegli la Modalità form: Tutte le modalità, Creazione, Modifica o Visualizzazione.',
            'Clicca Aggiungi sezione e compila Titolo (obbligatorio), Descrizione, Aspetto (Standard, In evidenza, Informativo, Secondario), Colonne (da 1 a 4), Collassabile e Chiusa di default.',
            'Clicca Aggiungi riga dentro la sezione.',
            'Trascina un campo da Attributi non posizionati nella riga.',
            'Per ogni campo scegli la Larghezza: Intera, Due terzi, Metà, Un terzo o Un quarto.',
            "Sposta sezioni e righe con le frecce e controlla l'Anteprima live.",
            'Clicca Salva layout. Compare "Layout salvato." e puoi passare a un altro contesto; Annulla chiude senza salvare.',
          ],
        },
        {
          type: 'table',
          headers: ['Situazione', 'Cosa vedi', 'Cosa puoi fare'],
          rows: [
            ['Modalità senza layout dedicato', 'Compare "Questa modalità usa il layout di Tutte le modalità."', 'Personalizza questa modalità; per tornare indietro Torna a tutte le modalità (conferma con Rimuovi).'],
            ['Categoria senza layout proprio', 'Compare "Questa categoria usa il layout di …" e l\'editor bloccato', 'Personalizza questa categoria; per tornare indietro Torna al layout ereditato.'],
          ],
        },
        {
          type: 'note',
          text: 'Se Eredita dal padre è spento in un contesto, la categoria non eredita neanche il layout di quel contesto.',
        },
        {
          type: 'tip',
          text: 'Un campo non posizionato non si perde: compare in fondo, nella sezione Altre informazioni.',
        },
      ],
    },
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
            ['N. Richiami non gestiti, N. Nuovi contatti non gestiti, N. Potenziali associati', 'Ignorano il periodo e guardano alla situazione di oggi.'],
            ['N. Telefonate Effettuate (nel periodo)', ''],
            ['N. Richiami non gestiti (nel periodo), N. Nuovi contatti non gestiti (nel periodo), N. Potenziali associati (nel periodo)', 'Versione legata al periodo delle colonne sopra.'],
            ['Aziende inserite (nel periodo)', ''],
            ['Associati, Trattative Concluse, Invio Presa in carico (nel periodo)', 'Contano le richieste passate nel periodo a uno stato chiuso con esito positivo (dettaglio in Gestione Richieste › Statistiche e report).'],
            ['Aule in gestione, Aule in partenza, Presa Appuntamenti', 'Oggi non hanno un calcolo e mostrano sempre 0.'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Attiva Visibile nei report: compare il riquadro Colonne report.',
            'Spunta le colonne che ti servono; il contatore mostra quante ne hai scelte (per esempio "5/14"). Tutte e Nessuna selezionano o svuotano.',
            'Clicca Salva.',
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
          text: 'Per spostare una categoria apri Modifica e cambia la Categoria padre (non puoi scegliere la categoria stessa né una sua discendente). Per spostarne molte:',
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
            '"Autofinanziato": padre "Formazione", selezionabile, con in più "Modalità di svolgimento" in Attributi Offerta.',
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
            ['"Questa categoria ha sottocategorie o prodotti."', 'Eliminazione di una categoria in uso.', 'Sposta sottocategorie e prodotti, oppure spegni Selezionabile.'],
            ['"La destinazione è una delle categorie selezionate…"', 'Destinazione uguale a una categoria da spostare.', "Scegli un'altra destinazione."],
            ['"La selezione contiene categorie annidate…"', 'Hai selezionato un padre e un suo figlio.', 'Deseleziona il figlio: segue il padre.'],
            ['"La destinazione si trova dentro una delle categorie selezionate…"', 'Sposteresti una categoria sotto una sua discendente.', 'Scegli una destinazione fuori dal ramo.'],
            ['"Alcune categorie sovrascriverebbero la funzione aziendale ereditata…"', 'Le categorie spostate hanno una funzione propria e la destinazione ne eredita già una.', 'Togli prima la loro funzione, poi sposta.'],
            ['"Ereditata da …. Per modificarla, agisci su quella categoria."', 'Regola o funzione decisa più in alto.', 'Modifica la categoria indicata.'],
            ['"Questa categoria prodotto non è selezionabile."', 'Scelta una categoria contenitore in un altro modulo.', 'Scegli una sottocategoria o attiva Selezionabile.'],
            ['"Questa opportunità è gestita su una singola categoria prodotto…"', 'Ramo in modalità Singola.', 'Usa una sola riga o passa la radice a Multipla.'],
            ['"La categoria prodotto di questa opportunità ammette una sola offerta: ne esiste già una."', 'Offerta unica per opportunità attiva.', "Modifica l'offerta esistente."],
            ['"Il layout non è valido…"', 'Sezione senza titolo o campo posizionato due volte.', 'Correggi e salva di nuovo.'],
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
            'Decidi le regole sulla radice e verificale nel Riepilogo prima di salvare.',
            'Assegna la funzione aziendale una sola volta, il più in alto possibile.',
            'Metti gli attributi comuni in alto e quelli specifici in basso.',
            'Disegna il layout sulla radice e personalizza solo dove serve.',
            'Per ritirare una categoria spegni Selezionabile invece di eliminarla.',
            'Dopo ogni modifica importante apri Visualizza e controlla i badge Ereditata da … e Forzato.',
          ],
        },
      ],
    },
  ],
}

export default guide
