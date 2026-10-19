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
            'Premi **Nuovo segnatempo**: si apre la scheda con le righe chiuse.',
            'Per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** la riporta com\'era.',
            'Premi **Aggiungi**: controlla tutte le righe e crea il segnatempo.',
          ],
        },
        {
          type: 'table',
          headers: ['Campo', 'Note'],
          rows: [
            ['Titolo, Data, Tipo', 'Obbligatori (tipo: per esempio riunione o chiamata).'],
            ['Orario: Dalle ore / Alle ore', 'Facoltativi, ma se ne indichi uno servono entrambi.'],
            ['Orario: Tempo', 'Minuti, da 1 a 1440; calcolato dagli orari, correggibile a mano.'],
            ['Note', 'Facoltative.'],
            ['Collegamenti: Cliente, Opportunità, Commessa, Attività', 'Facoltativi, tutti in una sola riga.'],
            ['Fase', 'Compare solo con una Commessa e senza Attività: una delle fasi aperte della commessa, oppure nessuna.'],
          ],
        },
        {
          type: 'warning',
          text: "Se colleghi un task nel campo **Attività**, titolo, cliente, opportunità, commessa e fase vengono presi dal task.",
        },
        {
          type: 'note',
          text: 'La fase segue il task: una voce collegata a un task sta nella fase di quel task (per un sotto-task, la fase del task principale). Se in seguito il task cambia fase, oppure viene spostato sotto un altro task, tutte le sue voci — comprese quelle dei sotto-task — passano alla nuova fase. Le voci senza task mantengono la fase scelta a mano. Le voci registrate prima di questa modifica restano come sono finché il loro task non cambia fase per la prima volta: da quel momento si allineano anche loro. Nella scheda Task della commessa, accanto a ogni fase, trovi i **Minuti registrati** sul segnatempo, più il totale delle voci **Senza fase**.',
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
          text: 'Espandi un giorno per vedere le voci e aggiungere una nota della giornata. Su ogni voce usa **Apri** (o clic sul titolo), **Elimina** o **Cambia tipo**. Filtri e **Ordinamento** restringono e ordinano i giorni; **Esporta** scarica il report filtrato o il report mensile di un utente.',
        },
        {
          type: 'paragraph',
          text: "La scheda di una voce non ha un modulo di modifica: ogni riga si modifica sul posto. Premi la matita (o il valore), cambia il campo e premi **Salva**; **Annulla** o Esc lasciano la riga com'era. Orario (Dalle ore, Alle ore, Tempo) e Collegamenti (Cliente, Opportunità, Commessa, Fase, Attività) si modificano ciascuno in un'unica riga. Con un'Attività collegata il Titolo non si modifica, lo decide il task. **Elimina**, in fondo alla scheda, rimuove la voce dopo una conferma. Senza il permesso di modifica le righe non mostrano la matita.",
        },
      ],
    },
    {
      id: 'team-view',
      title: 'Vista team e target giornaliero',
      blocks: [
        {
          type: 'tip',
          text: 'I responsabili possono passare da **Vista personale** a **Il mio team** e vedere in sola lettura i dati dei collaboratori. Un collaboratore che risponde a più responsabili compare sotto ciascuno di loro. Accanto a ogni collaboratore compaiono le sue **Mansioni** (se ne ha più di una, separate da virgole).',
        },
        {
          type: 'note',
          text: 'Il target di una giornata lavorativa è la **Durata giornaliera standard** meno la **Durata pausa giornaliera**, impostate nella scheda utente; nei giorni non lavorativi il target è zero. Se la scheda utente non le indica, vale un target predefinito.',
        },
      ],
    },
    {
      id: 'absences-and-target',
      title: 'Assenze e target',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le assenze approvate (vedi la guida **Assenze**) cambiano il target del giorno: con un’assenza a giornata intera il target è zero; con un’assenza a ore il target si riduce di quelle ore. La card del giorno mostra il tipo di assenza e le ore.',
        },
        {
          type: 'note',
          text: 'Le richieste ancora in attesa di approvazione non contano: il target cambia solo quando la richiesta è approvata.',
        },
      ],
    },
  ],
}

export default guide
