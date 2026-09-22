import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-importances',
  title: 'Importanza Task',
  summary: "L'Importanza Task indica la rilevanza di un task.",
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configura in **Configurazione › Importanza Task** e compare nel campo **Importanza** del task.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, al massimo 191 caratteri.'],
            ['Descrizione', 'Facoltativa, al massimo 500 caratteri.'],
            ['Colore', 'Obbligatorio.'],
            ['Icona', 'Facoltativa.'],
            ['Attiva', 'Se la spegni, la voce sparisce dai menu a tendina.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare, modificare, disattivare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova importanza**.',
            'Compila i campi e premi **Salva**.',
            'Per modificare, scegli **Modifica** sulla riga, cambia i dati e premi **Salva**.',
            'Per disattivare, apri **Modifica** e spegni **Attiva**: i task che la usano restano intatti.',
          ],
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
            'Premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            "Rilascia: l'ordine si salva subito e si riflette su tabella e menu a tendina.",
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
          text: 'Una voce in uso su un task non si può eliminare: disattivala invece di eliminarla.',
        },
      ],
    },
  ],
}

export default guide
