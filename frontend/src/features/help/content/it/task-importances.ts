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
          text: 'Si configura in **Configurazione › Task › Importanza Task** e compare nel campo **Importanza** del task.',
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
            ['Predefinita', "Se attiva, precompila il campo Importanza sui nuovi task quando non ne viene scelta una."],
          ],
        },
        {
          type: 'note',
          text: "Solo un livello di importanza alla volta può essere predefinito: attivarlo su uno lo disattiva automaticamente sugli altri. Un livello disattivato non può essere predefinito.",
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
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com\'era. Poi premi **Salva**.',
            'Per modificare non c\'è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com\'era.',
            'Per disattivare, premi la matita su **Attiva** e spegnila: i task che la usano restano intatti.',
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
