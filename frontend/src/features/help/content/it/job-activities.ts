import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'job-activities',
  title: 'Attività',
  summary: 'Le attività sono i compiti che compongono una mansione.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le attività',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un’attività (per esempio Preparazione preventivi) è un compito che può far parte di una o più mansioni. Il legame tra attività e mansioni **si modifica dalla mansione**; qui vedi, in sola lettura, le mansioni che comprendono l’attività.',
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
            ['Nome', 'Il nome dell’attività, univoco.'],
            ['Descrizione', 'Testo libero facoltativo.'],
            ['Mansioni', 'Elenco in sola lettura delle mansioni che comprendono l’attività.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare, modificare, eliminare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova attività**.',
            'Si apre il pannello con le righe chiuse: premi la matita su ogni riga, compilala e premi **Fatto**; **Ripristina** riporta la riga com’era. Poi premi **Salva**.',
            'Per modificare, apri il pannello con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva**: viene salvata solo quella riga.',
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
          text: 'Un’attività collegata a una mansione non si può eliminare: toglila prima dalla mansione. Un’attività non collegata a nessuna mansione si elimina dalla griglia.',
        },
      ],
    },
  ],
}

export default guide
