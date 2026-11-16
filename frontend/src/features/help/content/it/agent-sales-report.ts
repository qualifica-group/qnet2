import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'agent-sales-report',
  title: 'Report Vendite Agenti',
  summary:
    'Il Report Vendite Agenti elenca le offerte con cliente, commerciale, segnalatore, fase e imponibile, e permette di filtrarle e di scaricarle in tre file Excel.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Statistiche › Report Vendite Agenti**. Mostra subito l’elenco delle offerte, senza dover cercare prima: serve a vedere e scaricare il venduto per commerciale, cliente e periodo. È di sola lettura: non si crea, non si modifica e non si elimina nulla da qui.',
        },
        {
          type: 'list',
          items: [
            'Serve il permesso **Report Vendite Agenti › Visualizza**, che si assegna in **Configurazione › Sistema › Ruoli**: senza, la voce di menu non compare e la pagina mostra l’accesso negato.',
            'Ognuno vede solo le offerte a cui ha accesso nel modulo **Offerte**: il report non allarga mai la tua visibilità, e vale anche per i file scaricati.',
            'Il pulsante **Visualizza** (l’occhio) nella colonna **Azioni** apre l’offerta in una finestra sopra il report: chiudendola torni all’elenco con i tuoi filtri. Serve il permesso di vedere le Offerte. Con Ctrl/Cmd+clic, o cliccando il **N. offerta**, si apre invece la pagina dell’offerta.',
          ],
        },
      ],
    },
    {
      id: 'columns',
      title: 'Le colonne',
      blocks: [
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['N. offerta', 'Il codice dell’offerta.'],
            ['Data creazione', 'La data dell’offerta.'],
            ['Data accettazione', 'La data in cui l’offerta è entrata in uno stato vinto (chiuso con esito positivo). Resta vuota se l’offerta non è vinta; se torna in uno stato non vinto si svuota, se passa tra due stati vinti non cambia.'],
            ['Azienda, Indirizzo, CAP, Città, Telefono, Email', 'Anagrafica del cliente: indirizzo e contatti principali.'],
            ['Titolo', 'Il titolo dell’offerta.'],
            ['Linea prodotto', 'La categoria madre dei prodotti venduti (ISO, SOA, GDPR, Sicurezza, Gare, APL, Formazione…). Se l’offerta non ha ancora prodotti, quella delle categorie indicate sull’opportunità. Più valori sono separati da virgola.'],
            ['Categoria prodotto', 'La categoria dei prodotti venduti (per esempio ISO - 14001), con la stessa regola della Linea prodotto.'],
            ['Unità aziendale', 'La sede aziendale dell’offerta.'],
            ['Responsabili', 'I gestori account dell’offerta.'],
            ['Fase', 'Lo stato di lavorazione dell’offerta.'],
            ['Imponibile', 'L’importo dell’offerta al netto dell’IVA.'],
            ['Commerciale, Segnalatore', 'Le persone che hanno venduto e segnalato l’offerta.'],
            ['Imponibile al netto segnalatore', 'L’imponibile meno le provvigioni del segnalatore sulle righe dell’offerta (la percentuale del segnalatore applicata a ogni prodotto). Le provvigioni di commerciale, supervisore e fornitore non vengono tolte.'],
          ],
        },
        {
          type: 'note',
          text: 'La colonna Data scadenza del vecchio QNet non c’è: i prodotti non hanno una durata in mesi.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtrare e cercare',
      blocks: [
        {
          type: 'paragraph',
          text: 'Usa la ricerca in alto per cercare nel titolo e nel numero offerta, i filtri delle colonne (per esempio **Azienda**, **Linea prodotto**, **Categoria prodotto**, **Commerciale**, **Fase**, **Data accettazione**) e i **filtri salvati** per ritrovare le tue combinazioni abituali. Il numero di offerte trovate compare nella barra sopra la tabella.',
        },
        {
          type: 'tip',
          text: 'Tabella ed esportazioni usano gli stessi filtri e lo stesso ordinamento: quello che vedi è quello che scarichi.',
        },
      ],
    },
    {
      id: 'exports',
      title: 'Le tre esportazioni',
      blocks: [
        {
          type: 'steps',
          items: [
            'Applica i filtri che ti servono.',
            'Apri il menu con i tre puntini della barra e scegli una voce sotto **Esporta**.',
            'Per **Dettagli** e **Righe dettaglio** il file Excel parte subito; per **Sommario** scegli il formato e attendi la preparazione.',
          ],
        },
        {
          type: 'table',
          headers: ['Voce', 'Contenuto'],
          rows: [
            ['Sommario', 'Le colonne visibili della tabella, una riga per offerta, nello stesso ordine.'],
            ['Dettagli', 'Per ogni offerta un blocco: i dati dell’offerta (con gli importi Consulenza ed Ente), le righe di vendita con categoria, prodotto, quantità, prezzo e provvigioni, e il totale delle provvigioni con il netto.'],
            ['Righe dettaglio', 'Un elenco piatto: una riga per ogni riga di vendita delle offerte filtrate, con le provvigioni di supervisore, commerciale e segnalatore e il netto.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'In Dettagli e Righe dettaglio contano solo le righe di vendita (non i costi) e il netto è l’importo meno le provvigioni di supervisore, commerciale e segnalatore.',
        },
        {
          type: 'warning',
          text: 'Se le offerte filtrate sono troppe, Dettagli e Righe dettaglio non partono e compare un messaggio: restringi i filtri e riprova.',
        },
      ],
    },
  ],
}

export default guide
