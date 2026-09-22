import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'sectors',
  title: 'Settori',
  summary: 'I Settori classificano le anagrafiche per attività; con un Settore padre puoi costruire una gerarchia.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Settori** e si usa nelle **Anagrafiche**, per classificarle in base all’attività svolta.' },
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
            ['**Nome**', 'Il nome del settore.'],
            ['**Settore padre**', 'Facoltativo: collega il settore a un settore padre per creare una gerarchia.'],
          ],
        },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Settori** e premi **Nuovo settore**.', 'Compila **Nome** e, se serve, **Settore padre**.', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza**, **Modifica** ed **Elimina**, se il tuo ruolo lo consente.' },
        { type: 'warning', text: 'Un settore con sotto-settori non si può eliminare.' },
      ],
    },
  ],
}

export default guide
