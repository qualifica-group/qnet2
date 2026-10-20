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
            'Si apre la scheda del nuovo modello con le righe **Nome**, **Descrizione** e **Attivo** chiuse: premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com\'era.',
            'In **Fasi** premi **Aggiungi fase** per ogni fase del lavoro (facoltativo) e dai un nome a ciascuna.',
            'Premi **Aggiungi riga** per ogni task del modello.',
            'Trascina fasi e righe nell\'ordine voluto (le righe anche da una fase all\'altra) e premi **Salva**.',
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
      id: 'editing-a-template',
      title: 'Modificare un modello',
      blocks: [
        {
          type: 'paragraph',
          text: "La scheda di un modello non ha un modulo di modifica: ogni riga si modifica sul posto. Premi la matita su **Nome**, **Descrizione** o **Attivo**, cambia il valore e premi **Salva**; **Annulla** o Esc lasciano la riga com'era.",
        },
        {
          type: 'paragraph',
          text: "**Fasi** è un'unica riga a tutta larghezza: la matita apre la stessa board della creazione (fasi, righe, sotto-task, allegati). Premi **Salva** per salvare tutte le fasi e righe insieme, **Annulla** per scartare le modifiche. Gli allegati delle righe già salvate si caricano subito; quelli delle righe nuove dopo il salvataggio.",
        },
        {
          type: 'note',
          text: 'Senza il permesso di modifica le righe non mostrano la matita.',
        },
      ],
    },
    {
      id: 'sub-tasks',
      title: 'Sotto-task',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni riga può avere sotto-task, fino a 3 livelli sotto una riga radice (figlio, nipote, pronipote). Premi **Aggiungi sotto-task** sulla riga per crearne uno: appare indentato subito sotto di essa.',
        },
        {
          type: 'list',
          items: [
            'Un sotto-task non ha una fase propria: segue sempre la fase della sua riga radice.',
            'La sua scadenza (giorni da inizio Commessa) non può superare quella della riga padre diretta.',
            'Rimuovendo una riga si rimuovono anche tutti i suoi sotto-task.',
            '**Aggiungi sotto-task** non compare più sulle righe già al 3° livello: non si può andare oltre.',
            'Un modello ha al massimo 500 righe, sotto-task compresi.',
          ],
        },
        {
          type: 'note',
          text: 'Alla creazione della Commessa i sotto-task del modello generano sotto-task dei task corrispondenti, con la stessa struttura.',
        },
      ],
    },
    {
      id: 'stages',
      title: 'Fasi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le fasi raggruppano le righe nelle tappe del lavoro (es. Analisi, Esecuzione, Collaudo). Una riga senza fase finisce in **Senza fase**.',
        },
        {
          type: 'list',
          items: [
            'Trascina una fase dalla maniglia per cambiarne l\'ordine.',
            'Trascina una riga in un\'altra fase, oppure scegli la fase dal menu della riga (utile da tastiera).',
            '**Rimuovi fase** elimina la fase: le sue righe passano in **Senza fase**.',
          ],
        },
        {
          type: 'note',
          text: 'Alla creazione della Commessa le fasi vengono copiate nella Commessa, nello stesso ordine, insieme ai task.',
        },
      ],
    },
    {
      id: 'using-a-template',
      title: 'Usare un modello',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un modello si sceglie con l\'azione **Programma** sul contratto, alla creazione della Commessa: QNet crea i task del modello e li assegna ai responsabili (senza responsabili i task restano da assegnare). Li trovi nella sezione **Task** della Commessa, raggruppati per fase. Tipologia, priorità e importanza dei task creati sono quelle predefinite (impostate in **Tipologie Task**, **Priorità Task** e **Importanza Task**); puoi cambiarle su ogni task.',
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
