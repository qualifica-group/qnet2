import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'proforma-requests',
  title: 'Richieste Proforma',
  summary: 'Le richieste di emissione proforma inviate alla Contabilità dalle commesse.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Contabilità › Attiva › Richieste Proforma**. Elenca le richieste di emissione proforma create dalle commesse: all\'apertura mostra solo quelle **da evadere**. Puoi filtrare, ordinare ed esportare l\'elenco.',
        },
        {
          type: 'paragraph',
          text: 'Per vedere anche le richieste **evase** rimuovi il filtro iniziale sulla colonna **Stato** (oppure usa **Cancella filtri**).',
        },
      ],
    },
    {
      id: 'how-created',
      title: 'Come nascono le richieste',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le richieste non si creano da questo elenco: si inviano dal pulsante **€** dell\'elenco **Commesse** (vedi la guida Commesse). Una commessa genera le richieste **una sola volta**: se ne ha già una (da evadere o evasa) non se ne possono inviare altre. Per ogni commessa il sistema genera **una richiesta** per le righe di tipo **Consulenza** e **una richiesta per ciascun fornitore** delle righe di tipo **Ente**. La richiesta Ente non fattura all\'ente il prezzo di vendita ma la **provvigione** che ci spetta (vedi **Richieste Ente e provvigioni**).',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['**Cliente**', 'Il cliente della commessa (l\'anagrafica dell\'opportunità da cui nasce l\'offerta).'],
            ['**Tipo**', 'Consulenza oppure Ente.'],
            ['**Fornitore**', 'Il fornitore dell\'Ente a cui si riferisce la richiesta (vuoto per le Consulenze).'],
            ['**Modalità di pagamento**', 'Quella dell\'offerta al momento dell\'invio: se l\'offerta cambia dopo, la richiesta resta com\'era.'],
            ['**Stato**', '**Da evadere** finché la fatturazione non emette la proforma, poi **Evasa**.'],
            ['**Assegnata a / Richiesta da**', 'L\'utente che ha inviato la richiesta.'],
          ],
        },
      ],
    },
    {
      id: 'multi-quote-work-order',
      title: 'Commesse multi-offerta',
      blocks: [
        {
          type: 'paragraph',
          text: 'Su una commessa **multi-offerta** le richieste **Consulenza** sono generate **per ogni offerta**. Le richieste **Ente** sono una per ogni **fornitore** e per ogni **società emittente**: se lo stesso ente compare in più offerte della stessa società nasce una sola richiesta; se le offerte sono di società diverse, una richiesta per società.',
        },
        {
          type: 'list',
          items: [
            'Ogni richiesta riporta la propria **offerta**. Per una richiesta Ente che raccoglie più offerte è l\'offerta con il **codice più basso**.',
            'Cliente, azienda emittente e modalità di pagamento della fattura vengono presi dall\'**offerta della richiesta**, quindi ogni cliente riceve il proprio proforma.',
          ],
        },
      ],
    },
    {
      id: 'institution-commission',
      title: 'Richieste Ente e provvigioni',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella richiesta **Ente** ogni servizio viene proposto con l\'importo della **provvigione del fornitore**, calcolata dalle regole del ruolo **Fornitore** del **Configuratore Commissioni** (le "% provvigioni" dell\'ente), valide alla data di emissione. La regola si cerca prima sul servizio, poi sulla sua categoria e sulle categorie superiori.',
        },
        {
          type: 'table',
          headers: ['Regola', 'Importo proposto'],
          rows: [
            ['**Percentuale**', 'Imponibile di vendita della riga (prezzo × quantità) × percentuale. Esempio: 2 × 500 € al 10 % = 100 €.'],
            ['**Importo fisso**', 'L\'importo della regola, una volta per riga, qualunque sia la quantità.'],
            ['**Nessuna regola**', '0 €, con l\'avviso **Nessuna regola provvigionale**: aggiungi la regola nel Configuratore Commissioni o correggi il prezzo a mano.'],
          ],
        },
        { type: 'paragraph', text: 'Il servizio entra nel proforma con quantità 1 e prezzo uguale alla provvigione; l\'IVA è quella della riga d\'offerta. Il prezzo resta modificabile. La richiesta **Consulenza** fattura invece i prezzi di vendita.' },
      ],
    },
    {
      id: 'issue',
      title: 'Emettere il proforma',
      blocks: [
        {
          type: 'steps',
          items: [
            'Sulla riga della richiesta **Da evadere** premi l’azione **Emetti proforma** (serve il permesso di creazione fatture).',
            'Nella finestra controlla l’intestazione, aggiungi le righe dalle **Righe disponibili** della commessa (**Aggiungi** o **Aggiungi tutte**) e verifica le scadenze e i totali. Le righe disponibili sono solo quelle della richiesta: Consulenza o Ente, e per l’Ente solo quelle del suo fornitore.',
            'Premi **Emetti**: il proforma riceve il numero N/AAAA della società emittente e la richiesta passa a **Evasa**.',
          ],
        },
        { type: 'paragraph', text: 'Una volta evasa, l’azione è disattivata. Se elimini il proforma da **Fatture Attive**, la richiesta torna **Da evadere** e puoi emetterlo di nuovo. Vedi la guida **Fatture Attive**.' },
      ],
    },
    {
      id: 'manage',
      title: 'Aprire, modificare ed eliminare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri una richiesta con l\'azione **Visualizza** per vedere i dati, le **Note** e l\'**Attività**.',
            'Con il permesso di modifica puoi cambiare **solo la nota** per la Contabilità.',
            'Usa **Note** per aprire la discussione della richiesta e scrivere note CRM.',
            'Con il permesso di eliminazione, **Elimina** rimuove la richiesta dopo conferma.',
          ],
        },
        {
          type: 'warning',
          text: 'L\'eliminazione è definitiva. Una richiesta **evasa** (con fattura) non si può eliminare e l\'azione Elimina non compare: se elimini la fattura, la richiesta torna **Da evadere** e allora puoi eliminarla. Se elimini una richiesta non evasa, la commessa potrà riceverne una nuova.',
        },
      ],
    },
  ],
}

export default guide
