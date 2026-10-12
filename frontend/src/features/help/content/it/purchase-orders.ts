import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-orders',
  title: 'ODA',
  summary: 'Ordini di acquisto ai fornitori, con righe, tipo di costo e stampa.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Acquisti › ODA** e mostra gli ordini di acquisto ai fornitori. Le **schede per stato** in alto restringono l’elenco; la freccia a sinistra di ogni riga mostra le righe dell’ordine.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. La guida verrà pubblicata quando il modulo sarà completato.',
        },
      ],
    },
  ],
}

export default guide
