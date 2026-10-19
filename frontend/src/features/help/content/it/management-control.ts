import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control',
  title: 'Controllo di gestione',
  summary: 'Il conto economico di ogni commessa (ricavi, costi, provvigioni, margine atteso e consuntivo), raggruppato per dimensione, più i costi di struttura non legati a nessuna commessa.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il **Controllo di gestione** risponde alla domanda “le commesse stanno rendendo quanto previsto?”. Lo trovi in **Controllo di gestione › Conto economico**. La pagina è **solo di consultazione** e mostra le cifre **al netto dell’IVA** (imponibile).',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi i **totali**, poi il **prospetto per funzione aziendale** con il grafico di confronto, i **segmenti** (se attivi) e infine i **costi di struttura**.',
        },
        {
          type: 'list',
          items: [
            '**Ricavo contrattuale**: quanto prevede il contratto, cioè la somma dei ricavi delle righe assegnate alla commessa.',
            '**Fatturato**: l’imponibile delle fatture attive emesse sulla commessa. Le proforma non contano come fatturato.',
            '**Incassato**: la parte dell’imponibile fatturato che il cliente ha già pagato, in proporzione agli incassi registrati sulle scadenze.',
            '**Costi**: da quattro fonti, sommate. I **costi manuali** inseriti sulla commessa, gli **ordini a fornitore (ODA)** ancora da fatturare (impegnato), il **fatturato passivo** collegato alla commessa e il **costo del lavoro**.',
            '**Costo del lavoro**: le ore del segnatempo registrate sulla commessa, ciascuna moltiplicata per il **costo orario** di chi le ha lavorate, calcolato dalla retribuzione della condizione contrattuale in vigore alla data dell’ora (scheda Utente, Storico contrattuale: oraria così com’è, mensile diviso 173, annua diviso 2.076). Un aumento quindi non cambia i costi dei mesi precedenti. Le ore senza commessa non entrano nel conto economico. Le ore di chi in quella data non ha una condizione con retribuzione sono **contate nelle ore ma non valorizzate**: sotto le ore compare la nota “di cui … non valorizzate”, e il costo è sottostimato finché non completi lo storico contrattuale.',
            '**Provvigioni**: quelle di commerciale, segnalatore, supervisore e le provvigioni fornitore pagate. Riducono il margine.',
            '**Margine atteso**: ricavo contrattuale meno costi preventivati meno provvigioni preventivate. **Margine consuntivo**: ricavo consuntivo meno costi reali meno provvigioni maturate.',
          ],
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il **periodo** sceglie le commesse in base alla loro **data di inizio**; per ogni commessa scelta i valori sono quelli dell’intera vita della commessa. Di default va dal 1° gennaio dell’anno corrente a oggi.',
        },
        {
          type: 'paragraph',
          text: 'Puoi restringere per **società**, **sede società**, **sede operativa**, **funzione aziendale**, **tipo commessa** e **commerciale**. Società, sedi e commerciale sono quelli dell’offerta di riferimento della commessa (per una commessa con più offerte, la prima).',
        },
        {
          type: 'table',
          headers: ['Base del ricavo consuntivo', 'Cosa usa come ricavo'],
          rows: [
            ['**Contrattuale** (predefinita)', 'Il ricavo previsto dal contratto'],
            ['**Fatturato**', 'L’imponibile delle fatture attive emesse'],
            ['**Incassato**', 'La quota di imponibile già incassata'],
          ],
        },
        {
          type: 'note',
          text: 'Il **margine atteso** usa sempre il ricavo contrattuale: cambiando la base cambiano solo il ricavo e il margine consuntivo.',
        },
      ],
    },
    {
      id: 'income-statement',
      title: 'Prospetto per funzione aziendale',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il prospetto principale è **per funzione aziendale** e riguarda **tutte** le commesse scelte dai filtri. Non c’è un elenco delle singole commesse: per ogni funzione vedi il numero di commesse, il ricavo contrattuale e consuntivo, i costi preventivati e reali (manuali, ordini impegnati, fatturato passivo e costo del lavoro), le **ore registrate** (con la nota sulle ore non valorizzate), le provvigioni, il margine atteso, il margine consuntivo e lo scostamento. In fondo trovi la **riga di totale**.',
        },
        {
          type: 'paragraph',
          text: 'Lo **scostamento del margine** è margine consuntivo meno margine atteso: se è **negativo** la funzione rende meno del previsto. I valori negativi sono scritti con il segno meno e in rosso. Il grafico confronta margine atteso e consuntivo di ogni funzione.',
        },
      ],
    },
    {
      id: 'groups',
      title: 'Segmentazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Con **Segmentazione** scegli se dividere il prospetto per **società**, **sede società**, **sede operativa**, **tipo commessa** o **commerciale**. Per ogni valore compare un riquadro con il subtotale e **lo stesso prospetto per funzione aziendale** calcolato sulle sole commesse di quel valore. Con **Nessuna** (predefinito) vedi solo il prospetto principale.',
        },
        {
          type: 'paragraph',
          text: 'Le cifre di una commessa si dividono tra le funzioni delle sue righe di ricavo, in proporzione al ricavo di ciascuna: gli importi sommano al totale. Le righe senza funzione finiscono in **Non attribuito**, sempre in fondo. Il **numero di commesse** di una funzione conta quelle con almeno una riga in quella funzione, quindi la somma dei conteggi può superare il totale.',
        },
      ],
    },
    {
      id: 'structure-costs',
      title: 'Costi di struttura',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sono i costi passivi **non legati a nessuna commessa**: ordini a fornitore ancora da fatturare (**impegnato**) e righe di fatture passive (**fatturato**). Non vengono mai ripartiti sulle commesse. Li vedi per **tipo di costo**, con la natura (diretto, indiretto, generale), e per **società** o **sede**.',
        },
        {
          type: 'note',
          text: 'Le fatture passive degli estratti conto provvigioni sono escluse: quelle provvigioni sono già nel margine delle commesse.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Serve il permesso **Controllo di gestione** (dal form Ruoli). Chi lo ha vede **tutte** le commesse, anche quelle di cui non è responsabile né partecipante. Senza il permesso la voce di menu non compare.',
        },
      ],
    },
  ],
}

export default guide
