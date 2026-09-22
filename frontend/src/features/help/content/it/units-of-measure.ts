import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'units-of-measure',
  title: 'Unita di Misura',
  summary: 'Le unità di misura (per esempio ore o pezzi) sono selezionabili nella scheda Prodotto.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le unità di misura',
      blocks: [
        {
          type: 'paragraph',
          text: "Un'unità di misura indica come si conta un prodotto: ore, pezzi e simili. Si assegna ai prodotti e si applica alle righe delle offerte.",
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
            ['Nome', "Il nome dell'unità di misura."],
            ['Simbolo', 'Il simbolo mostrato accanto ai valori.'],
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
          text: "Il Codice non si modifica dopo la creazione. Non puoi eliminare un'unità di misura già usata.",
        },
        {
          type: 'note',
          text: "Salvando una riga d'offerta, l'unità di misura viene fissata sulla riga.",
        },
      ],
    },
  ],
}

export default guide
