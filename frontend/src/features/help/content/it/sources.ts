import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'sources',
  title: 'Fonti',
  summary: 'Le Fonti indicano da dove arriva un contatto: fiera, sito web, passaparola.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Fonti**. Ogni fonte ha solo un **Nome**.' },
        { type: 'paragraph', text: 'Le Fonti si usano in **Lead**, **Opportunità**, **Anagrafiche** e nei **buoni**; sono anche un criterio dei workflow offerta, ereditato dall’opportunità.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Fonti** e premi **Nuova fonte**.', 'Scrivi il **Nome**.', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza**, **Modifica** ed **Elimina**, se il tuo ruolo lo consente.' },
        { type: 'note', text: 'Se una fonte è già usata da qualche record, non puoi eliminarla: un messaggio ne spiega il motivo.' },
        { type: 'tip', text: 'Se il campo **Fonte** su un record è protetto, chi lo usa può proporne la modifica dalla guida **Richieste di modifica**.' },
      ],
    },
  ],
}

export default guide
