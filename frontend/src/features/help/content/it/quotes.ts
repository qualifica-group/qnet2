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
          type: 'steps',
          items: [
            "Apri **Offerte** e clicca **Nuova offerta** (oppure parti dalla scheda dell'opportunità).",
            'Compila i dati principali (vedi tabella).',
            "Nella scheda **Offerta** aggiungi le righe dei prodotti venduti.",
            'Nella scheda **Costi** aggiungi le righe di costo.',
            'Nella scheda **Note e pagamenti** indica il metodo di pagamento e le note interne.',
            'Clicca **Salva**.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi', 'Note'],
          rows: [
            [
              'Dati offerta',
              '**Codice**, **Titolo**, **Opportunità**, **Commerciale**, **Segnalatore**',
              'Lascia vuoto il codice per generarlo in automatico.',
            ],
            ['Team', '**Supervisore**, **Gestori account**', "Sincronizzati con l'opportunità."],
            ['Società e sedi', '**Società**, **Società sede**, **Sede operativa**', 'Scegli prima la società.'],
            ['Layout documento', '**Layout**', 'Il modello usato per il PDF.'],
            ['Stato', '**Stato**, **Nota**', 'Vedi sotto.'],
            ['Note e pagamenti', '**Metodo di pagamento**, **Note interne**', 'Le note interne non sono visibili al cliente.'],
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
            'Le **Commissioni** di ogni riga si possono aprire e modificare solo per quella riga.',
            'Al massimo 200 righe per scheda.',
          ],
        },
        {
          type: 'paragraph',
          text: "I prodotti sono limitati alle categorie dell'opportunità; **Mostra tutti i prodotti** apre l'intero catalogo (la nuova categoria viene aggiunta all'opportunità).",
        },
        {
          type: 'paragraph',
          text: 'Il riepilogo mostra **Ricavi attesi**, **Costi attesi** e **Margine atteso**, con **Imponibile**, **IVA** e **Totale**, più il **Riepilogo Commissioni** e il **Riepilogo per Tipologia Prodotto**.',
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
            'Apri l\'offerta in modifica.',
            'Nella sezione **Stato** scegli il nuovo stato.',
            'Se lo stato è segnato **Nota richiesta**, scrivi la **Nota** (viene registrata tra le note dell\'opportunità).',
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
