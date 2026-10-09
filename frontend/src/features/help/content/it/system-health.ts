import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'system-health',
  title: 'Stato del sistema',
  summary:
    'Stato del sistema mostra in tempo reale se Database, Email, Code e Sicurezza funzionano e quante persone sono online in questo momento.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è e chi la vede',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Develop › Stato del sistema** ed è riservata al ruolo **super-admin**: per gli altri utenti la voce di menu non compare. In alto vedi l\'**esito complessivo** e l\'ora dell\'ultima verifica; sotto, la card **Utenti online** e una card per ogni sottosistema.',
        },
      ],
    },
    {
      id: 'statuses',
      title: 'Significato degli stati',
      blocks: [
        {
          type: 'table',
          headers: ['Stato', 'Significato'],
          rows: [
            ['**OK**', 'Il controllo è superato.'],
            ['**Degradato**', 'Il sistema funziona ma c\'è qualcosa da sistemare, ad esempio job falliti in coda o una impostazione di sicurezza non ideale.'],
            ['**KO**', 'Il sottosistema non è raggiungibile o non è configurato: serve un intervento.'],
          ],
        },
        {
          type: 'note',
          text: 'L\'esito complessivo è il peggiore tra i quattro controlli. Gli utenti online non influiscono sull\'esito. Lo stato è sempre scritto a parole, non solo indicato dal colore.',
        },
      ],
    },
    {
      id: 'checks',
      title: 'Cosa controlla ogni card',
      blocks: [
        {
          type: 'list',
          items: [
            '**Database**: connessione raggiungibile, nome del database e tempo di risposta.',
            '**Email**: mailer e transport configurati e presenza delle credenziali di invio. Non viene inviata nessuna email di prova.',
            '**Code**: connessione della coda, job in attesa e job falliti. Job falliti o troppi job in attesa rendono lo stato Degradato.',
            '**Sicurezza**: modalità debug, ambiente, HTTPS, scadenza dei token, CORS, chiave dell\'applicazione, segreti email e registro attività.',
          ],
        },
      ],
    },
    {
      id: 'online-users',
      title: 'Utenti online',
      blocks: [
        {
          type: 'paragraph',
          text: 'La card mostra il numero di persone online e l\'elenco con nome, email e **ultima attività**. Se qualcuno sta usando l\'impersonificazione compare il badge **impersona** seguito dal nome dell\'utente impersonato.',
        },
        {
          type: 'list',
          items: [
            'Una persona è online se ha avuto attività negli **ultimi 2 minuti**.',
            'Finché l\'applicazione è aperta, il browser invia un segnale di presenza **ogni 60 secondi**, quindi anche chi la tiene aperta senza usarla resta online.',
            'Ogni persona è contata **una sola volta**, anche con più dispositivi o schede.',
            'Con l\'impersonificazione l\'utente online è **chi impersona**, non l\'utente impersonato.',
            'Gli utenti **disattivati** non sono mai contati.',
          ],
        },
      ],
    },
    {
      id: 'refresh',
      title: 'Aggiornamento',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si aggiorna da sola **ogni 30 secondi**. Per una verifica immediata fai clic su **Ricontrolla**.',
        },
      ],
    },
  ],
}

export default guide
