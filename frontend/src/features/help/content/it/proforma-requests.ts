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
          text: 'Le richieste non si creano da questo elenco: si inviano dal pulsante **€** dell\'elenco **Commesse** (vedi la guida Commesse). Per ogni commessa il sistema genera **una richiesta** per le righe di tipo **Consulenza** e **una richiesta per ciascun fornitore** delle righe di tipo **Ente**.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
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
      id: 'issue',
      title: 'Emettere il proforma',
      blocks: [
        {
          type: 'steps',
          items: [
            'Sulla riga della richiesta **Da evadere** premi l’azione **Emetti proforma** (serve il permesso di creazione fatture).',
            'Nella finestra controlla l’intestazione, aggiungi le righe dalle **Righe disponibili** della commessa (**Aggiungi** o **Aggiungi tutte**) e verifica le scadenze e i totali.',
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
          text: 'L\'eliminazione è definitiva. Se elimini una richiesta non evasa, la commessa potrà riceverne una nuova.',
        },
      ],
    },
  ],
}

export default guide
