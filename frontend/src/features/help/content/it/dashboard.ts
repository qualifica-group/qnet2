import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'dashboard',
  title: 'Dashboard',
  summary: 'La Dashboard è la prima pagina dopo l’accesso. Al momento è in allestimento.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa trovi',
      blocks: [
        { type: 'paragraph', text: 'La **Dashboard** si apre subito dopo aver premuto **Accedi**. Al momento la pagina è **in allestimento** e mostra solo un messaggio: riquadri e statistiche sono temporaneamente disattivati.' },
        { type: 'note', text: 'Task, segnatempo, assenze e statistiche restano disponibili nelle pagine dei rispettivi moduli, che apri dal menu.' },
      ],
    },
  ],
}

export default guide
