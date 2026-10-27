import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-trend',
  title: 'Andamento mensile',
  summary: 'Fatturato, costi, costo del lavoro e margine per funzione aziendale mese per mese, il confronto con gli stessi mesi dell’anno precedente e i costi per natura in rapporto al fatturato.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Andamento mensile** risponde alle domande “come si muovono fatturato, costi e margine, e in quale funzione?”. La trovi in **Contabilità › Controllo di gestione › Andamento mensile**. È **solo di consultazione** e mostra importi **al netto dell’IVA**, in forma aggregata, per mese della **data del documento**.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi 4 riquadri: **Fatturato**, **Costi**, **Costo del lavoro** e **Margine** del periodo, ciascuno con l’anno precedente e la variazione in percentuale; il riquadro del costo del lavoro riporta anche le **ore registrate** e, se ci sono, quelle **non valorizzate**. Sotto, la tabella per funzione, i grafici e i costi per natura.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e periodo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Di default il periodo copre gli **ultimi 12 mesi** (dal primo giorno del mese di undici mesi fa a oggi); può estendersi al massimo a **24 mesi**. Ogni mese dell’intervallo è mostrato, anche senza dati. Puoi restringere per **società**, **sede società** e **sede operativa**: le fatture attive prendono le sedi dall’offerta collegata, i documenti passivi dalla propria testata.',
        },
        {
          type: 'note',
          text: 'Il **fatturato** conta solo le fatture (le proforma non sono fatturato); i **costi** contano i documenti passivi, proforma comprese, **più il costo del lavoro**: le ore del segnatempo moltiplicate per il costo orario di chi le ha lavorate (dalla retribuzione della condizione contrattuale in vigore alla data dell’ora), per mese della data del segnatempo. Le ore di chi in quella data non ha una condizione con retribuzione sono contate come ore ma non valorizzate.',
        },
      ],
    },
    {
      id: 'functions',
      title: 'Funzioni aziendali per mese',
      blocks: [
        {
          type: 'paragraph',
          text: 'Scegli la **misura** (Fatturato, Costi, Costo del lavoro o Margine; i Costi includono già il costo del lavoro) per vedere ogni funzione aziendale mese per mese, con una colonna **Totale**. Una riga di ricavo con riga d’offerta va alla funzione della categoria del suo prodotto; senza, si ripartisce con le quote della commessa; una fattura senza commessa va in **Non attribuito**. Un costo con commessa si ripartisce con le quote della commessa; senza commessa va in **Struttura**. Le ore del segnatempo con commessa seguono le quote della commessa; quelle senza commessa vanno alle funzioni aziendali di appartenenza della persona, in parti uguali se appartiene a più funzioni, e in **Struttura** solo se la persona non appartiene a nessuna funzione. Conta l’appartenenza attuale: spostare una persona di funzione sposta anche le sue ore passate.',
        },
        {
          type: 'paragraph',
          text: 'I grafici mostrano **una linea per funzione** sulla misura scelta e, accanto, il **totale a confronto con l’anno precedente**.',
        },
      ],
    },
    {
      id: 'previous-year',
      title: 'Confronto con l’anno precedente',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sotto la tabella trovi i totali, gli **stessi mesi dell’anno precedente** e la **variazione %** per ogni mese e per l’intero periodo. Quando l’anno precedente vale zero la variazione è **n.d.**, perché non c’è nulla con cui confrontare.',
        },
      ],
    },
    {
      id: 'nature',
      title: 'Costi per natura',
      blocks: [
        {
          type: 'paragraph',
          text: 'La tabella ripartisce i costi di ogni mese per **natura** del tipo di costo (diretto, indiretto, generale, o senza tipo) e, sotto ogni importo, mostra la sua **quota sul fatturato** del mese. Dove non c’è fatturato la quota è **n.d.**. Le ore interne **non hanno una natura**: il costo del lavoro non compare in questa tabella, che riguarda i soli costi passivi. In fondo, costi passivi totali e fatturato.',
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
