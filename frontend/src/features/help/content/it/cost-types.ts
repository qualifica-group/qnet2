import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'cost-types',
  title: 'Tipi di costo',
  summary: 'I tipi di costo (per esempio Collaboratori o Fitti) si scelgono su ogni riga degli ordini di acquisto.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono i tipi di costo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un tipo di costo classifica la spesa di una riga di un ordine di acquisto (ODA). Il tipo è **obbligatorio su ogni riga** e compare come badge colorato nel suo colore (menu di scelta delle righe ODA e di Genera ODA, dettaglio ODA, griglie Tipi di costo e Scadenze passive). Lo stesso tipo si può impostare come predefinito su un prodotto e indicare sui costi di una commessa. Sono già presenti Collaboratori, Fitti, Utenze e Altro; puoi aggiungerne altri o modificarli.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Campi',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Il nome del tipo di costo, univoco.'],
            ['Descrizione', 'Testo libero facoltativo.'],
            ['Natura', '**Diretto** (imputabile a una commessa), **Indiretto** (costo di produzione, ripartito) o **Generale** (struttura e spese generali). Obbligatoria; i tipi già presenti sono Diretto.'],
            ['Colore', 'Il colore del badge del tipo, scelto dalla tavolozza. Obbligatorio; i tipi già presenti sono grigio ardesia.'],
            ['Attivo', 'Un tipo non attivo non si può più scegliere sulle nuove righe.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare, modificare, disattivare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo tipo di costo**.',
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com’era. Poi premi **Salva**. Il nuovo tipo va in fondo all’elenco.',
            'Per modificare non c’è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga (Nome, Descrizione, Natura, Colore, Attivo), cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era.',
            'Dall’elenco puoi anche modificare direttamente nelle celle **Nome**, **Descrizione**, **Natura**, **Colore** (scegli dalla palette) e **Attivo**: fai doppio clic sulla cella, cambia il valore e conferma. Valgono le stesse regole della scheda (per esempio il nome deve essere unico).',
          ],
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Cambiare l’ordine',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            'Rilascia: l’ordine si salva subito e si riflette su tabella e menu di scelta. L’ordine non si scrive più a mano.',
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Non puoi eliminare un tipo di costo già usato da righe di ordini di acquisto, da fatture passive, da prodotti o da costi di commessa: disattivalo. Nella griglia i tipi in uso non offrono Elimina.',
        },
      ],
    },
  ],
}

export default guide
