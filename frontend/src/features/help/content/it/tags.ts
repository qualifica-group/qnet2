import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'tags',
  title: 'Tag',
  summary: 'I Tag sono etichette di classificazione, con il solo campo Nome: una tabella di riferimento della tua organizzazione.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Tag**. Ogni tag ha solo un **Nome**.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Tag** e premi **Nuovo tag**.', 'Scrivi il **Nome**.', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza**, **Modifica** ed **Elimina**, se il tuo ruolo lo consente.' },
        { type: 'note', text: 'Se un tag è già associato ad almeno un record, non puoi eliminarlo: un messaggio ne spiega il motivo.' },
      ],
    },
  ],
}

export default guide
