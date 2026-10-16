import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'job-roles',
  title: 'Mansioni',
  summary: 'Le mansioni descrivono cosa fa una persona; si assegnano alle persone dalla scheda utente.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le mansioni',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una mansione (per esempio Coordinatore commerciale) sostituisce la vecchia descrizione mansione a testo libero. Una persona può avere **nessuna, una o più mansioni**, scelte nella sezione Impiego della scheda utente. Le mansioni **non hanno alcun legame con ruoli e permessi**: non cambiano ciò che una persona può fare in QNet. Ogni mansione può essere collegata alle attività che comprende.',
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
            ['Nome', 'Il nome della mansione, univoco.'],
            ['Descrizione', 'Testo libero facoltativo.'],
            ['Attività', 'Le attività comprese nella mansione (nessuna, una o più). Il legame si modifica qui, non dall’attività.'],
            ['Persone', 'Conteggio in sola lettura delle persone che hanno questa mansione.'],
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
            'Premi **Nuova mansione**.',
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
          text: 'Una mansione assegnata ad almeno una persona non si può eliminare: toglila prima da quelle persone. Una mansione senza persone si elimina dalla griglia.',
        },
      ],
    },
  ],
}

export default guide
