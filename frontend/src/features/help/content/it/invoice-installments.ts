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
        { type: 'paragraph', text: 'La colonna **Cliente** mostra il nome seguito dalla **partita IVA** (es. *Rossi Srl - 01234567890*); se l’anagrafica non ha la partita IVA compare il **codice fiscale**, se non ha nessuno dei due solo il nome.' },
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
            'Sulla riga di una rata non incassata fai doppio clic sulla cella **Data scadenza** o **Modalità** (serve il permesso di modifica).',
            'Cambia il valore e conferma: si salva subito solo quella cella. Esc annulla.',
            'Le rate con un incasso restano in sola lettura: annulla prima l’incasso.',
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
      id: 'write-off',
      title: 'Abbuono e stralcio',
      blocks: [
        { type: 'paragraph', text: 'Se nella finestra **Registra incasso** scrivi un importo **minore** della rata, il residuo si gestisce con tre scelte: **Spalma sulle rate successive**, **Nuova scadenza per il residuo** oppure **Abbuono / stralcio**, che chiude la differenza senza incassarla.' },
        { type: 'list', items: ['**Abbuono**: la differenza è un arrotondamento o una differenza minima, da perdonare.', '**Stralcio**: il credito non è recuperabile e si cancella.', 'Scegli il **Motivo** (obbligatorio) e, se vuoi, scrivi una **Nota (facoltativa)**. L’**Importo dell’abbuono** non si scrive: è calcolato da solo come differenza tra la rata e l’importo indicato.', 'Se il residuo è entro la **soglia** (predefinita 5 euro) viene proposto **Abbuono**; sopra la soglia viene proposta **Nuova scadenza**. Puoi sempre cambiare scelta.', 'Lo **stralcio totale** si fa con importo **0**: è ammesso solo con **Abbuono / stralcio**, e il conto non serve.'] },
        { type: 'paragraph', text: 'La rata risulta **Incassata** con l’etichetta **Abbuono** o **Stralcio** e l’importo chiuso. **Annulla incasso** cancella anche l’abbuono e ripristina la rata.' },
        { type: 'note', text: 'L’abbuono **non muove i saldi di cassa e banca** e non genera provvigioni: la rata si chiude, ma nessun denaro entra su un conto. Non è disponibile nel **Incasso multiplo**.' },
      ],
    },
    {
      id: 'collection-account',
      title: 'Il conto dell’incasso',
      blocks: [
        { type: 'paragraph', text: 'Nella finestra di **Registra incasso** c’è il campo **Conto** (facoltativo): indica su quale banca, cassa o carta è entrato l’incasso. Viene **proposto il conto della fattura**; puoi cambiarlo o svuotarlo, e in quel caso vale il conto della fattura. L’incasso compare così nell’**Estratto conto** di quel conto.' },
        { type: 'note', text: '**Annulla incasso** azzera anche il conto scelto. Un incasso senza alcun conto (né sulla scadenza né sulla fattura) finisce nel riquadro **Da verificare** dell’Estratto conto.' },
      ],
    },
    {
      id: 'bulk-collection',
      title: 'Incasso multiplo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per registrare in un colpo solo i pagamenti di un cliente su più scadenze usa l’**incasso multiplo**. È disponibile solo quando l’elenco è **raggruppato per Cliente**.',
        },
        {
          type: 'steps',
          items: [
            'Da **Raggruppa per** spunta **Cliente**: accanto alle scadenze da incassare compaiono le caselle di selezione.',
            'Espandi il cliente e spunta le scadenze pagate. Devono essere tutte **dello stesso cliente**.',
            'Apri **Azioni** nella barra strumenti e scegli **Incassa selezionate**.',
            'Nella finestra imposta la **Data incasso** (una sola per tutte le righe, di default oggi) e scrivi per ogni scadenza l’**importo incassato**. In fondo vedi il **Totale incassato** accanto al **Totale selezionato**.',
            'Premi **Salva**.',
          ],
        },
        {
          type: 'list',
          items: [
            'I campi importo partono **vuoti**: una riga lasciata vuota o a 0 **non viene incassata**.',
            'L’importo non può **superare** quello della scadenza: il campo va in errore e **Salva** resta disabilitato.',
            'Se scrivi un importo **minore** della scadenza, la scadenza si chiude a quell’importo e nasce una **scadenza residua** con la differenza e la **stessa data di scadenza** di quella di partenza (se la data è già passata, il residuo risulta subito scaduto).',
            'Il campo **Conto** è facoltativo: se resta **vuoto**, ogni incasso va sul conto della **propria fattura**; se lo scegli, vale per tutte le righe.',
            'Il salvataggio è **tutto o niente**: se una riga non è valida (ad esempio la scadenza è stata incassata nel frattempo) non viene registrato nulla e la riga viene segnalata.',
          ],
        },
        {
          type: 'note',
          text: 'Per annullare uno di questi incassi usa **Annulla incasso** sulla singola scadenza: il piano rate torna com’era e la scadenza residua viene eliminata.',
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
            ['Modificare **Data scadenza** e **Modalità** nelle celle', 'Modifica scadenze (e permesso sul singolo campo)'],
            ['**Registra / Annulla incasso**, **Incasso multiplo**', 'Registra incassi'],
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
    {
      id: 'reconciled-lock',
      title: 'Incassi spuntati sull\'estratto della banca',
      blocks: [
        { type: 'paragraph', text: 'Quando l\'incasso di una rata e\' **spuntato** nell\'estratto conto, **Annulla incasso** (e una nuova registrazione) e\' disabilitato e ne viene mostrato il motivo. Togli prima la spunta nell\'estratto.' },
      ],
    },
    {
      id: 'compensation',
      title: 'Compensazione',
      blocks: [
        { type: 'paragraph', text: 'Su una rata aperta l’azione **Compensa** apre la finestra con la rata già scelta: scegli la rata passiva della stessa anagrafica e società. Serve il permesso di incasso **e** quello di pagamento. Se i residui sono diversi scegli **Spalma** o **Nuova rata** per il resto.' },
        { type: 'paragraph', text: 'Su una rata compensata **Annulla incasso** è disabilitato e ne viene mostrato il motivo: annulla la compensazione dal **Partitario** dell’anagrafica.' },
      ],
    },
    {
      id: 'link-filter',
      title: 'Aprire la pagina gia\' filtrata',
      blocks: [
        { type: 'paragraph', text: 'L\'indirizzo accetta `?quick=` con **unpaid**, **due**, **overdue**, **paid** o **all**: la pagina si apre con quel filtro rapido attivo (un valore sconosciuto lascia quello predefinito). Il Quadro finanziario lo usa: **Aperto** apre **unpaid**, **A scadere** apre **due**, **Scaduto** e le fasce di scaduto aprono **overdue**.' },
      ],
    },
  ],
}

export default guide
