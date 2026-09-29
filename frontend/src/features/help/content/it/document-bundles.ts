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
          text: "Il modulo si trova in **Configurazione › Modelli documenti**. Un modello documenti è un insieme di file (per esempio un modulo di consegna o un'informativa) che il composer email di una commessa può allegare tutto insieme con **Da modello documenti**.",
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
            'Apri **Configurazione › Modelli documenti** e premi **Nuovo modello documenti**.',
            'Compila **Nome** e, se serve, **Descrizione**; imposta **Attivo**.',
            'Salva il modello.',
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
