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
          text: 'Un tipo di costo classifica la spesa di una riga di un ordine di acquisto (ODA) ed è il **sottoconto di costo** del Piano dei conti: nei documenti il campo si chiama **Sottoconto costo** e il menu di scelta mostra codice e nome, raggruppati per mastro e conto. Il campo è **obbligatorio su ogni riga** e compare come badge colorato nelle griglie Tipi di costo e Scadenze passive. Lo stesso tipo si può impostare come predefinito su un prodotto e indicare sui costi di una commessa. Sono già presenti Collaboratori, Fitti, Utenze e Altro; puoi aggiungerne altri o modificarli.',
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
            ['Codice e conto', 'Facoltativi qui: un tipo di costo è anche un **sottoconto di costo** del **Piano dei conti**, dove ha un conto padre e un codice univoco. Da questa pagina non si modificano: si collocano dal Piano dei conti (**Colloca nel piano**) e non cambiano se salvi da qui.'],
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
          text: 'Non puoi eliminare un tipo di costo già usato da righe di ODA, fatture passive e loro righe, prodotti, costi di commessa, movimenti di tesoreria o profili fiscali agente: disattivalo. Nella griglia i tipi usati da ODA, fatture passive, prodotti o costi di commessa non offrono Elimina; negli altri casi il messaggio dice da cosa è usato.',
        },
      ],
    },
  ],
}

export default guide
