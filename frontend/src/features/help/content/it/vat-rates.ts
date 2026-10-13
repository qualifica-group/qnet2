import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'vat-rates',
  title: 'IVA',
  summary: 'Le aliquote IVA si assegnano ai prodotti e vengono applicate alle righe delle offerte.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le aliquote IVA',
      blocks: [
        {
          type: 'paragraph',
          text: "Un'aliquota IVA è definita da un Nome e da un'Aliquota, il valore percentuale usato per calcolare l'imposta.",
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
            ['Nome', "Il nome dell'aliquota."],
            ['Aliquota', 'Il valore percentuale, zero o positivo.'],
          ],
        },
      ],
    },
    {
      id: 'usage',
      title: 'Dove si usa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le aliquote IVA sono selezionabili nella scheda Prodotto (campo IVA) e vengono applicate alle righe delle offerte.',
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
            'Premi **Nuova aliquota IVA**.',
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com\'era. Poi premi **Salva**.',
            'Per modificare non c\'è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com\'era.',
            'Gli eventuali campi flessibili compaiono come righe a parte e si modificano allo stesso modo.',
          ],
        },
      ],
    },
  ],
}

export default guide
