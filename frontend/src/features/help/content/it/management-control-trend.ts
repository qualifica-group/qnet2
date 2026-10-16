import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-trend',
  title: 'Andamento mensile',
  summary: 'Fatturato, costi e margine per funzione aziendale mese per mese, il confronto con gli stessi mesi dell’anno precedente e i costi per natura in rapporto al fatturato.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Andamento mensile** risponde alle domande “come si muovono fatturato, costi e margine, e in quale funzione?”. La trovi in **Controllo di gestione › Andamento mensile**. È **solo di consultazione** e mostra importi **al netto dell’IVA**, in forma aggregata, per mese della **data del documento**.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi 3 riquadri: **Fatturato**, **Costi** e **Margine** del periodo, ciascuno con l’anno precedente e la variazione in percentuale. Sotto, la tabella per funzione, i grafici e i costi per natura.',
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
          text: 'Il **fatturato** conta solo le fatture (le proforma non sono fatturato); i **costi** contano i documenti passivi, proforma comprese.',
        },
      ],
    },
    {
      id: 'functions',
      title: 'Funzioni aziendali per mese',
      blocks: [
        {
          type: 'paragraph',
          text: 'Scegli la **misura** (Fatturato, Costi o Margine) per vedere ogni funzione aziendale mese per mese, con una colonna **Totale**. Una riga di ricavo con riga d’offerta va alla funzione della categoria del suo prodotto; senza, si ripartisce con le quote della commessa; una fattura senza commessa va in **Non attribuito**. Un costo con commessa si ripartisce con le quote della commessa; senza commessa va in **Struttura**.',
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
          text: 'La tabella ripartisce i costi di ogni mese per **natura** del tipo di costo (diretto, indiretto, generale, o senza tipo) e, sotto ogni importo, mostra la sua **quota sul fatturato** del mese. Dove non c’è fatturato la quota è **n.d.**. In fondo, costi totali e fatturato.',
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
