import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'pipeline-statuses',
  title: 'Stati progetto/campagna',
  summary: 'Gli stati che progetti e campagne attraversano, con nome, colore e gruppo di appartenenza.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni stato indichi **Nome**, **Colore** e **Gruppo** (Aperto, In attesa o Chiuso). Gli stati di sistema **Nuovo** (sempre primo) e **Chiuso** (sempre ultimo) hanno gruppo fisso.',
        },
        {
          type: 'warning',
          text: 'Uno stato in uso non si può eliminare.',
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
      id: 'reorder-statuses',
      title: "Cambiare l'ordine degli stati",
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri la tabella e premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            "Rilascia: l'ordine si salva subito.",
          ],
        },
        {
          type: 'paragraph',
          text: 'Gli stati di sistema restano bloccati in testa o in coda.',
        },
      ],
    },
  ],
}

export default guide
