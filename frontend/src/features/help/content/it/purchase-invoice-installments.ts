import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-invoice-installments',
  title: 'Scadenze passive',
  summary: 'Tutte le rate da pagare ai fornitori in un unico elenco: filtra, raggruppa, paga e sposta le scadenze.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Contabilità › Passiva › Scadenze**. Mostra **le rate di tutte le fatture e proforma passive** in un elenco unico, con fornitore, ODA collegati, commessa, società, sedi, importo, pagato e residuo.',
        },
        {
          type: 'paragraph',
          text: 'Le scadenze **non si creano né si eliminano** da qui. Da questo elenco puoi **registrare o annullare un pagamento** e **cambiare data e modalità** di una rata da pagare.',
        },
        {
          type: 'paragraph',
          text: 'Con **Apri documento** sulla riga apri la scheda della **fattura o proforma passiva** a cui appartiene la rata, dalla pagina **Contabilità › Passiva › Fatture passive**. Serve il permesso di visualizzazione delle fatture passive. Le rate nascono dal documento: vedi la guida **Fatture passive**.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e filtro rapido',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sopra l’elenco il **filtro rapido** sceglie quali rate vedere: **Da pagare** (predefinito: rate non ancora pagate), **In scadenza** (da pagare con scadenza non ancora passata), **Scadute** (da pagare con scadenza passata), **Pagate** oppure **Tutte**. Il filtro rapido agisce sulle colonne **Stato** e **Scaduta**: se le cambi a mano dai filtri di colonna, nessuna scheda risulta selezionata.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['**Stato**', 'Da pagare, Pagata. Un pagamento parziale chiude la rata all’importo pagato e sposta il residuo su un’altra rata.'],
            ['**Scaduta / Giorni di ritardo**', 'Una rata da pagare con scadenza prima di oggi è scaduta; i giorni di ritardo contano dalla scadenza.'],
            ['**Importo / Importo pagato / Residuo**', 'Importo della rata, quanto già pagato e quanto resta.'],
            ['**Conto di pagamento**', 'Il conto finanziario da cui è uscito il pagamento.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Come in ogni elenco puoi ordinare le colonne, cercare, usare i **filtri avanzati** e salvare le **viste filtro**.',
        },
      ],
    },
    {
      id: 'grouping',
      title: 'Raggruppamento',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Raggruppa per** sopra la tabella e spunta una o più colonne (**Fornitore**, **Tipo di costo**, **Commessa**, **Società**, **Sede aziendale**, **Sede operativa**, **Codice modalità**, **Conto di pagamento** o **Mese scadenza**): l’ordine in cui le spunti è l’ordine dei livelli.',
            'Ogni gruppo mostra il numero di rate e i totali di **Importo**, **Pagato** e **Residuo**: espandilo per vedere i livelli successivi o le rate.',
            'Puoi raggruppare fino a **3 livelli**; togli un livello con la **x** del suo chip o usa **Rimuovi raggruppamento** per tornare all’elenco piatto.',
          ],
        },
        {
          type: 'note',
          text: 'Le rate senza valore nella colonna raggruppata finiscono nel gruppo senza valore. I filtri attivi valgono anche per i gruppi e i loro totali.',
        },
      ],
    },
    {
      id: 'totals',
      title: 'Totali',
      blocks: [
        {
          type: 'paragraph',
          text: 'In fondo all’elenco trovi i totali di **Importo**, **Pagato** e **Residuo** calcolati su **tutte** le rate del filtro corrente, non solo sulla pagina visibile. Se non hai il permesso di vedere un importo, il relativo totale non compare.',
        },
      ],
    },
    {
      id: 'edit-due-date',
      title: 'Modificare la scadenza',
      blocks: [
        {
          type: 'steps',
          items: [
            'Sulla riga di una rata da pagare premi **Modifica scadenza** (serve il permesso di modifica).',
            'Cambia la **data di scadenza** e/o scegli la **modalità** di pagamento dall’elenco, poi premi **Salva**.',
            'L’elenco si aggiorna da solo.',
          ],
        },
        {
          type: 'list',
          items: [
            'La nuova data non può essere precedente alla **data del documento**.',
            'La modalità si sceglie tra quelle di pagamento esistenti; **Nessuna** la svuota.',
            'L’**importo** non si cambia da qui.',
          ],
        },
        {
          type: 'warning',
          text: 'Una rata già **pagata** non è modificabile: il salvataggio viene rifiutato. Annulla prima il pagamento, poi modifica la scadenza.',
        },
      ],
    },
    {
      id: 'payments',
      title: 'Pagamenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Con **Registra pagamento** (sulle rate da pagare) indichi **importo pagato**, **data di pagamento** e **conto di pagamento**. Il conto proposto è quello del documento; l’elenco mostra i conti della **società del documento** (o senza società): un conto di un’altra società viene rifiutato.',
        },
        {
          type: 'paragraph',
          text: 'Se l’importo è **minore** della rata, la rata si chiude a quell’importo e scegli cosa fare del residuo: **spalmarlo** sulle rate successive ancora da pagare (disponibile solo se ne esistono) oppure creare **una nuova scadenza** con la data che indichi (proposta: scadenza + 30 giorni).',
        },
        {
          type: 'paragraph',
          text: '**Annulla pagamento** (sulle rate pagate) chiede conferma e ripristina il piano precedente. Se una delle rate toccate è stata pagata dopo, l’annullamento viene rifiutato: annulla prima i pagamenti successivi.',
        },
      ],
    },
    {
      id: 'bulk-payment',
      title: 'Pagamento multiplo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per registrare in un colpo solo i pagamenti a un fornitore su più scadenze usa il **pagamento multiplo**. È disponibile solo quando l’elenco è **raggruppato per Fornitore**.',
        },
        {
          type: 'steps',
          items: [
            'Da **Raggruppa per** spunta **Fornitore**: accanto alle scadenze da pagare compaiono le caselle di selezione.',
            'Espandi il fornitore e spunta le scadenze. Devono essere tutte **dello stesso fornitore** (al massimo 100).',
            'Apri **Azioni** nella barra strumenti e scegli **Paga selezionate**.',
            'Nella finestra imposta la **Data pagamento** e il **Conto di pagamento** (uno solo per tutte le righe) e scrivi per ogni scadenza l’**importo pagato**. In fondo vedi il **Totale pagato** accanto al **Totale selezionato**.',
            'Premi **Salva**.',
          ],
        },
        {
          type: 'list',
          items: [
            'I campi importo partono **vuoti**: una riga lasciata vuota o a 0 **non viene pagata**.',
            'L’importo non può **superare** quello della scadenza.',
            'Se scrivi un importo **minore**, la scadenza si chiude a quell’importo e nasce una **scadenza residua** con la differenza e la **stessa data di scadenza**.',
            'Il salvataggio è **tutto o niente**: se una riga non è valida (ad esempio è stata pagata nel frattempo, o il conto non è della società di un documento) non viene registrato nulla e la riga viene segnalata.',
          ],
        },
        {
          type: 'note',
          text: 'Per annullare uno di questi pagamenti usa **Annulla pagamento** sulla singola scadenza.',
        },
      ],
    },
    {
      id: 'export',
      title: 'Esportazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il pulsante **Esporta** della barra strumenti produce un file **CSV o Excel** con le rate del filtro, della ricerca e dell’ordinamento correnti. L’esportazione è **sempre piatta**: il raggruppamento non si riflette nel file. Le colonne che non puoi vedere sono escluse. Serve il permesso di esportazione.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'table',
          headers: ['Azione', 'Permesso'],
          rows: [
            ['Vedere l’elenco e il menu **Scadenze** (Passiva)', 'Visualizzazione scadenze passive'],
            ['**Modifica scadenza**', 'Modifica scadenze passive (e permesso sul singolo campo)'],
            ['**Registra / Annulla pagamento**, **Pagamento multiplo**', 'Registra pagamenti (fatture passive)'],
            ['**Apri documento**', 'Visualizzazione fatture passive'],
            ['**Esporta**', 'Esportazione scadenze passive'],
          ],
        },
        {
          type: 'note',
          text: 'Importi, pagato, residuo, data e conto di pagamento possono essere nascosti da permessi di campo: in quel caso le colonne, i totali e i raggruppamenti che li usano non sono disponibili.',
        },
      ],
    },
  ],
}

export default guide
