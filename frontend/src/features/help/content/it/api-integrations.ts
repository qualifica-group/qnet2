import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'api-integrations',
  title: 'API e integrazioni',
  summary:
    'API e integrazioni permette ai sistemi esterni di usare le API di QNet con una chiave dedicata e di consultare la documentazione, che si aggiorna da sola.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono i client API',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Amministrazione › API e integrazioni**. Un **client API** rappresenta un sistema esterno (ad esempio un gestionale o un sito) che usa le **stesse API di QNet** con una **chiave** propria. Con la sola chiave il sistema agisce come l\'**utente tecnico** del client, con permessi da super-admin; con la chiave può anche far accedere un utente QNet e agire con i permessi di quell\'utente. Tutte le API sono elencate nella scheda **Documentazione**.',
        },
        {
          type: 'note',
          text: 'La pagina è visibile a chi ha il permesso di vedere i client API; creare, modificare, ruotare e revocare richiedono permessi distinti.',
        },
      ],
    },
    {
      id: 'create-client',
      title: 'Creare un client',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri la scheda **Client API** e fai clic su **Nuovo client**.',
            'Scrivi un **nome** che riconosca il sistema esterno e, se vuoi, una descrizione.',
            'Se serve imposta il **limite di richieste** e la **scadenza** della chiave, poi fai clic su **Crea client**.',
          ],
        },
        {
          type: 'tip',
          text: 'Per modificare un client apri la sua riga. Per sospenderlo senza eliminarlo disattiva **Client attivo**.',
        },
      ],
    },
    {
      id: 'key-shown-once',
      title: 'La chiave viene mostrata una sola volta',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dopo la creazione compare una finestra con la **chiave in chiaro**. Usa **Copia** e conservala subito nel sistema esterno: chiusa la finestra, la chiave non è più consultabile, nemmeno da un amministratore. In elenco resta visibile solo la fine della chiave per riconoscerla.',
        },
        {
          type: 'warning',
          text: 'Tratta la chiave come una password: chi la possiede può chiamare le API come super-admin. Non condividerla per email o chat.',
        },
      ],
    },
    {
      id: 'service-user',
      title: 'L\'utente tecnico del client',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni client ha un **utente tecnico**, creato in automatico con il nome "API · nome del client". Con la sola chiave si agisce come questo utente, con permessi da super-admin: le operazioni risultano fatte da lui e compare come **autore** nello storico delle modifiche. Lo vedi in sola lettura aprendo il client.',
        },
        {
          type: 'list',
          items: [
            'Non può accedere a QNet e non compare nell\'elenco Utenti.',
            'Si rinomina insieme al client; se elimini il client resta, disattivato, per conservare lo storico.',
          ],
        },
      ],
    },
    {
      id: 'user-login',
      title: 'Far accedere un utente QNet via API',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il sistema esterno invia email e password a **POST /auth/client-login** usando la chiave del client come Bearer e riceve un **token utente** con la sua data di scadenza (**expires_at**). Le chiamate successive usano quel token e le operazioni risultano fatte dall\'utente, con i **suoi permessi**.',
        },
        {
          type: 'list',
          items: [
            'Il logout è **POST /auth/logout** e revoca il token.',
            'Il token smette di funzionare anche se l\'utente o il client vengono disattivati; ruotare la chiave del client non lo invalida.',
            'Utenti inattivi e utenti tecnici non possono accedere: l\'errore è lo stesso delle credenziali errate.',
          ],
        },
        {
          type: 'warning',
          text: 'Usa il login utente solo con sistemi fidati: la password dell\'utente transita dall\'integrazione.',
        },
      ],
    },
    {
      id: 'rotate-revoke',
      title: 'Ruotare o revocare una chiave',
      blocks: [
        {
          type: 'list',
          items: [
            '**Ruota chiave**: emette una nuova chiave e disattiva subito quella precedente. La nuova chiave appare nella stessa finestra, una sola volta.',
            '**Revoca client**: elimina il client e tutte le sue chiavi e accessi utente; il sistema esterno riceve errore di autenticazione.',
            'Entrambe le azioni chiedono conferma.',
          ],
        },
      ],
    },
    {
      id: 'expiry-rate-limit',
      title: 'Scadenza e limite di richieste',
      blocks: [
        {
          type: 'table',
          headers: ['Impostazione', 'Effetto'],
          rows: [
            ['**Scadenza della chiave**', 'Dopo la data indicata la chiave smette di funzionare. Vuota: la chiave non scade.'],
            ['**Limite di richieste**', 'Numero massimo di chiamate al minuto del client (da 1 a 1000), condiviso tra chiave e accessi utente. Vuoto: si applica il limite predefinito di 60 al minuto. Oltre il limite il sistema esterno riceve un errore 429 e deve attendere.'],
          ],
        },
      ],
    },
    {
      id: 'documentation',
      title: 'Consultare e scaricare la documentazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'La scheda **Documentazione** elenca le operazioni delle API di QNet, raggruppate per tag (chiusi di default), con metodo, indirizzo, parametri e struttura di richiesta e risposta. Usa il campo **Cerca** per filtrare per percorso, descrizione o tag. In cima trovi l\'**autenticazione**, con gli esempi per le due modalità. Le API sono quelle usate da QNet e possono cambiare con gli aggiornamenti: la documentazione si aggiorna da sola.',
        },
        {
          type: 'list',
          items: [
            '**Scarica OpenAPI**: il file di specifica da dare agli integratori.',
            '**Scarica collection Postman**: la collection pronta da importare.',
          ],
        },
      ],
    },
    {
      id: 'postman',
      title: 'Importare la collection in Postman',
      blocks: [
        {
          type: 'steps',
          items: [
            'Scarica la collection Postman dalla scheda **Documentazione**.',
            'In Postman scegli **Import** e seleziona il file scaricato.',
            'Apri le variabili della collection e inserisci la chiave del client in **api_key**; **base_url** è già compilata.',
            'Per agire come un utente, invia la richiesta di login utente: salva da sola il token nella variabile **user_token**.',
          ],
        },
      ],
    },
  ],
}

export default guide
