import type { HelpSection } from '../../../types'

/** Task-tab sections of the work-orders guide, split out to stay under the file-size limit. */
export const taskSections: HelpSection[] = [
    {
      id: 'task-board-selection',
      title: 'Task: selezione multipla',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella scheda **Task** del dettaglio ogni task principale ha una casella di selezione: selezionandone uno o più compare in basso la barra con le azioni massive (Assegna, Completa, Riapri, Blocca, Priorità, Date). I sotto-task non si selezionano.',
        },
        {
          type: 'list',
          items: [
            '**Seleziona tutti**, nella barra dei filtri, seleziona tutti i task visibili di tutte le fasi; cliccandola di nuovo li deseleziona.',
            'La casella accanto al nome di ogni **fase** (anche **Senza fase**, e in testa a ogni colonna della vista Board) seleziona o deseleziona tutti i task di quella fase.',
            'Se solo una parte dei task è selezionata la casella mostra un trattino: un clic completa la selezione.',
          ],
        },
        {
          type: 'note',
          text: 'Le caselle "seleziona tutti" considerano solo i task mostrati con i filtri attuali. Su una commessa chiusa il board è in sola lettura e le caselle non compaiono.',
        },
      ],
    },
    {
      id: 'task-template-reload',
      title: 'Task: caricare un modello',
      blocks: [
        {
          type: 'paragraph',
          text: "Nella scheda **Task**, dal menu **...** in alto a destra (su una commessa ancora senza task anche dal pulsante al centro della scheda), **Carica modello task** applica un **Modello di Task** a una commessa già creata: scegli il modello e premi **Carica modello**. Serve sia per una commessa creata senza modello sia per ricaricare il modello (lo stesso, se nel frattempo è cambiato, o un altro).",
        },
        {
          type: 'list',
          items: [
            'I task creati dal modello attuale, con tutti i loro sotto-task (anche quelli aggiunti a mano), vengono **eliminati** e sostituiti da quelli del modello scelto, assegnati ai Responsabili come alla creazione. I task creati a mano restano.',
            'Se anche uno solo di quei task è **completato** il modello non si ricarica e nulla cambia: il messaggio dice quanti sono.',
            "Le fasi rimaste vuote vengono eliminate; quelle con task creati a mano o con ore registrate restano. Una fase del nuovo modello con lo stesso nome di una fase esistente la riusa, le altre si aggiungono in fondo.",
            'Le ore già registrate sui task eliminati restano sulla commessa.',
          ],
        },
        {
          type: 'note',
          text: 'Serve il permesso di modificare la commessa. Su una commessa chiusa il board è in sola lettura e la voce non compare. Il campo **Modello di Task** nella scheda Info mostra il modello caricato per ultimo.',
        },
      ],
    },
]
