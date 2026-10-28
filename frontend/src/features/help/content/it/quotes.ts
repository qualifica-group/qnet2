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
            "Apri **Offerte** e clicca **Nuova offerta** (oppure parti dalla scheda dell'opportunità).",
            "Nei **Dati offerta** scegli l'**Opportunità**: Commerciale, Segnalatore, Supervisore, Gestori account e Sede operativa vengono precompilati dall'opportunità (puoi cambiarli).",
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
              '**Codice**, **Titolo**, **Data offerta**, **Opportunità**',
              "La data offerta è proposta con la data di oggi. L'opportunità non si cambia dopo la creazione.",
            ],
            ['Anagrafica e contatti', '**Commerciale**, **Segnalatore** (con i suoi **Buoni**)', "Precompilati dall'opportunità."],
            ['Team', '**Supervisore**, **Gestori account**', "Sincronizzati con l'opportunità."],
            ['Società e sedi', '**Società**, **Società sede**, **Sede operativa**', 'Scegli prima la società.'],
            ['Documento e pagamento', '**Layout**, **Metodo di pagamento**', 'Il layout predefinito è già proposto.'],
            ['Note interne', '**Note interne**', 'Non sono visibili al cliente.'],
          ],
        },
        {
          type: 'note',
          text: "Le **Informazioni aggiuntive** compaiono, sotto le righe **Offerta** e **Costi**, appena una riga offerta ha un prodotto che le prevede. Alcune sezioni compaiono solo quando un campo sì/no è attivo: spento il campo, la sezione sparisce, i suoi obbligatori non servono e al salvataggio i suoi valori vengono svuotati. Uscendo senza salvare (Annulla, chiusura del pannello, un link) ti viene chiesta conferma.",
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
      ],
    },
    {
      id: 'editing-a-quote',
      title: "Modificare un'offerta",
      blocks: [
        {
          type: 'paragraph',
          text: "Non esiste una pagina di modifica separata: l'offerta si modifica **direttamente dal suo dettaglio**, un campo alla volta, comprese le **Informazioni aggiuntive** (ogni campo flessibile ha la sua riga) e le righe **Offerta** e **Costi**.",
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
          text: "Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure si sceglie solo in creazione (Codice, Opportunità). Anagrafica, Referente, Fonte, Linee prodotto e Note generali vengono dall'opportunità e restano in sola lettura.",
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
      id: 'list-editing',
      title: "Modifica rapida dall'elenco",
      blocks: [
        {
          type: 'paragraph',
          text: "Un clic su una cella modificabile (Titolo, Stato, Commerciale, Segnalatore, Supervisore, Gestori account, Società, Società sede, Sede operativa) la modifica **direttamente nell'elenco**, con le **stesse regole del dettaglio**: un valore non valido o un campo che non puoi modificare viene rifiutato con un messaggio e la cella torna al valore di prima.",
        },
        {
          type: 'note',
          text: "Lo **Stato** offre solo gli stati del workflow dell'offerta e chiede la **nota** quando lo stato scelto la richiede. Cambiando la **Società** la Società sede si svuota; la Società sede si sceglie tra le sedi della società della riga. Cambiando un ruolo le commissioni delle righe si ricalcolano come dal dettaglio; un Titolo vuoto torna al titolo automatico.",
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
            ['**Prezzo unitario**', 'Non negativo, al massimo 2 decimali.'],
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
            'La scheda **Costi** mostra tutto il catalogo dei prodotti di costo, senza filtro per categoria, e lo stesso prodotto si può aggiungere più volte.',
            '**Descrizione aggiuntiva** aggiunge un testo alla riga, stampabile nel preventivo.',
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
        { type: 'table', headers: ["Permesso", "Offerte visibili"], rows: [["**Visualizza**", "Quelle di cui sei **Supervisore** o **Gestore account**."], ["**Visualizza per sede**", "Le tue e quelle della tua **Sede operativa** (fisica o remota). Un'offerta senza sede operativa conta solo per il suo team."], ["**Visualizza tutti**", "Tutte."]] },
        { type: 'paragraph', text: "Il filtro vale per elenco, scheda, filtri, esportazioni, statistiche e dashboard, selettori e documenti. Aprire il link di un'offerta che non puoi vedere mostra un errore di accesso. Vedere un'offerta non permette di modificarla o eliminarla: servono i permessi **Modifica** ed **Elimina**, e si modifica solo ciò che si vede." },
        { type: 'note', text: "Se crei un'offerta senza far parte del suo team, non la vedrai più a meno di avere **Visualizza tutti** o **Visualizza per sede** (con la sede operativa giusta)." },
      ],
    },
  ],
}

export default guide
