import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contracts',
  title: 'Contratti',
  summary: "Il contratto nasce quando un'offerta passa a uno stato Chiuso con esito positivo.",
  sections: [
    {
      id: 'overview',
      title: 'Come nasce un contratto',
      blocks: [
        {
          type: 'paragraph',
          text: "Il contratto non si crea a mano: nasce quando un'offerta passa a uno stato **Chiuso con esito positivo**, con data di **Accettazione** del giorno e lo stato **Predefinito** di **Stati Contratto**. Non nasce se la categoria prodotto è impostata per non generare contratti. Se l'offerta esce dallo stato positivo, il contratto viene **Sospeso** e QNet ricorda lo stato precedente.",
        },
      ],
    },
    {
      id: 'contract-actions',
      title: 'Azioni sul contratto',
      blocks: [
        {
          type: 'table',
          headers: ['Stato attuale', 'Azioni disponibili'],
          rows: [
            ['Aperto o In attesa', '**Modifica dati**, **Cambia stato**, **Valida contratto**, **Disdici contratto**'],
            ['Chiuso positivo', '**Programma**, **Disdici contratto**, **Riapri contratto**'],
            ['Chiuso negativo', '**Riapri contratto**'],
            ['Sospeso', '**Riapri contratto**'],
          ],
        },
        {
          type: 'list',
          items: [
            '**Valida contratto**: indica la **Data di validazione** (non futura).',
            '**Disdici contratto**: indica **Data di disdetta**, **Motivazione** e **Stato di destinazione**.',
            '**Modifica dati**: aggiorna **Data di scadenza**, **Data di rinnovo**, **Note di pagamento** e **Commenti**.',
            '**Riapri contratto**: riporta il contratto in lavorazione; se era sospeso, torna allo stato precedente.',
          ],
        },
        {
          type: 'note',
          text: "L'azione **Programma** genera una commessa dal contratto, assegnando le righe prodotto scelte a una nuova commessa. Il modulo Commesse è in fase di sviluppo: la guida dedicata verrà pubblicata quando sarà completato.",
        },
      ],
    },
    {
      id: 'expiry-and-renewal',
      title: 'Scadenze e rinnovi',
      blocks: [
        {
          type: 'paragraph',
          text: 'La colonna **Avviso** mostra **In scadenza** (scadenza entro 30 giorni) o **Da rinnovare** (rinnovo entro 30 giorni); se valgono entrambe, prevale **In scadenza**. I contratti in **Chiuso negativo** non mostrano avvisi.',
        },
        {
          type: 'tip',
          text: 'Compila sempre **Data di scadenza** e **Data di rinnovo** con **Modifica dati**: senza queste date gli avvisi non compaiono.',
        },
      ],
    },
  ],
}

export default guide
