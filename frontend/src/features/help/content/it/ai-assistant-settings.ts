import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ai-assistant-settings',
  title: 'Impostazioni assistente AI',
  summary:
    'Qui il super-admin sceglie il modello dell\'assistente AI, imposta come OpenRouter instrada le richieste, scrive le istruzioni aggiuntive e accende o spegne l\'assistente.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è e chi la vede',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è in **Develop › Impostazioni assistente AI** ed è riservata al ruolo **super-admin**: gli altri utenti non vedono la voce di menu e il server rifiuta le loro richieste. L\'assistente usa subito i valori salvati, senza riavvii.',
        },
        {
          type: 'note',
          text: 'La chiave OpenRouter non si imposta qui: sta nella configurazione del server. La pagina dice solo se è **Presente** o **Mancante** e non la mostra mai.',
        },
      ],
    },
    {
      id: 'model',
      title: 'Scegliere il modello',
      blocks: [
        {
          type: 'list',
          items: [
            'L\'elenco mostra solo modelli che chiamano strumenti e leggono e scrivono **solo testo**. Cerca per nome o id.',
            'Ogni riga mostra i prezzi in **dollari per milione di token** (ingresso, uscita, lettura da cache) e la dimensione del contesto.',
            'Sotto l\'elenco, i **fornitori senza conservazione dei dati** del modello scelto, con disponibilità e, quando OpenRouter li pubblica, velocità e latenza.',
            'L\'elenco arriva da QNET2, che lo legge da OpenRouter e lo tiene per pochi minuti. Se OpenRouter non risponde compare un messaggio e puoi riprovare più tardi.',
          ],
        },
      ],
    },
    {
      id: 'routing',
      title: 'Instradamento',
      blocks: [
        {
          type: 'table',
          headers: ['Impostazione', 'Significato'],
          rows: [
            ['**Solo conservazione zero dei dati**', 'Le richieste arrivano solo a fornitori che non conservano i prompt.'],
            ['**Raccolta dei dati**', '"Non consentire" esclude i fornitori che potrebbero raccogliere dati.'],
            ['**Richiedi il supporto degli strumenti**', 'Esclude i fornitori che ignorano un parametro della richiesta, come gli strumenti.'],
            ['**Ordina i fornitori per**', 'Velocità, prezzo o latenza: il primo fornitore in quell\'ordine è provato per primo.'],
          ],
        },
      ],
    },
    {
      id: 'instructions',
      title: 'Istruzioni aggiuntive',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il testo si aggiunge **dopo** il nucleo fisso dell\'assistente e non lo sostituisce: le regole di sicurezza e i permessi di ciascuna persona non si cambiano da qui. Il testo iniziale ha sette parti: ruolo, vincoli, contesto, formato, esempi, risultati da evitare e criteri di riuscita. C\'è un limite di caratteri, indicato sotto il campo.',
        },
      ],
    },
    {
      id: 'switch-and-caps',
      title: 'Interruttore generale e tetti di spesa',
      blocks: [
        {
          type: 'list',
          items: [
            'Con l\'assistente **spento**, tutti ricevono un messaggio fisso e nessun modello viene chiamato.',
            'I **tetti di spesa mensili** (per persona e totale) sono in dollari; lascia vuoto un campo per nessun tetto.',
            'Ogni salvataggio registra chi ha cambiato le impostazioni e quando: lo vedi in fondo alla pagina.',
          ],
        },
      ],
    },
  ],
}

export default guide
