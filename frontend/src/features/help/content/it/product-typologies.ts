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
          text: "Una tipologia prodotto raggruppa i prodotti dal punto di vista commerciale. Si assegna nella scheda Prodotto e alimenta il Riepilogo per Tipologia Prodotto dell'offerta.",
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
            ['Descrizione', 'Testo libero facoltativo.'],
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
