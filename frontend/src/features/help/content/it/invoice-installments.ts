import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'invoice-installments',
  title: 'Scadenze',
  summary: 'Tutte le rate delle fatture attive in un unico elenco: filtra, raggruppa, incassa e sposta le scadenze.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Contabilità › Attiva › Scadenze**. Mostra **le rate di tutte le fatture attive** (le scadenze create all’emissione o dalla rimodulazione di una fattura) in un elenco unico, con cliente, commessa, società, sedi, importo, incassato e residuo.',
        },
        {
          type: 'paragraph',
          text: 'Le scadenze **non si creano né si eliminano** da qui: nascono e spariscono solo dalla fattura. Da questo elenco puoi **aprire la fattura**, **registrare o annullare un incasso** e **cambiare data e modalità** di una rata aperta.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e filtro rapido',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sopra l’elenco il **filtro rapido** sceglie quali rate vedere: **Da incassare** (predefinito: rate non ancora incassate), **In scadenza** (da incassare con scadenza non ancora passata), **Scadute** (da incassare con scadenza passata), **Incassate** oppure **Tutte**. Il filtro rapido agisce sulle colonne **Stato** e **Scaduta**: se le cambi a mano dai filtri di colonna, nessuna scheda risulta selezionata.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['**Stato**', 'Da incassare, Incassata. Un incasso parziale chiude la rata all’importo incassato e sposta il residuo su un’altra rata.'],
            ['**Scaduta / Giorni di ritardo**', 'Una rata aperta con scadenza prima di oggi è scaduta; i giorni di ritardo contano dalla scadenza.'],
            ['**Importo / Importo incassato / Residuo**', 'Importo della rata, quanto già incassato e quanto resta.'],
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
            'Apri **Raggruppa per** sopra la tabella e spunta una o più colonne (**Cliente**, **Commessa**, **Società**, **Sede aziendale**, **Sede operativa**, **Codice modalità** o **Mese scadenza**): l’ordine in cui le spunti è l’ordine dei livelli.',
            'Ogni gruppo mostra il numero di rate e i totali di **Importo**, **Incassato** e **Residuo**: espandilo per vedere i livelli successivi o le rate.',
            'Puoi raggruppare fino a **3 livelli**; togli un livello con la **x** del suo chip o usa **Rimuovi raggruppamento** per tornare all’elenco piatto.',
          ],
        },
        {
          type: 'note',
          text: 'Le rate senza sede (offerta senza sede aziendale o operativa) finiscono nel gruppo senza valore. I filtri attivi valgono anche per i gruppi e i loro totali.',
        },
      ],
    },
    {
      id: 'totals',
      title: 'Totali',
      blocks: [
        {
          type: 'paragraph',
          text: 'In fondo all’elenco trovi i totali di **Importo**, **Incassato** e **Residuo** calcolati su **tutte** le rate del filtro corrente, non solo sulla pagina visibile. Se non hai il permesso di vedere un importo, il relativo totale non compare.',
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
            'Sulla riga di una rata aperta premi **Modifica scadenza** (serve il permesso di modifica).',
            'Cambia la **data di scadenza** e/o il **codice della modalità** di pagamento, poi premi **Salva**.',
            'L’elenco si aggiorna da solo.',
          ],
        },
        {
          type: 'list',
          items: [
            'La nuova data non può essere precedente alla **data del documento** della fattura.',
            'Il codice modalità deve essere quello di una modalità di pagamento esistente; lascia vuoto per nessuna.',
            'L’**importo** non si cambia da qui: rimodula la fattura dalla sua modifica.',
          ],
        },
        {
          type: 'warning',
          text: 'Una rata con un **incasso** non è modificabile: il salvataggio viene rifiutato. Annulla prima l’incasso, poi modifica la scadenza.',
        },
        {
          type: 'paragraph',
          text: 'Se una rimodulazione successiva della fattura riguarda rate aperte, la data e la modalità che hai impostato vengono **conservate**.',
        },
      ],
    },
    {
      id: 'collections',
      title: 'Incassi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le azioni **Registra incasso** (sulle rate aperte) e **Annulla incasso** (su quelle incassate) sono le stesse del dettaglio fattura, con le stesse regole: per un incasso parziale scegli se **spalmare il residuo** sulle rate successive o creare **una nuova scadenza** per il residuo; l’annullamento chiede conferma e ripristina il piano precedente.',
        },
        {
          type: 'paragraph',
          text: 'L’azione **Apri fattura** mostra il documento in un pannello laterale, senza lasciare l’elenco. Serve il permesso di incasso per le azioni di incasso e quello di visualizzazione fatture per aprire il documento.',
        },
      ],
    },
    {
      id: 'export',
      title: 'Esportazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il pulsante **Esporta** della barra strumenti produce un file **CSV o Excel** con le rate del filtro, della ricerca e dell’ordinamento correnti. L’esportazione è **sempre piatta**: il raggruppamento non si riflette nel file (le colonne di gruppo, come Cliente o Commessa, sono comunque esportabili). Le colonne che non puoi vedere sono escluse. Serve il permesso di esportazione.',
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
            ['Vedere l’elenco e il menu **Scadenze**', 'Visualizzazione scadenze'],
            ['**Modifica scadenza**', 'Modifica scadenze (e permesso sul singolo campo)'],
            ['**Registra / Annulla incasso**', 'Registra incassi'],
            ['**Apri fattura**', 'Visualizzazione fatture attive'],
            ['**Esporta**', 'Esportazione scadenze'],
          ],
        },
        {
          type: 'note',
          text: 'Importi, incassato e residuo possono essere nascosti da permessi di campo: in quel caso le colonne, i totali e i raggruppamenti che li usano non sono disponibili.',
        },
      ],
    },
  ],
}

export default guide
