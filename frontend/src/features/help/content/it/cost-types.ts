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
          text: 'Un tipo di costo classifica la spesa di una riga di un ordine di acquisto (ODA). Il tipo è **obbligatorio su ogni riga**. Sono già presenti Collaboratori, Fitti, Utenze e Altro; puoi aggiungerne altri o modificarli.',
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
            ['Ordine', "La posizione nell'elenco di scelta: i numeri più bassi compaiono prima."],
            ['Attivo', 'Un tipo non attivo non si può più scegliere sulle nuove righe.'],
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
          text: 'Non puoi eliminare un tipo di costo già usato da righe di ordini di acquisto: disattivalo.',
        },
      ],
    },
  ],
}

export default guide
