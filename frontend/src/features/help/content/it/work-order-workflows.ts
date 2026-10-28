import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-order-workflows',
  title: 'Configuratore Stati Commessa',
  summary:
    'Il configuratore definisce i set di stati di lavorazione delle commesse, con criteri che decidono a quali commesse si applica ciascun set.',
  sections: [
    {
      id: 'overview',
      title: "Cos'è un set di stati",
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova nel menu **Configurazione › Commerciale** (pagina **Configuratore Stati Commessa**, subito dopo il Configuratore Stati Offerta) e definisce gli stati di lavorazione delle commesse. Ogni set ha un nome, dei criteri che decidono a quali commesse si applica e un proprio elenco di stati. Lo stato scelto per una commessa è il campo **Stato lavorazione**, distinto dallo stato calcolato (Aperta, In corso, Completata, Chiusa).',
        },
      ],
    },
    {
      id: 'edit-in-place',
      title: 'Modificare un set di stati',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non c\'è una pagina di modifica: apri il set dall\'elenco e modifica direttamente nel dettaglio. Passa il mouse su **Nome**, **Attivo**, **Criteri** o **Stati** (su touch la matita è sempre visibile) e clicca la matita: compare l\'editor di quella riga, che confermi con **Salva** o annulli con **Annulla**. Si modifica una riga alla volta e il salvataggio riguarda solo quella riga. Le matite compaiono solo se hai il permesso di modifica.',
        },
        {
          type: 'paragraph',
          text: 'Con **Nuovo set di stati** si apre lo stesso dettaglio in creazione: **Nome** e **Attivo** sono righe da confermare con **Fatto** (o **Ripristina**), mentre gli editor di **Criteri** e **Stati** sono già aperti. Il set nasce solo con **Salva**; se esci prima, QNet chiede conferma.',
        },
      ],
    },
    {
      id: 'criteria',
      title: 'Criteri',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un set vale per le commesse che soddisfano tutti i suoi criteri (almeno uno): **Funzione aziendale**, **Categoria prodotto** e **Categoria prodotto (ramo)** (include le sottocategorie). I criteri si valutano sulle righe di ricavo programmate nella commessa; la funzione aziendale è quella effettiva della categoria, anche se ereditata da una categoria superiore. Una commessa senza righe di ricavo usa gli stati di default.',
        },
        {
          type: 'paragraph',
          text: 'Se una commessa corrisponde a più set, vince quello con più criteri; a parità, quello con la categoria più vicina. Due set non possono avere gli stessi criteri.',
        },
      ],
    },
    {
      id: 'processing-statuses',
      title: 'Stati di lavorazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni set nasce con tre stati fissi: **Aperta** in testa, **Chiusa con esito positivo** e **Chiusa con esito negativo** in coda. In mezzo aggiungi stati con **Aggiungi stato** e li riordini trascinandoli. Per ognuno indichi **Nome stato**, **Gruppo** (Aperta, In lavorazione, Chiusa con esito positivo, Chiusa con esito negativo), **Descrizione dello stato** e **% avanzamento** (da 0 a 100). Per gli stati fissi puoi cambiare nome, descrizione, colore e % avanzamento, non il gruppo. La chiusura forzata di una commessa la porta su **Chiusa con esito negativo**; se la riapri torna su **Aperta**.',
        },
        {
          type: 'paragraph',
          text: 'Le commesse senza set attivo usano gli **Stati di default globali**, modificabili con **Stati di default**.',
        },
        {
          type: 'note',
          text: 'In questa versione la **% avanzamento** è solo un dato configurato e mostrato: scegliere lo stato di una commessa non muove la sua percentuale di avanzamento.',
        },
      ],
    },
    {
      id: 'reassignment',
      title: 'Cosa succede alle commesse',
      blocks: [
        {
          type: 'list',
          items: [
            'Una nuova commessa parte dallo stato **Aperta** del set che le corrisponde.',
            'Una commessa può passare solo agli stati del set che le corrisponde in quel momento.',
            'Se le righe programmate della commessa cambiano e il set applicabile è un altro, lo stato resta se esiste anche nel nuovo set, altrimenti diventa lo stato fisso equivalente o **Aperta**.',
            'Eliminando un set, le sue commesse passano al set corrispondente o agli stati di default.',
          ],
        },
        {
          type: 'tip',
          text: 'Per sospendere un set, spegni **Attivo** invece di eliminarlo.',
        },
      ],
    },
  ],
}

export default guide
