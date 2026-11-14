import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'quotes',
  title: 'Offerte',
  summary: "L'offerta è la proposta economica al cliente, sempre collegata a un'opportunità.",
  sections: [
    {
      id: 'create-a-quote',
      title: "Creare un'offerta",
      blocks: [
        {
          type: 'paragraph',
          text: "Il modulo di creazione ha lo stesso aspetto del dettaglio dell'offerta: le stesse sezioni e righe, **chiuse**. Apri una riga con la matita, compila il campo e premi **Fatto** per tenerlo (o **Ripristina** per riportarlo com'era). Il **Codice** è già proposto. Il codice proposto è indicativo: se lo lasci così e intanto un altro utente lo usa, al salvataggio ricevi il primo codice libero.",
        },
        {
          type: 'steps',
          items: [
            "Apri **Offerte** e clicca **Nuova offerta** (oppure parti dalla scheda dell'opportunità). Se puoi creare anche le opportunità, prima si apre una finestra: scegli **Da un'opportunità esistente** (i passi seguenti) oppure **Nuova opportunità + offerta** (vedi la sezione dedicata).",
            "Nei **Dati offerta** scegli l'**Opportunità**: Commerciale, Segnalatore, Supervisore, Gestori account e Sede operativa vengono precompilati dall'opportunità (puoi cambiarli); la **Fonte** si precompila se l'opportunità è nata da un lead.",
            'Compila le altre righe che ti servono (vedi tabella).',
            'In fondo, nella scheda **Offerta** aggiungi le righe dei prodotti venduti scegliendoli dal **Catalogo prodotti** e nella scheda **Costi** le righe di costo: gli editor sono già aperti e il riepilogo si aggiorna mentre scrivi.',
            'Clicca **Salva** (in alto o in fondo): QNet controlla tutti i campi e crea l\'offerta; gli errori compaiono sotto le righe da correggere.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi', 'Note'],
          rows: [
            [
              'Dati offerta',
              '**Codice**, **Titolo**, **Data offerta**, **Data ricontatto**, **Opportunità**',
              "La data offerta è proposta con la data di oggi. La data ricontatto è facoltativa. L'opportunità non si cambia dopo la creazione.",
            ],
            ['Contesto', '**Fonte**', "Campo dell'offerta: precompilata con la Fonte del lead se l'opportunità ne ha uno, altrimenti vuota; puoi sceglierla o cambiarla."],
            ['Anagrafica e contatti', '**Commerciale**, **Segnalatore** (con i suoi **Buoni**)', "Precompilati dall'opportunità."],
            ['Team', '**Supervisore**, **Gestori account**', "Sincronizzati con l'opportunità."],
            ['Società e sedi', '**Società**, **Società sede**, **Sede operativa**', 'Scegli prima la società.'],
            ['Documento e pagamento', '**Layout**, **Metodo di pagamento**', 'Il layout predefinito è già proposto.'],
            ['Note interne', '**Note interne**', 'Non sono visibili al cliente.'],
          ],
        },
        {
          type: 'note',
          text: "Le **Informazioni aggiuntive** compaiono, sotto le righe **Offerta** e **Costi**, appena l'opportunità scelta o una riga offerta hanno una categoria che le prevede: sono le stesse che vedi in **Gestione Richieste** per la stessa richiesta, con gli stessi valori. Alcune sezioni compaiono solo quando un campo sì/no è attivo: spento il campo, la sezione sparisce, i suoi obbligatori non servono e al salvataggio i suoi valori vengono svuotati. Uscendo senza salvare (Annulla, chiusura del pannello, un link) ti viene chiesta conferma.",
        },
        {
          type: 'note',
          text: "Chi inserisci tra i **Gestori account** riceve la notifica **Sei stato inserito come Gestore Account** (campanella ed email) con il titolo e i dati dell'offerta. Il link apre l'offerta, oppure la richiesta in **Gestione Richieste** se non ha accesso alle Offerte. La ricevono anche i gestori che l'offerta eredita dall'opportunità quando la crei senza indicarli; chi la riceve non riceve la notifica dell'opportunità. Non la ricevono chi viene solo spostato di posizione e chi esegue il salvataggio.",
        },
        {
          type: 'tip',
          text: "Il **Titolo** è facoltativo: se lo lasci vuoto QNet usa il codice dell'offerta seguito dai prodotti delle sue righe di ricavo (per esempio QUO-0042 - ISO 9001 + SOA) e lo aggiorna quando cambi le righe. Se scrivi un titolo tuo resta quello; svuota il campo per tornare al titolo automatico.",
        },
        {
          type: 'note',
          text: "La **Data offerta** è obbligatoria e si può cambiare anche dal dettaglio. Un'offerta creata da **Gestione Richieste** o dalla conversione di un lead prende la data del giorno in cui viene creata.",
        },
        {
          type: 'note',
          text: "La **Data ricontatto** è una data facoltativa per segnarti quando riprendere in mano l'offerta: si imposta e si cambia dal dettaglio o dall'elenco, e si svuota cancellando la data. Non invia promemoria ed è diversa dal **Prossimo richiamo**, che si pianifica da **Gestione Richieste**.",
        },
      ],
    },
    {
      id: 'create-quote-with-opportunity',
      title: "Creare un'opportunità e la sua offerta insieme",
      blocks: [
        {
          type: 'paragraph',
          text: "Quando la trattativa non esiste ancora, puoi crearla insieme all'offerta in un'unica procedura. È disponibile solo se hai i permessi per creare sia le offerte sia le opportunità: altrimenti **Nuova offerta** apre direttamente il modulo dell'offerta.",
        },
        {
          type: 'steps',
          items: [
            'In **Offerte** clicca **Nuova offerta** e scegli **Nuova opportunità + offerta**.',
            "Nel **passo 1 - Opportunità** compili gli stessi campi della **Nuova opportunità** (senza il lead: la conversione da lead resta nei moduli Lead e Opportunità). Clicca **Avanti**.",
            "Se i campi obbligatori non sono compilati, compaiono gli errori e non si avanza. Se l'anagrafica ha già un'**opportunità aperta** che blocca la nuova, compare un avviso con **Crea l'offerta su questa opportunità** (apre il modulo dell'offerta già collegato) e **Vai all'opportunità**: la procedura non prosegue.",
            "Nel **passo 2 - Offerta** compili gli stessi campi della **Nuova offerta**. Commerciale, Segnalatore, Supervisore, Sede operativa e Gestori account sono già precompilati dal passo 1 e puoi cambiarli. L'**Opportunità** risulta **nuova**: viene creata al salvataggio. Le **Informazioni aggiuntive** seguono le linee prodotto del passo 1.",
            "Clicca **Salva**: opportunità e offerta vengono create insieme e si apre l'offerta. Se qualcosa non va, non viene creato nulla; gli errori ti riportano al passo e al campo da correggere.",
          ],
        },
        {
          type: 'note',
          text: "**Indietro** conserva tutto quello che hai scritto. Se dal passo 1 cambi Commerciale, Segnalatore, Supervisore, Sede operativa o Gestori account, il passo 2 si riallinea solo sui campi che non hai modificato a mano. Prima del salvataggio non viene creato nulla: uscendo dalla pagina perdi quanto inserito (ti viene chiesta conferma). I Gestori account dell'offerta entrano anche nel team della nuova opportunità.",
        },
      ],
    },
    {
      id: 'editing-a-quote',
      title: "Modificare un'offerta",
      blocks: [
        {
          type: 'paragraph',
          text: "Le schede di Opportunità, Offerte, Contratti e Commesse hanno le stesse sezioni nello stesso posto. Qui trovi **Dati offerta** e **Contesto**, poi **Anagrafica e contatti** e **Buoni**, poi **Team** e **Società e sedi**, infine **Documento e pagamento**. La sezione Buoni resta visibile anche vuota (\"Nessun buono\").",
        },
        {
          type: 'paragraph',
          text: "Non esiste una pagina di modifica separata: l'offerta si modifica **direttamente dal suo dettaglio**, un campo alla volta, comprese le **Informazioni aggiuntive** (ogni campo flessibile ha la sua riga) e le righe **Offerta** e **Costi**. Cliccando il titolo **Informazioni aggiuntive** la sezione si chiude o si riapre: la scelta resta salvata nel browser e vale per tutte le offerte, nel dettaglio e nella creazione.",
        },
        {
          type: 'steps',
          items: [
            "Apri l'offerta dall'elenco.",
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            "Premi **Salva** (o Invio nei campi di testo) per salvare solo quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) per chiuderlo lasciandolo com'era, senza salvare.",
          ],
        },
        {
          type: 'tip',
          text: "Le **Note interne** stanno nel riquadro giallo in cima al dettaglio, come le Note generali in Gestione Richieste: se sono vuote il riquadro invita a scriverle; con la matita (o un clic sul testo) le scrivi direttamente nel riquadro. Subito sotto, in un riquadro uguale ma in sola lettura, trovi le **Note generali dell'opportunità**.",
        },
        {
          type: 'note',
          text: "Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure si sceglie solo in creazione (Codice, Opportunità). Anagrafica, Referente, Linee prodotto e Note generali vengono dall'opportunità e restano in sola lettura; la **Fonte** è un campo dell'offerta e si modifica qui.",
        },
        {
          type: 'list',
          items: [
            "Le righe **Offerta** e **Costi** hanno una matita ciascuna: si apre l'editor con il catalogo e le righe, e il riepilogo sotto mostra i totali di ciò che stai scrivendo. Se i nuovi prodotti portano altre Informazioni aggiuntive, compaiono sotto la griglia: compilale nello stesso salvataggio (quelle obbligatorie servono per salvare).",
            "I **Buoni** si modificano dalla riga **Segnalatore**. Cambiando Commerciale, Segnalatore o Supervisore QNet aggiorna da sé le commissioni delle righe.",
            "Cambiando la **Società** la **Società sede** viene svuotata nello stesso salvataggio.",
          ],
        },
        {
          type: 'paragraph',
          text: "Il pulsante **Ricalcola commissioni**, in alto a destra accanto al PDF, riallinea le commissioni dell'offerta alle regole attuali del Configuratore Commissioni (per esempio dopo aver cambiato il verso di una regola Fornitore). Il riepilogo indica quante righe, commissioni e commissioni cambiate a mano sono coinvolte; devi scegliere una risposta, nessuna è preselezionata: **Aggiorna solo le commissioni da regola** (quelle cambiate a mano restano, con l'importo ricalcolato) oppure **Rigenera tutte dalle regole** (anche quelle cambiate a mano vengono sostituite). Si rilegge anche l'interruttore **Calcolo commissione Fornitore** della tipologia del prodotto; margine e riepilogo si aggiornano subito. Il pulsante compare solo se puoi modificare le commissioni e l'offerta non è chiusa.",
        },
      ],
    },
    {
      id: 'people-filters',
      title: "Filtrare per persona",
      blocks: [
        {
          type: 'paragraph',
          text: "Nel pannello **Filtri avanzati** (pulsante nella barra sopra l'elenco) i campi **Supervisore** e **Gestori account** permettono di scegliere una o più persone: compaiono le offerte in cui almeno una di loro ha quel ruolo. Se compili entrambi i campi, l'offerta deve soddisfarli tutti e due.",
        },
      ],
    },
    {
      id: 'list-filters',
      title: "Filtri predefiniti e categoria",
      blocks: [
        {
          type: 'paragraph',
          text: "All'apertura la pagina **Offerte** mostra tutti i record che il tuo profilo ti permette di vedere, senza un filtro **Assegnazione**: con il permesso di vedere tutto vedi tutto, altrimenti vedi le offerte dove sei **Supervisore** o **Gestore account** (e quelle della tua sede o della tua competenza, se il profilo lo prevede). Nel pannello **Filtri avanzati** la **Categoria madre** parte vuota, e vuota vale per tutte le categorie madri in cui sei abilitato: se sei abilitato a tutte vedi le offerte di ogni categoria, altrimenti solo le offerte delle tue categorie (con tutte le loro sottocategorie). Aprendo l'elenco del campo trovi solo le categorie madri che puoi scegliere: tutte se sei abilitato a tutto, altrimenti solo le tue. Scegline una o più e premi **Applica** per restringere: la scelta resta salvata. Il campo **Cerca** trova le offerte anche per nome del cliente (l'anagrafica): basta una parte del nome, per esempio \"napoli\".",
        },
        {
          type: 'paragraph',
          text: "Le categorie in cui sei abilitato sono le categorie di competenza del tuo profilo (l'ambito di assegnazione). Se non hai categorie di competenza **Categoria madre** non limita nulla. I filtri non allargano mai ciò che il tuo profilo può vedere.",
        },
        {
          type: 'paragraph',
          text: "Nello stesso pannello c'è anche **Categoria prodotto** (solo quella categoria). Rientra in una categoria un'offerta che la ha tra le **righe di ricavo** dell'offerta.",
        },
        {
          type: 'paragraph',
          text: "Le categorie con **Visibile in** su **Gestione Richieste / Iscritti** non fanno parte di questa pagina: non compaiono in **Categoria madre** e **Categoria prodotto**, e le offerte che hanno **solo** categorie di questo tipo non sono nell'elenco né nelle statistiche (il dettaglio resta apribile, per esempio da un contratto). Se un'offerta ha anche una categoria usata qui resta visibile.",
        },
        {
          type: 'paragraph',
          text: "Le colonne dei campi flessibili compaiono in fondo all'elenco quando il filtro di categoria indica una sola categoria: una sola voce in **Categoria prodotto** oppure, se quella è vuota, una sola categoria madre attuale in **Categoria madre** (per esempio ISO). Scegliendo una categoria madre attuale entrano nell'elenco anche i record delle categorie vecchie collegate (per esempio [OLD] ISO), che nel filtro non compaiono come voci a parte, e le colonne sono prima quelle della categoria attuale, poi quelle delle vecchie. Con due categorie madri attuali diverse vedi i record di entrambe ma nessuna colonna; lo stesso con la **Categoria madre** vuota. Per vedere solo i record di una categoria vecchia o solo di una nuova usa **Categoria prodotto**. Un campo con lo stesso nome in più categorie può comparire due volte. Le colonne si ordinano, si filtrano e si esportano; se hai il permesso di modificare le offerte si modificano nella cella, tranne quando il campo non riguarda quell'offerta (la cella resta in sola lettura). I valori restano leggibili anche nel dettaglio.",
        },
        {
          type: 'note',
          text: "Il limite alle tue categorie con la **Categoria madre** vuota vale solo nella pagina **Offerte**: il campo non compare dentro una scheda e nelle sezioni Offerte di opportunità e anagrafiche. L'esportazione segue i filtri attivi.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Modifica rapida dall'elenco",
      blocks: [
        {
          type: 'paragraph',
          text: "Un clic su una cella modificabile (Titolo, Stato, Commerciale, Segnalatore, Supervisore, Gestori account, Società, Società sede, Sede operativa, Fonte, Data ricontatto) la modifica **direttamente nell'elenco**, con le **stesse regole del dettaglio**: un valore non valido o un campo che non puoi modificare viene rifiutato con un messaggio e la cella torna al valore di prima.",
        },
        {
          type: 'note',
          text: "Lo **Stato** offre solo gli stati del workflow dell'offerta e chiede la **nota** quando lo stato scelto la richiede. Cambiando la **Società** la Società sede si svuota; la Società sede si sceglie tra le sedi della società della riga. Cambiando un ruolo le commissioni delle righe si ricalcolano come dal dettaglio; un Titolo vuoto torna al titolo automatico.",
        },
        {
          type: 'paragraph',
          text: "La colonna **Anagrafica** mostra il cliente dell'opportunità collegata: si può filtrare e ordinare ma non modificare.",
        },
        {
          type: 'paragraph',
          text: "Togliendo o aggiungendo persone nei **Gestori account** gli altri restano nella loro posizione. Restano in sola lettura Codice, Opportunità, Ricavo/Costo/Margine netto, Creato il, Prossimo richiamo, Avviso e Linee di prodotto (i prodotti delle righe di ricavo, con la categoria tra parentesi) e Categoria madre (la macrocategoria dei prodotti, ad esempio Formazione; filtrabile).",
        },
      ],
    },
    {
      id: 'offer-and-cost-lines',
      title: 'Righe offerta e righe di costo',
      blocks: [
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['**Prodotto** / **Codice**', 'Il prodotto scelto dal catalogo.'],
            ['**Quantità**', 'Maggiore di zero, al massimo 2 decimali.'],
            ['**Unita**', "L'unità di misura."],
            ['**Prezzo unitario**', 'Al massimo 2 decimali. Può essere negativo per applicare uno sconto: aggiungi il prodotto **SCONTO** con quantità 1 e prezzo, per esempio, -100; la riga riduce imponibile e totale dell\'offerta e non genera provvigioni.'],
            ['**IVA**', "L'aliquota della riga."],
            ['**Imponibile**, **IVA**, **Totale**', 'Calcolati in automatico.'],
          ],
        },
        {
          type: 'list',
          items: [
            "Ogni scheda ha a sinistra il **Catalogo prodotti** e a destra le righe (**Righe dell'offerta** / **Righe di costo**); su schermi stretti stanno uno sotto l'altro. Cerca per nome o descrizione: ogni prodotto mostra nome, codice, tipologia, prezzo con unità di misura (nella scheda Costi il prezzo di costo), percorso della categoria e descrizione.",
            'Spunta uno o più prodotti e premi **Aggiungi N selezionati**: nasce una riga per prodotto con quantità 1 e prezzo (vendita o costo) e IVA del prodotto, modificabili. Nella scheda **Offerta** vengono caricate anche le provvigioni predefinite di ogni prodotto: se il caricamento fallisce non viene aggiunta nessuna riga e la selezione resta, per riprovare.',
            "Ogni riga è una card su due livelli: prodotto, codice e tipologia; sotto quantità × prezzo unitario, IVA e imponibile/IVA/totale. In fondo trovi il **Totale offerta** / **Totale costi**. Nel dettaglio dell'offerta (e del contratto) le righe salvate hanno la stessa card, in sola lettura: prodotto con tipologia e categoria, poi quantità, prezzo unitario, aliquota IVA (e per i costi il prodotto associato) accanto a imponibile/IVA/totale, e la descrizione aggiuntiva sotto.",
            "Se il prodotto ha dei fornitori, sulla riga compare **Fornitore** con i fornitori del prodotto: è già scelto il predefinito e il campo è obbligatorio. Nella scheda **Offerta** il prezzo della riga è il prezzo del fornitore scelto e cambia se cambi fornitore; accanto al fornitore compare l'avviso della commissione Fornitore, se c'è (per esempio 'Commissione 10% pagata'). Nella scheda **Costi** il prezzo è il costo del fornitore; se il fornitore ha la commissione su quel prodotto il prezzo è 0 e non modificabile, con lo stesso avviso (il costo di quel fornitore è la commissione Fornitore della riga di vendita).",
            "La commissione **Fornitore** va al fornitore scelto sulla riga. Se cambi fornitore, le commissioni della riga si ricalcolano (se ce ne sono, prima chiede conferma). Una regola provvigionale dedicata a quel fornitore si applica in automatico.",
            "Le righe già salvate tengono il proprio fornitore anche se il prodotto cambia la sua lista di fornitori.",
            'Per cambiare il prodotto di una riga, rimuovila e scegli il nuovo prodotto dal catalogo.',
            'Una riga **assegnata a una commessa** mostra "Assegnata a COM-..." ed è bloccata: nessun campo è modificabile e non si può rimuovere finché la commessa non viene eliminata. Vale anche quando modifichi le righe dal contratto o da Gestione Richieste; le provvigioni della riga si modificano comunque dall\'offerta.',
            'La scheda **Costi** mostra tutto il catalogo dei prodotti di costo, senza filtro per categoria, e lo stesso prodotto si può aggiungere più volte.',
            '**Descrizione aggiuntiva** aggiunge un testo alla riga, sia nella scheda **Offerta** sia nella scheda **Costi**; il testo è stampabile nel preventivo.',
            "Le **Commissioni** di ogni riga si possono aprire e modificare solo per quella riga. Il popup mostra in alto imponibile della riga, costi imputati, base di calcolo e totale commissioni; per ogni ruolo un'etichetta indica l'origine della commissione (**Regola: Prodotto**, **Regola: Categoria** o **Regola personale** se viene da una regola del Configuratore, **Modifica manuale** se è stata cambiata a mano; passando sopra l'etichetta compare la spiegazione). In modifica, ogni ruolo mostra anche il **Calcolo di sistema** (quanto prevede il Configuratore Commissioni): se hai cambiato il valore a mano puoi tenerlo oppure tornare al calcolo con **Applica calcolo di sistema**. La riga **Fornitore** mostra anche il **Verso**: se la commissione viene da una regola è quello della regola; se la modifichi a mano scegli tu **Ricevuta** (è il ricavo della riga) o **Pagata** (costo verso il fornitore), e il margine si aggiorna di conseguenza.",
            "Nel riepilogo, sotto le card, **Mostra dettaglio provvigioni** apre (e **Nascondi dettaglio provvigioni** richiude) il blocco **Dettaglio provvigioni per prodotto** dell'offerta salvata, che elenca ogni riga con commissioni: per **Commerciale**, **Segnalatore**, **Supervisore** e **Fornitore** il destinatario, la percentuale (o l'importo fisso) e l'importo; per il Fornitore anche il verso (**Ricevuta** o **Pagata**), con un (?) che spiega cosa significa. Il nome del destinatario porta alla sua scheda: passando sopra un utente compare la sua scheda profilo, passando sopra un referente o un'anagrafica compare il collegamento alla scheda, che si apre anche cliccando il nome (se hai il permesso di vederla). Passando sopra un importo compare il calcolo: per una percentuale la base (imponibile meno costi imputati alla riga) e la percentuale applicata, per i totali il saldo. Ogni importo ha il segno **+** se la commissione entra (Fornitore ricevuta) e **−** se esce (Commerciale, Segnalatore, Supervisore e Fornitore pagata). L'ultima riga e l'ultima colonna danno i totali come saldo: quello che entra meno quello che esce. È visibile solo a chi può vedere le commissioni.",
            'Ogni riga di costo ha un **Prodotto associato**: scegli "Nessuno (costo generico)" oppure una riga del tab Prodotti, per imputare quel costo a quella vendita. Eliminando la riga prodotto associata, il costo torna automaticamente "Nessuno".',
            'Al massimo 200 righe per scheda.',
          ],
        },
        {
          type: 'paragraph',
          text: "Nella scheda **Offerta** il catalogo mostra i prodotti delle categorie dell'opportunità, con la chip **Tutte** e una chip per categoria. La chip **Tutto il catalogo** chiede conferma e poi mostra tutto il catalogo (la nuova categoria viene aggiunta all'opportunità); cliccando **Tutte** o una categoria si torna al filtro. Un prodotto già nelle righe è **Già aggiunto** e non si può selezionare. Se l'opportunità è gestita su una sola categoria non ci sono le caselle: il clic su un prodotto sostituisce quello della riga (con conferma se le provvigioni vanno rigenerate).",
        },
        {
          type: 'paragraph',
          text: 'Il riepilogo mostra, in quest\'ordine, **Ricavi attesi**, **Costi attesi**, il **Riepilogo Commissioni** (ogni importo ha il segno **+** se la commissione entra e **−** se esce: Commerciale, Segnalatore e Supervisore sono sempre pagate; il Fornitore è **+** se ricevuto e **−** se pagato, e un\'offerta con entrambi i versi mostra le due righe **Fornitore (ricevute)** e **Fornitore (pagate)**) e il **Margine atteso** (somma dei margini delle righe meno i costi generici: una riga con commissione Fornitore **ricevuta** ha come ricavo la sola commissione, le altre sono imponibile meno costi e commissioni pagate; i **Ricavi attesi** non cambiano), oltre al **Riepilogo per Tipologia Prodotto**. Le commissioni a percentuale si calcolano sul margine della riga prodotto (ricavo netto meno i costi imputati a quella riga, mai sotto zero): il **Margine per prodotto** mostra ricavo, costo imputato, commissioni e margine di ogni riga prodotto, così che ricavo meno costo meno commissioni dia il margine (sulle righe con commissione Fornitore ricevuta compare "ricavo = commissione ricevuta": il ricavo è la commissione ricevuta e le commissioni sono solo quelle pagate), più una riga "Costi generici" per i costi non associati. Passando sopra il nome del prodotto compare il nome intero; ogni importo è sottolineato a tratti e, passandoci sopra o toccandolo, mostra il calcolo (per esempio 3.200,00 − 0,00 − 3.200,00 = 0,00). Il blocco si apre con **Mostra dati avanzati**, sotto le card del riepilogo, ed è visibile solo a chi può vedere le commissioni.',
        },
        {
          type: 'warning',
          text: "Un'offerta nuova deve avere almeno una riga prodotto; se l'opportunità è gestita su una sola categoria, una sola riga venduta. In tabella l'avviso **Righe offerta mancanti** segnala le offerte senza righe.",
        },
      ],
    },
    {
      id: 'change-the-quote-status',
      title: "Cambiare lo stato di un'offerta",
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli stati disponibili dipendono dal workflow configurato nel **Configuratore Stati Offerta**.',
        },
        {
          type: 'steps',
          items: [
            "Nel dettaglio dell'offerta premi la matita della riga **Stato** (nei Dati offerta).",
            'Scegli il nuovo stato.',
            'Se lo stato è segnato **Nota richiesta**, scrivi la **Nota** che compare sotto (viene registrata tra le note dell\'opportunità).',
            'Clicca **Salva**.',
          ],
        },
        {
          type: 'paragraph',
          text: "Portando l'offerta in uno stato **Chiuso con esito positivo** nasce il contratto. Da quel momento il pulsante **Apri contratto** in cima al dettaglio dell'offerta, e la voce **Apri contratto** nel menu delle altre azioni (i tre puntini) della riga in tabella, aprono il contratto in una finestra sopra l'offerta. Li vedi solo se il tuo ruolo può consultare i Contratti.",
        },
      ],
    },
    {
      id: 'quote-pdf',
      title: 'Preventivo in PDF',
      blocks: [
        {
          type: 'paragraph',
          text: "Apri l'offerta (o usa l'azione in tabella) e clicca **Scarica preventivo**. QNet usa il **Layout** dell'offerta o, in mancanza, il layout predefinito attivo dei preventivi.",
        },
        {
          type: 'tip',
          text: "QNet non invia l'offerta per email: scarica il PDF e invialo al cliente con i tuoi strumenti abituali.",
        },
      ],
    },
    {
      id: 'visibility',
      title: "Quali offerte vedi",
      blocks: [
        { type: 'paragraph', text: "Cosa vedi dipende dal tuo ruolo. Serve sempre il permesso **Visualizza**; i livelli si sommano:" },
        { type: 'table', headers: ["Permesso", "Offerte visibili"], rows: [["**Visualizza**", "Quelle di cui sei **Supervisore** o **Gestore account**."], ["**Visualizza per sede**", "Le tue e quelle della tua **Sede operativa** (fisica o remota). Un'offerta senza sede operativa conta solo per il suo team."], ["**Visualizza per competenza**", "Le tue e quelle con almeno un prodotto dei ricavi in una categoria del tuo ambito di assegnazione, in qualunque sede. Con **Ambito su tutte le categorie** tutte."], ["**Visualizza tutti**", "Tutte."]] },
        { type: 'paragraph', text: "Il filtro vale per elenco, scheda, filtri, esportazioni, statistiche e dashboard, selettori e documenti. Aprire il link di un'offerta che non puoi vedere mostra un errore di accesso. Vedere un'offerta non permette di modificarla o eliminarla: servono i permessi **Modifica** ed **Elimina**, e si modifica solo ciò che si vede." },
        { type: 'note', text: "Se crei un'offerta senza far parte del suo team, non la vedrai più a meno di avere **Visualizza tutti**, **Visualizza per sede** (con la sede operativa giusta) o **Visualizza per competenza** (con la categoria giusta nel tuo ambito)." },
      ],
    },
  ],
}

export default guide
