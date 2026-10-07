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
          text: "Il modulo di creazione ha lo stesso aspetto del dettaglio dell'offerta: le stesse sezioni e righe, **chiuse**. Apri una riga con la matita, compila il campo e premi **Fatto** per tenerlo (o **Ripristina** per riportarlo com'era). Il **Codice** è già proposto.",
        },
        {
          type: 'steps',
          items: [
            "Apri **Offerte** e clicca **Nuova offerta** (oppure parti dalla scheda dell'opportunità).",
            "Nei **Dati offerta** scegli l'**Opportunità**: Commerciale, Segnalatore, Supervisore, Gestori account e Sede operativa vengono precompilati dall'opportunità (puoi cambiarli).",
            'Compila le altre righe che ti servono (vedi tabella).',
            'In fondo, nella scheda **Offerta** aggiungi le righe dei prodotti venduti e nella scheda **Costi** le righe di costo: le griglie sono già aperte e il riepilogo si aggiorna mentre scrivi.',
            'Clicca **Salva** (in alto o in fondo): QNet controlla tutti i campi e crea l\'offerta; gli errori compaiono sotto le righe da correggere.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi', 'Note'],
          rows: [
            [
              'Dati offerta',
              '**Codice**, **Titolo**, **Opportunità**',
              "L'opportunità non si cambia dopo la creazione.",
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
          text: "Le **Informazioni aggiuntive** compaiono appena una riga offerta ha un prodotto che le prevede. Uscendo senza salvare (Annulla, chiusura del pannello, un link) ti viene chiesta conferma.",
        },
        {
          type: 'note',
          text: "Chi inserisci tra i **Gestori account** riceve la notifica **Sei stato inserito come Gestore Account** (campanella ed email) con il titolo e i dati dell'offerta. Il link apre l'offerta, oppure la richiesta in **Gestione Richieste** se non ha accesso alle Offerte. La ricevono anche i gestori che l'offerta eredita dall'opportunità quando la crei senza indicarli; chi la riceve non riceve la notifica dell'opportunità. Non la ricevono chi viene solo spostato di posizione e chi esegue il salvataggio.",
        },
        {
          type: 'tip',
          text: "Il **Titolo** è facoltativo: se lo lasci vuoto QNet usa il codice dell'offerta seguito dai prodotti delle sue righe di ricavo (per esempio QUO-0042 - ISO 9001 + SOA) e lo aggiorna quando cambi le righe. Se scrivi un titolo tuo resta quello; svuota il campo per tornare al titolo automatico.",
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
          text: "Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure si sceglie solo in creazione (Codice, Opportunità). Anagrafica, Referente, Fonte, Funzioni aziendali e Note generali vengono dall'opportunità e restano in sola lettura.",
        },
        {
          type: 'list',
          items: [
            "Le righe **Offerta** e **Costi** hanno una matita ciascuna: si apre la griglia di modifica e il riepilogo sotto mostra i totali di ciò che stai scrivendo. Se i nuovi prodotti portano altre Informazioni aggiuntive, compaiono sotto la griglia: compilale nello stesso salvataggio (quelle obbligatorie servono per salvare).",
            "I **Buoni** si modificano dalla riga **Segnalatore**. Cambiando Commerciale, Segnalatore o Supervisore QNet aggiorna da sé le commissioni delle righe.",
            "Cambiando la **Società** la **Società sede** viene svuotata nello stesso salvataggio.",
          ],
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
            ['**Prodotto** / **Codice**', 'Il prodotto scelto.'],
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
            'Nella scheda **Offerta** scegli solo prodotti **Vendibile** (prezzo proposto: prezzo di vendita); nella scheda **Costi** solo prodotti **Utilizzabile come costo** (prezzo proposto: costo).',
            'Scelto il prodotto, QNet precompila unità, prezzo e IVA: puoi modificarli.',
            '**Descrizione aggiuntiva** aggiunge un testo alla riga, stampabile nel preventivo.',
            "Le **Commissioni** di ogni riga si possono aprire e modificare solo per quella riga. Il popup mostra in alto imponibile della riga, costi imputati, base di calcolo e totale commissioni; per ogni ruolo un'etichetta indica l'origine della commissione (**Regola: Prodotto**, **Regola: Categoria** o **Regola personale** se viene da una regola del Configuratore, **Modifica manuale** se è stata cambiata a mano; passando sopra l'etichetta compare la spiegazione). In modifica, ogni ruolo mostra anche il **Calcolo di sistema** (quanto prevede il Configuratore Commissioni): se hai cambiato il valore a mano puoi tenerlo oppure tornare al calcolo con **Applica calcolo di sistema**.",
            'Ogni riga di costo ha un **Prodotto associato**: scegli "Nessuno (costo generico)" oppure una riga del tab Prodotti, per imputare quel costo a quella vendita. Eliminando la riga prodotto associata, il costo torna automaticamente "Nessuno".',
            'Al massimo 200 righe per scheda.',
          ],
        },
        {
          type: 'paragraph',
          text: "I prodotti sono limitati alle categorie dell'opportunità; **Mostra tutti i prodotti** apre l'intero catalogo (la nuova categoria viene aggiunta all'opportunità).",
        },
        {
          type: 'paragraph',
          text: 'Il riepilogo mostra, in quest\'ordine, **Ricavi attesi**, **Costi attesi**, il **Riepilogo Commissioni** e il **Margine atteso** (somma dei margini delle righe meno i costi generici: una riga con commissione Fornitore **ricevuta** ha come ricavo la sola commissione, le altre sono imponibile meno costi e commissioni pagate; i **Ricavi attesi** non cambiano), oltre al **Riepilogo per Tipologia Prodotto**. Le commissioni a percentuale si calcolano sul margine della riga prodotto (ricavo netto meno i costi imputati a quella riga, mai sotto zero): il **Margine per prodotto** mostra ricavo, costo imputato, commissioni e margine di ogni riga prodotto (sulle righe con commissione ricevuta compare "ricavo = commissione ricevuta"), più una riga "Costi generici" per i costi non associati. Il blocco si apre con **Mostra dati avanzati**, sotto le card del riepilogo, ed è visibile solo a chi può vedere le commissioni.',
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
          text: "Portando l'offerta in uno stato **Chiuso con esito positivo** nasce il contratto.",
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
  ],
}

export default guide
