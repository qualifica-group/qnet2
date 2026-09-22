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
          text: "Si configurano in **Configurazione › Stati Task**. Per ogni stato indichi Nome, Descrizione, Colore, Icona e la **Fase** a cui appartiene: Aperto, In attesa, Da validare, Chiuso con esito positivo o Chiuso con esito negativo.",
        },
        {
          type: 'paragraph',
          text: 'Ogni stato ha anche una **Percentuale di completamento**: è il valore che il task mostra quando gli viene assegnato quello stato.',
        },
      ],
    },
    {
      id: 'system-statuses',
      title: 'Stati di sistema e disattivazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'I tre stati di sistema (uno fissato in testa, due in coda) permettono di cambiare solo nome, colore, icona e percentuale di completamento: gli altri campi restano fissi.',
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
          text: "Gli stati di sistema restano bloccati in testa o in coda: solo gli stati personalizzati si spostano liberamente.",
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
