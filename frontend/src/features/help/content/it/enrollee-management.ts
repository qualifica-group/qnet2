import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'enrollee-management',
  title: 'Gestione Iscritti',
  summary:
    'Gestione Iscritti funziona come Gestione Richieste, con le stesse colonne, filtri e azioni: mostra solo le richieste arrivate a uno stato Validato o Chiuso con esito positivo.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa cambia rispetto a Gestione Richieste',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Opportunità e Commesse › Gestione Iscritti**. È lo stesso banco di lavoro di **Gestione Richieste**: stesse colonne, filtri, ricerca (dal terzo carattere, per parole che iniziano con quanto scrivi), azioni sulle righe.',
        },
        {
          type: 'table',
          headers: ['Differenza', 'Dettaglio'],
          rows: [
            [
              'Cosa mostra',
              'Solo le richieste con stato di lavorazione del gruppo **Validato** o **Chiuso con esito positivo**.',
            ],
            [
              'Creazione',
              'Non ha il pulsante di creazione: una richiesta entra negli iscritti solo cambiando stato.',
            ],
            ['Permessi', 'Ha permessi propri, separati da quelli di Gestione Richieste.'],
            [
              'Chi vede cosa',
              'Senza altri permessi vedi solo le richieste di cui sei operatore. **Visualizza sede fisica** aggiunge quelle della tua sede fisica (non delle sedi remote); **Visualizza per sede** quelle di tutte le tue sedi; **Visualizza tutti** tutte.',
            ],
            [
              'Notifiche di trasferimento',
              'Stesse regole di Gestione Richieste; il riepilogo di ogni trasferimento arriva a chi ha **Visualizza tutti** in Gestione Iscritti.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'Per creare, lavorare o assegnare le richieste vedi la guida di **Gestione Richieste**: le stesse azioni valgono qui.',
        },
      ],
    },
    {
      id: 'statistics-differences',
      title: "Le statistiche non ci sono più",
      blocks: [
        {
          type: 'paragraph',
          text: "Le statistiche non sono più disponibili in Gestione Iscritti: tabella senza pannello né pulsante statistiche. Le statistiche sulle richieste hanno una pagina propria, **Statistiche Gestione Richieste** (vedi la relativa guida).",
        },
      ],
    },
  ],
}

export default guide
