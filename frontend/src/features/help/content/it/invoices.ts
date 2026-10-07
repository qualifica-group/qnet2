import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'invoices',
  title: 'Fatture Attive',
  summary: 'Proforma e fatture emesse ai clienti, con scadenze, incassi e totali.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Contabilità › Attiva › Fatture Attive**. Elenca i documenti emessi: usa le schede **Tutte**, **Proforma** e **Fatture**, scegli l\'**anno** e, nella barra dei mesi, seleziona uno o più mesi (ognuno mostra il numero di documenti e il totale) oppure **tutti**.' },
        { type: 'paragraph', text: 'In fondo all\'elenco trovi i **totali del filtro corrente**: imponibile, IVA, totale, incassato e residuo. Puoi anche filtrare, ordinare ed esportare l\'elenco.' },
      ],
    },
    {
      id: 'issue',
      title: 'Emettere un proforma',
      blocks: [
        { type: 'paragraph', text: 'I proforma si emettono dalla finestra **Emissione Proforma**, aperta dall\'azione **Emetti proforma** sulle righe di **Richieste Proforma** (vedi la guida Richieste Proforma).' },
        {
          type: 'table',
          headers: ['Parte', 'Cosa fare'],
          rows: [
            ['**Intestazione**', 'Data documento, società emittente (non modificabile in modifica), intestatario, modalità di pagamento, banca della società, tag **Preventivo/Consuntivo**, note e note interne.'],
            ['**Righe disponibili**', 'Le righe della commessa non ancora usate: **Aggiungi** una riga oppure **Aggiungi tutte**.'],
            ['**Righe del documento**', 'Puoi modificare quantità, prezzo e IVA; ogni riga è un prodotto a catalogo oppure una descrizione libera.'],
            ['**Scadenze**', 'Anteprima delle rate calcolata dalla modalità di pagamento (vedi la guida Modalità di Pagamento).'],
            ['**Totali**', 'Imponibile, IVA e totale del documento.'],
          ],
        },
        { type: 'paragraph', text: 'Con **Emetti** il documento riceve un numero progressivo per società emittente e anno, nel formato **N/AAAA**, e la richiesta di origine passa a **Evasa**.' },
      ],
    },
    {
      id: 'status',
      title: 'Stato dei pagamenti',
      blocks: [
        {
          type: 'table',
          headers: ['Pallino', 'Significato'],
          rows: [
            ['Pagata', 'Tutte le scadenze sono incassate.'],
            ['Non scaduta', 'Ci sono scadenze aperte, nessuna ancora scaduta.'],
            ['Scaduta fino a 21 giorni', 'Almeno una scadenza aperta è scaduta da non più di 21 giorni.'],
            ['Scaduta oltre 21 giorni', 'Almeno una scadenza aperta è scaduta da più di 21 giorni.'],
          ],
        },
      ],
    },
    {
      id: 'collections',
      title: 'Righe, scadenze e incassi',
      blocks: [
        {
          type: 'steps',
          items: [
            'Espandi la riga del documento per vedere **Righe** e **Scadenze**.',
            'Su una scadenza premi **Registra incasso** e compila la finestra.',
            'Per correggere un incasso premi **Annulla incasso** sulla stessa scadenza e conferma nella finestra che si apre.',
          ],
        },
        { type: 'paragraph', text: '**Registra incasso** compare solo sulle scadenze non ancora incassate; su una scadenza incassata trovi **Annulla incasso**.' },
        { type: 'paragraph', text: '**Incasso parziale.** Se l\'importo incassato è inferiore alla rata, la rata si chiude all\'importo incassato e devi scegliere cosa fare del residuo:' },
        {
          type: 'table',
          headers: ['Scelta', 'Effetto'],
          rows: [
            ['**Spalma sulle rate successive**', 'Il residuo è diviso in parti uguali tra le rate successive non incassate. Non è disponibile se non ci sono rate successive da incassare.'],
            ['**Nuova scadenza per il residuo**', 'Il residuo diventa una nuova rata subito dopo quella incassata, con la data che indichi (proposta: scadenza della rata + 30 giorni). Le rate successive scalano di una posizione.'],
          ],
        },
        { type: 'paragraph', text: '**Annullare un incasso** che aveva ridistribuito il residuo ripristina esattamente il piano precedente (importi e numerazione delle rate; l\'eventuale rata residua viene eliminata).' },
        { type: 'warning', text: 'Gli incassi si annullano in **ordine inverso**: se una rata toccata dalla ridistribuzione è stata incassata dopo, devi prima **annullare gli incassi successivi**. Se nel frattempo il documento è stato rimodulato, l\'annullamento azzera solo l\'incasso e le rate restano come sono.' },
      ],
    },
    {
      id: 'actions',
      title: 'Azioni sulla riga',
      blocks: [
        {
          type: 'table',
          headers: ['Azione', 'Cosa fa'],
          rows: [
            ['**Visualizza**', 'Apre il documento in sola lettura.'],
            ['**Modifica**', 'Riapre la finestra di emissione, anche con incassi presenti (**rimodulazione**): data documento, cliente e modalità di pagamento diventano di sola lettura, le rate incassate restano e il residuo (nuovo totale meno incassato) è diviso in parti uguali tra le rate non incassate, che mantengono la scadenza. Il totale non può essere inferiore all\'incassato; se è uguale, le rate aperte vengono eliminate. Se non ci sono rate aperte e il totale cresce, viene creata una nuova rata a +30 giorni dall\'ultima scadenza. L\'anteprima delle scadenze mostra già il piano rimodulato.'],
            ['**Dettagli**', 'Registra **numero e data esterni** di Fatture in Cloud: il proforma diventa **Fattura**. Svuotandoli torna proforma. Qui trovi anche tag, scostamento e note interne.'],
            ['**Elimina**', 'Rimuove il documento dopo conferma. Non è possibile se ha incassi.'],
            ['**Attività**', 'Mostra lo storico delle modifiche.'],
          ],
        },
        { type: 'warning', text: 'Se elimini un proforma, la richiesta di origine torna **Da evadere** e può essere emessa di nuovo, ma il numero eliminato non viene riutilizzato.' },
      ],
    },
    {
      id: 'pdf',
      title: 'Scaricare il PDF',
      blocks: [
        { type: 'paragraph', text: 'Con **Scarica PDF** sulla riga (o nel dettaglio) QNet genera il documento usando il **layout Fatture attivo e predefinito** (vedi la guida Layout).' },
        { type: 'warning', text: 'Se non esiste un layout del modulo **Fatture** attivo e predefinito, compare un errore: creane uno in **Configurazione › Layout**.' },
      ],
    },
    {
      id: 'email',
      title: 'Inviare il documento per email',
      blocks: [
        {
          type: 'steps',
          items: [
            'Sulla riga premi **Invia email**: si apre il composer.',
            'Il **destinatario** è la **PEC** del cliente, se presente, altrimenti la sua **email**; il **PDF** è già allegato.',
            'Completa oggetto e corpo (puoi scegliere un **Modello email** del modulo Fatture) e invia.',
          ],
        },
        { type: 'paragraph', text: 'Nel dettaglio del documento la scheda **Email** mostra lo storico delle email inviate. Servono i permessi per **inviare** e **visualizzare** le email delle fatture.' },
      ],
    },
    {
      id: 'reminder',
      title: 'Sollecitare un pagamento',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Sollecita** sulla riga: l\'azione è attiva solo se almeno una scadenza è **scaduta**.',
            'QNet crea una **bozza di sollecito** con il PDF allegato e apre il composer.',
            'Scegli un **Modello email** del modulo Fatture: può usare le variabili della **elenco delle scadenze scadute** e dell\'**importo scaduto**.',
            'Controlla e invia.',
          ],
        },
        { type: 'paragraph', text: 'La colonna **Ultimo sollecito** dell\'elenco indica quando è stato inviato l\'ultimo sollecito.' },
      ],
    },
  ],
}

export default guide
