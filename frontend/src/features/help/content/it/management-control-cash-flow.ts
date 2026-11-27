import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-cash-flow',
  title: 'Cash flow previsionale',
  summary: 'Entrate e uscite attese mese per mese, l’arretrato già scaduto, il saldo mensile e cumulato per società e uno scenario in cui l’arretrato viene regolato entro 30 giorni.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Cash flow previsionale** risponde alla domanda “quanto entrerà e uscirà nei prossimi mesi?”. La trovi in **Contabilità › Controllo di gestione › Cash flow previsionale**. È **solo di consultazione** e mostra importi **sul totale del documento** (IVA compresa), in forma aggregata: non c’è l’elenco dei singoli documenti, che restano nella pagina **Scadenze**.',
        },
        {
          type: 'paragraph',
          text: 'È una **fotografia a oggi** delle rate aperte e degli impegni verso i fornitori, collocati nel mese in cui si prevede che scadano. In alto trovi 8 riquadri: **Arretrato netto**, **Entrate**, **Uscite**, **Impegni**, **Stipendi** e **F24** dell’orizzonte, **Saldo netto** dell’orizzonte e **Saldo netto con scenario**.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non c’è un periodo: la previsione parte sempre dal mese corrente. Puoi restringere per **società**, **sede società** e **sede operativa**. Le fatture attive prendono le sedi dall’offerta collegata: una fattura senza offerta non ha sede, quindi esce quando filtri per sede.',
        },
        {
          type: 'table',
          headers: ['Filtro', 'Cosa fa'],
          rows: [
            ['**Tipo documento**', 'Tutti (predefinito, proforma comprese), solo proforma o solo fatture, su entrambi i lati'],
            ['**Orizzonte**', 'Quanti mesi mostrare, a partire da quello corrente: 3, 6, 12 o 24'],
            ['**Includi stima personale**', 'Acceso di default; spento toglie stipendi e F24 stimati (vedi Stima del personale)'],
          ],
        },
      ],
    },
    {
      id: 'backlog',
      title: 'Arretrato e oltre',
      blocks: [
        {
          type: 'paragraph',
          text: 'La colonna **Arretrato** raccoglie ciò che è **già scaduto** alla data di oggi: rate aperte con scadenza passata e ordini a fornitore la cui data attesa è trascorsa. La colonna **Oltre** raccoglie ciò che scade **dopo l’ultimo mese** dell’orizzonte.',
        },
        {
          type: 'note',
          text: 'Il **cumulato** somma il netto mensile e **non include l’arretrato**, che è mostrato a parte. Lo scenario qui sotto lo aggiunge.',
        },
      ],
    },
    {
      id: 'months',
      title: 'Mesi dell’orizzonte',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni mese mostra le **Entrate** (rate attive aperte), le **Uscite** (rate passive aperte, già al netto di ritenuta ed ENASARCO), gli **Impegni** (ordini a fornitore approvati e non ancora fatturati, IVA compresa come le rate), gli **Stipendi** e l’**F24** stimati (vedi Stima del personale), il **Netto** (entrate − uscite − impegni − stipendi − F24) e il **Cumulato**. Una rata cade nel mese della sua scadenza; un ordine cade nel mese della sua prima scadenza attesa, calcolata dalla data dell’ordine e dal metodo di pagamento (la data dell’ordine stessa se manca il metodo).',
        },
        {
          type: 'paragraph',
          text: 'Sotto la tabella, un grafico confronta le **entrate** con le **uscite e impegni** di ogni colonna e un secondo segue il **netto cumulato** mese per mese.',
        },
      ],
    },
    {
      id: 'personnel-estimate',
      title: 'Stima del personale',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le colonne **Stipendi (stima)** e **F24 (stima)** (in corsivo) prevedono le uscite per il personale dei prossimi mesi. Sono **stime**, non documenti, e contano nel netto, nei riquadri e nel grafico come le uscite.',
        },
        {
          type: 'table',
          headers: ['Voce', 'Come viene stimata'],
          rows: [
            ['**Base**', 'L’ultimo mese per cui sono stati importati sia il **file dei costi** sia il **file dei netti**; ogni persona è stimata sul proprio costo e netto di quel mese'],
            ['**Stipendio netto**', 'Un netto al mese, pagato nello **stesso giorno degli ultimi cedolini importati** (il 15 se non noto) del mese successivo'],
            ['**Tredicesima e quattordicesima**', 'Un netto in più ciascuna: la tredicesima il **20 dicembre**, la quattordicesima il **15 luglio**'],
            ['**F24**', 'Pagato il **16** del mese successivo: costo mensile − netto − 2/12 del netto, perché i ratei di tredicesima e quattordicesima sono già nel costo'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Quando vengono importati i **cedolini veri**, la stima di quel mese sparisce e al suo posto ci sono le rate reali. Le persone **cessate** sono escluse dal mese di cessazione in poi; i **nuovi assunti** non sono stimati finché non compaiono nei file importati. Le stime con data **già passata** non vengono mostrate e quelle oltre l’orizzonte finiscono in **Oltre**.',
        },
        {
          type: 'note',
          text: 'Con un filtro **sede società** o **sede operativa** le stime sono **escluse**, perché il personale non ha sede. L’interruttore **Includi stima personale** nei filtri (acceso di default) le nasconde: le due colonne vanno a zero e il netto torna a comprendere solo documenti e impegni.',
        },
      ],
    },
    {
      id: 'scenario',
      title: 'Scenario: arretrato regolato in 30 giorni',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’interruttore **Arretrato incassato e pagato entro 30 giorni** simula che tutto lo scaduto venga regolato a breve: l’arretrato si sposta sul **primo mese** e il cumulato viene ricalcolato, mentre gli altri mesi restano invariati. La colonna Arretrato mostra allora zero. Il riquadro **Saldo netto con scenario** mostra sempre il risultato, a interruttore acceso o spento.',
        },
        {
          type: 'tip',
          text: 'Confronta il cumulato con l’interruttore acceso e spento per vedere quanto pesa lo scaduto sui mesi successivi.',
        },
      ],
    },
    {
      id: 'by-company',
      title: 'Netto per società',
      blocks: [
        {
          type: 'paragraph',
          text: 'La tabella mostra il **netto** di ogni società per l’arretrato, ogni mese, l’oltre e il **totale**, con la riga dell’intera selezione in fondo. La società è quella del documento o dell’ordine; la riga **Non attribuito** raccoglie ciò che non ne ha una. Con lo scenario acceso, le tabelle lo seguono.',
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
