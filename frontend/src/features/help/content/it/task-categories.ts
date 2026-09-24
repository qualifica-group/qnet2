import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-categories',
  title: 'Categorie Task',
  summary: "Le Categorie Task classificano l'area di appartenenza di un task.",
  sections: [
    {
      id: 'overview',
      title: 'A cosa servono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configurano in **Configurazione › Categorie Task** e compaiono nel campo **Categoria** del task.',
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
            ['Categoria padre', 'Facoltativa: rende questa categoria una sotto-categoria di quella scelta.'],
          ],
        },
        {
          type: 'note',
          text: 'Le categorie possono annidarsi su più livelli: il menu del task le mostra ad albero, indentate sotto il rispettivo padre, e puoi scegliere anche una categoria padre, non solo le foglie. Colore e icona non si ereditano dal padre: vanno impostati su ogni categoria. Il nome deve essere unico solo tra le categorie con lo stesso padre.',
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
            'Premi **Nuova categoria**.',
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
        {
          type: 'warning',
          text: 'Una categoria con sotto-categorie non si può eliminare finché non elimini o sposti le sotto-categorie: allo stesso modo non puoi sceglierla come padre di se stessa o di una delle sue discendenti.',
        },
      ],
    },
  ],
}

export default guide
