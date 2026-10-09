import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-request-lines',
  title: 'Gestione righe RDA',
  summary: 'Approvazione ed evasione delle righe delle richieste di acquisto, anche in blocco.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Acquisti › Gestione righe RDA** e mostra **una riga per ogni riga di RDA**, di tutte le richieste che puoi vedere. Puoi filtrare, ordinare ed esportare; le **schede per stato** in alto restringono l’elenco a un solo stato.',
        },
        {
          type: 'paragraph',
          text: 'Le celle sono in sola lettura: per modificare il contenuto di una riga apri la sua RDA con l’azione **Apri**.',
        },
      ],
    },
    {
      id: 'who-can',
      title: 'Chi può cambiare stato',
      blocks: [
        {
          type: 'table',
          headers: ['Chi', 'Cosa può fare'],
          rows: [
            ['**Responsabile di funzione** della RDA', 'Da approvare → **Approvato** o **Rifiutato**.'],
            ['Chi ha il permesso di **evasione**', 'Approvato → **Ordinato** o **Stand by**; Ordinato → **Ricevuto** o **Stand by**.'],
            ['Chi ha il permesso di **gestire gli stati**', 'Qualsiasi stato diverso da quello attuale.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Chi ha più ruoli ottiene l’unione delle possibilità. Su una RDA **chiusa** non si cambia più alcuno stato.',
        },
      ],
    },
    {
      id: 'change-status',
      title: 'Cambiare stato',
      blocks: [
        {
          type: 'steps',
          items: [
            'Per una riga usa l’azione **Cambia stato** sulla riga; per più righe selezionale con le caselle (anche di RDA diverse) e scegli **Cambia stato** dal menu **Azioni**.',
            'La finestra riepiloga le **Righe selezionate** (numero, descrizione, quantità con unità di misura, totale e stato attuale) e, sotto **Stai operando come**, i tuoi ruoli su di esse: Responsabile di funzione, Evasione o Gestione stati.',
            'Scegli il **Nuovo stato** con i pulsanti: sono proposti solo gli stati consentiti **a tutte** le righe selezionate. Se non ce ne sono in comune lo vedi in un messaggio e non puoi confermare. Con una sola riga lo stato attuale è già selezionato e il pulsante di conferma si abilita solo scegliendone un altro.',
            'Scrivi una **motivazione** (facoltativa; con più righe vale per tutte) e conferma con **Conferma** (o **Conferma su N righe**).',
          ],
        },
        {
          type: 'warning',
          text: 'Il cambio è **tutto o niente**: se anche una sola riga non può passare allo stato scelto, nessuna riga viene modificata.',
        },
        {
          type: 'note',
          text: 'Quando l’ultima riga non finale di una RDA passa a uno stato finale (Ricevuto, Stand by o Rifiutato) la RDA si chiude da sola e ricevi un avviso.',
        },
      ],
    },
    {
      id: 'history',
      title: 'Storico della riga',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’azione **Storico stati** elenca, dal più recente, ogni cambio di stato: chi lo ha fatto, da quale stato a quale, la motivazione e se faceva parte di un **cambio massivo**.',
        },
      ],
    },
  ],
}

export default guide
