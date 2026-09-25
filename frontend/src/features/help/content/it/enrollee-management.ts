import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'enrollee-management',
  title: 'Gestione Iscritti',
  summary:
    'Gestione Iscritti funziona come Gestione Richieste, con le stesse colonne, filtri, azioni, pannello e statistiche: mostra solo le richieste arrivate a uno stato Validato o Chiuso con esito positivo.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa cambia rispetto a Gestione Richieste',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Opportunità e Commesse › Gestione Iscritti**. È lo stesso banco di lavoro di **Gestione Richieste**: stesse colonne, filtri, azioni sulle righe, pannello e statistiche.',
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
      title: 'Le statistiche in Gestione Iscritti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il pannello statistiche è lo stesso di Gestione Richieste, con gli stessi filtri e le stesse colonne (vedi la guida di Gestione Richieste per i dettagli). Le differenze:',
        },
        {
          type: 'list',
          items: [
            'Conta solo le richieste che oggi sono in uno stato del gruppo **Validato** o **Chiuso con esito positivo**.',
            'Ha un proprio permesso **Genera report** e filtri memorizzati separati.',
            'Il file generato si chiama enrollee-management-report-DAL_AL (o -from-DAL / -to-AL con un solo estremo, nessuna data se il periodo è vuoto).',
            '**N. Nuovi contatti non gestiti** (con o senza periodo) vale sempre 0: nessuna richiesta qui è in stato Aperto.',
            '**N. Richiami non gestiti** (con o senza periodo) conta solo le richieste in Validato; **N. Potenziali associati** senza periodo conta le richieste che oggi sono in Validato.',
          ],
        },
      ],
    },
  ],
}

export default guide
