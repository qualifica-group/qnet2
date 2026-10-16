import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-billing',
  title: 'Fatturazione',
  summary: 'Quanto resta da fatturare sulle commesse aperte, per funzione aziendale, società o commerciale e per età, e il ciclo delle proforma: emesse, convertite in fattura, ancora aperte e tempi medi.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Fatturazione** risponde alle domande “quanto valore delle commesse aperte devo ancora fatturare?” e “come vanno le proforma?”. La trovi in **Controllo di gestione › Fatturazione**. È **solo di consultazione** e mostra dati aggregati, senza elenchi per documento.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi 4 riquadri: **Commesse aperte**, **Contratto**, **Fatturato** e **Da fatturare**. Sotto, il da fatturare per dimensione, per età della commessa e il ciclo delle proforma.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e periodo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Puoi restringere per **società**, **sede società** e **sede operativa**, e scegliere come raggruppare il da fatturare: per **funzione aziendale**, **società** o **commerciale**. Il **periodo** (di default dal 1 gennaio a oggi) vale **solo per il ciclo delle proforma**: il da fatturare è sempre la situazione a oggi.',
        },
        {
          type: 'note',
          text: 'Le commesse chiuse forzatamente non compaiono nel da fatturare. Le proforma non sono fatturato: nel da fatturare contano solo le fatture.',
        },
      ],
    },
    {
      id: 'backlog',
      title: 'Da fatturare per dimensione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni riga trovi il numero di **commesse**, il **contratto** (ricavo delle righe assegnate), il **fatturato** (imponibile delle fatture collegate) e il **da fatturare**, cioè la differenza. Un importo **negativo** indica una commessa fatturata oltre il contratto. Le commesse senza ricavo vanno in **Non attribuito**. Il grafico affianca contratto e fatturato di ogni riga.',
        },
        {
          type: 'note',
          text: 'Per funzione aziendale una commessa può comparire in più righe, ripartita con le sue quote: il conteggio delle commesse per riga non si somma, il totale sì.',
        },
      ],
    },
    {
      id: 'age',
      title: 'Da fatturare per età',
      blocks: [
        {
          type: 'paragraph',
          text: 'La tabella raggruppa il da fatturare per **mesi trascorsi dall’inizio della commessa**: 0-3, 3-6, 6-12 e oltre 12 mesi. Le fasce sono sempre quattro, anche se vuote, e la loro somma coincide con il totale.',
        },
      ],
    },
    {
      id: 'proforma',
      title: 'Ciclo delle proforma',
      blocks: [
        {
          type: 'paragraph',
          text: 'Tre riquadri mostrano le proforma **emesse** e **convertite in fattura** nel periodo e quelle **ancora aperte** a oggi, con importo (sul totale del documento) e numero di documenti. Due riquadri riportano i **tempi medi** da proforma a fattura e da fattura a incasso: se non ci sono dati mostrano **n.d.**.',
        },
        {
          type: 'paragraph',
          text: 'Le tabelle mostrano le proforma aperte per **età** (0-30, 31-60, 61-90 e oltre 90 giorni) e il ciclo per **tipologia di richiesta** (Consulenza, Ente, Senza richiesta).',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Serve il permesso **Controllo di gestione** (dal form Ruoli), lo stesso delle altre pagine del modulo. Chi lo ha vede i dati di **tutte** le società. Senza il permesso la voce di menu non compare.',
        },
      ],
    },
  ],
}

export default guide
