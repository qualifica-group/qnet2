import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'product-typologies',
  title: 'Tipologie Prodotto',
  summary: 'Le tipologie prodotto sono un raggruppamento commerciale usato nei prodotti e nei report di offerta.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le tipologie prodotto',
      blocks: [
        {
          type: 'paragraph',
          text: "Una tipologia prodotto raggruppa i prodotti dal punto di vista commerciale. Ha un colore, che la rende riconoscibile a colpo d'occhio. Si assegna nella scheda Prodotto e alimenta il Riepilogo per Tipologia Prodotto dell'offerta.",
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
            ['Nome', 'Il nome della tipologia.'],
            ['Codice', 'Un codice univoco.'],
            ['Colore', 'Obbligatorio (di partenza **grigio**). È il colore del badge con cui la tipologia compare ovunque: tabella prodotti, scheda prodotto, righe e riepilogo per tipologia dell\'offerta, Dati contrattuali della commessa.'],
            ['Descrizione', 'Testo libero facoltativo.'],
            ['Calcolo commissione Fornitore', 'Interruttore: se attivo, sulle nuove righe d\'offerta di questa tipologia si calcola la commissione Fornitore.'],
            ['Verso', 'Visibile e obbligatorio solo con l\'interruttore attivo. **Ricevuta**: la commissione è il ricavo della riga e l\'imponibile passa al fornitore. **Pagata**: è un costo verso il fornitore.'],
            ['Tipo richiesta proforma', 'Nella sezione **Fatturazione**. Decide in quale richiesta proforma confluiscono le righe dei prodotti di questa tipologia: **Consulenza**, **Ente** oppure **Nessuna**.'],
          ],
        },
      ],
    },
    {
      id: 'supplier-commission',
      title: 'Commissione Fornitore',
      blocks: [
        {
          type: 'paragraph',
          text: 'Con l\'interruttore spento la commissione Fornitore non viene creata sulle righe di questa tipologia, anche se esiste una regola. Il margine della riga dipende dal verso: **Ricevuta** = commissione Fornitore meno costi e altre commissioni; **Pagata** = imponibile meno costi, altre commissioni e commissione Fornitore; **disattivato** = imponibile meno costi e altre commissioni.',
        },
        {
          type: 'warning',
          text: 'L\'impostazione si congela sulla riga d\'offerta quando la riga viene creata: cambiare la tipologia vale solo per le righe nuove, le righe esistenti non cambiano.',
        },
      ],
    },
    {
      id: 'proforma-kind',
      title: 'Tipo richiesta proforma',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quando dalla commessa chiedi le proforma, le righe dei prodotti con tipo **Consulenza** confluiscono in un’unica richiesta di consulenza; quelle con tipo **Ente** formano una richiesta per ogni fornitore. Le stesse righe sono quelle proposte nella bozza della fattura di quella richiesta.',
        },
        {
          type: 'tip',
          text: 'Con **Nessuna** (campo vuoto) le righe dei prodotti di questa tipologia non si fatturano tramite richiesta proforma. Il tipo si imposta su qualsiasi tipologia, anche su quelle nuove: conta il tipo scelto, non il nome o il codice della tipologia.',
        },
      ],
    },
    {
      id: 'manage',
      title: 'Creare e modificare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova tipologia prodotto**.',
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com\'era. Poi premi **Salva**.',
            'Per modificare non c\'è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com\'era.',
            'Il **Codice** si inserisce solo alla creazione. **Calcolo commissione Fornitore** e **Verso** sono una riga sola: si aprono, si salvano e si annullano insieme.',
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
          text: 'Il Codice non si modifica dopo la creazione. Non puoi eliminare una tipologia già usata.',
        },
      ],
    },
  ],
}

export default guide
