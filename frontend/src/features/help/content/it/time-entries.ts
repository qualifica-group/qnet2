import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'time-entries',
  title: 'Segnatempo',
  summary: 'Il Segnatempo registra il tempo dedicato al lavoro. Il tempo si inserisce a mano: non c\'è un cronometro.',
  sections: [
    {
      id: 'overview',
      title: "Cos'è il Segnatempo",
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova alla voce **Segnatempo** del menu.',
        },
      ],
    },
    {
      id: 'recording-time',
      title: 'Registrare il tempo',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo segnatempo**.',
            'Compila i campi.',
            'Premi **Aggiungi**.',
          ],
        },
        {
          type: 'table',
          headers: ['Campo', 'Note'],
          rows: [
            ['Titolo, Data, Tipo', 'Obbligatori (tipo: per esempio riunione o chiamata).'],
            ['Dalle ore / Alle ore', 'Facoltativi, ma se ne indichi uno servono entrambi.'],
            ['Tempo', 'Minuti, da 1 a 1440; calcolato dagli orari, correggibile a mano.'],
            ['Note', 'Facoltative.'],
            ['Cliente, Opportunità, Commessa, Attività', 'Collegamenti facoltativi.'],
            ['Fase', 'Compare solo con una Commessa e senza Attività: una delle fasi aperte della commessa, oppure nessuna.'],
          ],
        },
        {
          type: 'warning',
          text: "Se colleghi un task nel campo **Attività**, titolo, cliente, opportunità, commessa e fase vengono presi dal task.",
        },
        {
          type: 'note',
          text: 'La fase resta quella registrata: se in seguito il task cambia fase o la fase viene chiusa, le voci già salvate non cambiano. Nella scheda Task della commessa, accanto a ogni fase, trovi i **Minuti registrati** sul segnatempo, più il totale delle voci **Senza fase**.',
        },
      ],
    },
    {
      id: 'from-a-task',
      title: 'Registrare il tempo da un task',
      blocks: [
        {
          type: 'tip',
          text: 'Puoi registrare il tempo anche dal dettaglio del task: scheda **Segnatempo**, compila **Nuovo intervallo** e premi **Aggiungi segnatempo**.',
        },
      ],
    },
    {
      id: 'reviewing-and-editing',
      title: 'Consultare e modificare',
      blocks: [
        {
          type: 'paragraph',
          text: 'La card **Periodo** offre le viste **Giornaliero**, **Settimanale**, **Mensile**, **Annuale** o **Intervallo personalizzato**. Sotto trovi:',
        },
        {
          type: 'list',
          items: [
            '**Panoramica**: target del periodo, tempo tracciato, focus medio e anomalie.',
            '**Polso operativo**: copertura e tipologie di attività più frequenti.',
            "L'elenco dei giorni, ognuno In target, Sotto target, Oltre target o Nessun target.",
          ],
        },
        {
          type: 'paragraph',
          text: 'Espandi un giorno per vedere le voci e aggiungere una nota della giornata. Su ogni voce usa **Modifica**, **Elimina** o **Cambia tipo**, poi **Aggiorna**. Filtri e **Ordinamento** restringono e ordinano i giorni; **Esporta** scarica il report filtrato o il report mensile di un utente.',
        },
      ],
    },
    {
      id: 'team-view',
      title: 'Vista team e target giornaliero',
      blocks: [
        {
          type: 'tip',
          text: 'I responsabili possono passare da **Vista personale** a **Il mio team** e vedere in sola lettura i dati dei collaboratori. Un collaboratore che risponde a più responsabili compare sotto ciascuno di loro.',
        },
        {
          type: 'note',
          text: 'Il target di una giornata lavorativa è la **Durata giornaliera standard** meno la **Durata pausa giornaliera**, impostate nella scheda utente; nei giorni non lavorativi il target è zero. Se la scheda utente non le indica, vale un target predefinito.',
        },
      ],
    },
  ],
}

export default guide
