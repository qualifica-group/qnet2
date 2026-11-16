import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-statuses',
  title: 'Stati Task',
  summary: 'Gli Stati Task descrivono l\'avanzamento di un task: la fase in cui si trova e la percentuale di completamento.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa servono',
      blocks: [
        {
          type: 'paragraph',
          text: "Si configurano in **Configurazione › Task › Stati Task**. Per ogni stato indichi Nome, Descrizione, Colore, Icona e la **Fase** a cui appartiene: Aperto, In attesa, Da validare, Chiuso con esito positivo o Chiuso con esito negativo.",
        },
        {
          type: 'paragraph',
          text: 'Ogni stato ha anche una **Percentuale di completamento**: è il valore che il task mostra quando gli viene assegnato quello stato.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare e modificare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo stato**.',
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com\'era. Poi premi **Salva**.',
            'Per modificare non c\'è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com\'era.',
          ],
        },
      ],
    },
    {
      id: 'system-statuses',
      title: 'Stati di sistema e disattivazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli stati di sistema permettono di cambiare solo nome, colore, icona e percentuale di completamento: gli altri campi restano fissi e sulle loro righe non compare la matita. Si possono però spostare nell\'ordine come tutti gli altri.',
        },
        {
          type: 'note',
          text: 'Una voce disattivata sparisce dai menu a tendina, ma i task che la usano restano intatti.',
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Cambiare l\'ordine',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Stati Task** e premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            "Rilascia: l'ordine si salva subito.",
          ],
        },
        {
          type: 'note',
          text: "Si spostano tutti gli stati, compresi quelli di sistema. La posizione cambia solo l'ordine di tabella, menu a tendina e colonne della Kanban: non cambia il comportamento dello stato, che dipende dalla sua fase. Un nuovo stato viene aggiunto in fondo all'elenco.",
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Uno stato in uso non si può eliminare: disattivalo invece di eliminarlo.',
        },
      ],
    },
  ],
}

export default guide
