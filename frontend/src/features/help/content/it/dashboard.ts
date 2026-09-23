import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'dashboard',
  title: 'Dashboard',
  summary: 'La Dashboard è la prima pagina dopo l’accesso: riassume i tuoi task aperti, il segnatempo e i numeri principali dei moduli.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa trovi',
      blocks: [
        { type: 'paragraph', text: 'La **Dashboard** si apre subito dopo aver premuto **Accedi**. È divisa in blocchi, dall’alto verso il basso: **Attività da completare** (con le statistiche dei Task), **Segnatempo**, e le statistiche di **Opportunità**, **Offerte**, **Lead** e **Anagrafiche**.' },
        { type: 'note', text: 'Vedi solo i blocchi dei moduli per cui hai il permesso. Se non ne hai nessuno, la pagina mostra “Nessun contenuto disponibile”.' },
      ],
    },
    {
      id: 'tasks-to-complete',
      title: 'Attività da completare',
      blocks: [
        { type: 'paragraph', text: 'Cinque riquadri contano i task **aperti** che puoi vedere, sotto-task compresi. Ogni riquadro mostra anche il **tempo stimato** complessivo.' },
        {
          type: 'table',
          headers: ['Riquadro', 'Cosa conta'],
          rows: [
            ['Tutti', 'Tutti i task aperti che puoi vedere.'],
            ['Assegnati a me', 'I task di cui sei assegnatario.'],
            ['Assegnati da me', 'I task di cui sei richiedente, se non sei anche assegnatario.'],
            ['Creati da me', 'I task che hai creato, se il richiedente è un’altra persona.'],
            ['Osservati da me', 'I task che segui come osservatore.'],
          ],
        },
        { type: 'paragraph', text: 'Nel riquadro **Assegnati da me** compare il contrassegno **Da validare** quando alcuni di quei task aspettano la tua validazione.' },
        { type: 'steps', items: ['Premi un riquadro: si apre la lista **Task** già filtrata con gli stessi criteri.', 'Premi il contrassegno **Da validare** per vedere solo i task in validazione assegnati da te.'] },
        { type: 'tip', text: 'Il filtro aperto dalla Dashboard vale solo per quella visita: i filtri che avevi salvato nella lista Task non cambiano, finché non premi **Applica** o **Reimposta**.' },
        { type: 'paragraph', text: 'Sotto i riquadri, nello stesso blocco, trovi le statistiche dei Task: Scaduti, In scadenza oggi, Stimato, Effettivo e i grafici per stato, per priorità e dei nuovi task per mese.' },
      ],
    },
    {
      id: 'time-entries',
      title: 'Segnatempo',
      blocks: [
        { type: 'paragraph', text: 'Mostra il riepilogo del tuo segnatempo e il polso operativo, come nella pagina **Segnatempo**. Scegli il periodo con **Giornaliero** (preimpostato), **Settimanale**, **Mensile** o **Annuale**.' },
      ],
    },
    {
      id: 'module-statistics',
      title: 'Statistiche dei moduli',
      blocks: [
        { type: 'paragraph', text: 'I blocchi **Opportunità**, **Offerte**, **Lead** e **Anagrafiche** mostrano gli stessi numeri e grafici del pannello **Statistiche** di ciascun modulo, sempre aperti.' },
        { type: 'paragraph', text: 'Il blocco **Offerte** mostra il numero di offerte, i **Ricavi netti**, il **Margine netto**, le offerte **Vinte**, la ripartizione per stato e le nuove offerte per mese.' },
      ],
    },
  ],
}

export default guide
