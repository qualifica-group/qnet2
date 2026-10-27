import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-bundles',
  title: 'Modelli documenti',
  summary: 'Insiemi di file riusabili da allegare alle email delle commesse.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: "Il modulo si trova in **Configurazione › Documenti › Modelli documenti**. Un modello documenti è un insieme di file (per esempio un modulo di consegna o un'informativa) che il composer email di una commessa può allegare tutto insieme con **Da modello documenti**.",
        },
        {
          type: 'note',
          text: 'Solo i modelli **Attivi** compaiono nel selettore del composer; un modello disattivato resta modificabile ed eliminabile da qui, con tutti i suoi file.',
        },
      ],
    },
    {
      id: 'create-bundle',
      title: 'Creare un modello e caricare i file',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Configurazione › Documenti › Modelli documenti** e premi **Nuovo modello documenti**.',
            "Si apre la scheda con le righe chiuse: per **Nome**, **Descrizione** e **Attivo** premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com'era.",
            'Premi **Salva**: il modello viene creato con tutte le righe insieme.',
            "Nel dettaglio del modello, nella scheda **File**, trascina uno o più file nell'area di caricamento oppure cliccala per sceglierli.",
          ],
        },
        {
          type: 'tip',
          text: 'Il numero di file caricati compare nella colonna **File** della tabella; rimuovi un file dal dettaglio con il cestino sulla sua riga.',
        },
      ],
    },
    {
      id: 'edit-bundle',
      title: 'Modificare un modello',
      blocks: [
        {
          type: 'paragraph',
          text: "Non c'è un modulo di modifica a parte: apri il modello con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com'era. Si apre una riga alla volta.",
        },
      ],
    },
    {
      id: 'usage',
      title: 'Dove si usa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un modello **Attivo** è tra le fonti di allegati del composer email di una commessa.',
        },
      ],
    },
  ],
}

export default guide
