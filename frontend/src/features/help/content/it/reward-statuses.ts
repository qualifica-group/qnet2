import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'reward-statuses',
  title: 'Stati Buoni Collegati',
  summary: 'Gli stati che un buono, premio o incentivo può attraversare, dalla proposta alla decisione.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa servono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configurano in **Premi e Incentivi › Stati Buoni Collegati**.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio.'],
            ['Descrizione', 'Facoltativa.'],
            ['Colore', 'Obbligatorio.'],
            ['Gruppo', 'In attesa, Approvato o Negato.'],
            ['Attivo', 'Se lo spegni, la voce sparisce dai menu a tendina.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare e modificare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo stato**.',
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com\'era. Poi premi **Salva**.',
            'Per modificare non c\'è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com\'era.',
          ],
        },
      ],
    },
    {
      id: 'system-statuses',
      title: 'Stati di sistema',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli stati di sistema **In attesa** (sempre primo), **Approvato** e **Negato** hanno campi fissi: ogni nuovo buono parte da In attesa.',
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Cambiare l\'ordine',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Stati Buoni Collegati** e premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            "Rilascia: l'ordine si salva subito.",
          ],
        },
        {
          type: 'note',
          text: 'Gli stati di sistema restano bloccati in testa o in coda: solo gli stati personalizzati si spostano liberamente.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Uno stato in uso non si può eliminare: disattivalo invece di eliminarlo.',
        },
      ],
    },
  ],
}

export default guide
