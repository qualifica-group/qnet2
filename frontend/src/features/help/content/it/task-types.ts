import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-types',
  title: 'Tipologie Task',
  summary: 'Le Tipologie Task classificano il tipo di attività di un task, per esempio una chiamata o un sopralluogo.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa servono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configurano in **Configurazione › Tipologie Task** e compaiono nel campo **Tipologia** del task.',
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
            ['Predefinita', 'Se attiva, precompila il campo Tipologia sui nuovi task quando non ne viene scelta una.'],
          ],
        },
        {
          type: 'note',
          text: 'Solo una tipologia alla volta può essere predefinita: attivarla su una la disattiva automaticamente sulle altre. Una tipologia disattivata non può essere predefinita.',
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
            'Premi **Nuova tipologia**.',
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
