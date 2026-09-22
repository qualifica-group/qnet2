import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-templates',
  title: 'Modelli di Task',
  summary: 'Un modello di task è un elenco di attività standard, generate in automatico alla creazione di una Commessa.',
  sections: [
    {
      id: 'overview',
      title: "Cos'è un modello di task",
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configurano in **Task › Modelli di Task**. Scegliendo un modello alla creazione di una Commessa, QNet crea i task del modello e li assegna ai responsabili.',
        },
        {
          type: 'note',
          text: 'I task creati sono copie indipendenti dal modello: modificarli non cambia il modello, e modificare il modello non cambia i task già creati.',
        },
      ],
    },
    {
      id: 'creating-a-template',
      title: 'Creare un modello',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Task › Modelli di Task** e premi **Nuovo modello di task**.',
            'Inserisci **Nome** e **Descrizione**, lasciando attivo **Attivo**.',
            'In **Righe** premi **Aggiungi riga** per ogni task del modello.',
            'Trascina le righe nell\'ordine voluto e premi **Salva**.',
          ],
        },
        {
          type: 'table',
          headers: ['Campo riga', 'Cosa indicare'],
          rows: [
            ['Titolo', 'Il titolo del task generato.'],
            ['Descrizione', 'Facoltativa.'],
            ['Tempo stimato (min)', 'Facoltativo.'],
            ['Scadenza (giorni da inizio Commessa)', 'Quanti giorni dopo la data di inizio della Commessa scade il task.'],
            ['Stato iniziale', 'Lo stato con cui il task nasce.'],
            ['Allegati', 'File da allegare al task generato.'],
          ],
        },
      ],
    },
    {
      id: 'using-a-template',
      title: 'Usare un modello',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un modello si sceglie con l\'azione **Programma** sul contratto, alla creazione della Commessa: QNet crea i task del modello e li assegna ai responsabili. Li trovi nella sezione **Task** della Commessa.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Un modello già usato da commesse non si può eliminare: disattivalo con **Attivo**.',
        },
      ],
    },
  ],
}

export default guide
