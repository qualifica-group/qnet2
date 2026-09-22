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
  ],
}

export default guide
