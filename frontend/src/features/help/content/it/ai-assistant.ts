import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ai-assistant',
  title: 'Assistente AI',
  summary:
    'Un assistente a cui fare domande su QNet: conosce la guida della pagina in cui sei, può leggere i record che menzioni con @ e i file che alleghi.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è e a cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'L\'assistente AI risponde in una chat, scrivendo la risposta man mano che la prepara. Puoi chiedergli come si usa una pagina, di riassumere un record, di preparare una bozza di email o di spiegare un documento allegato.',
        },
        {
          type: 'note',
          text: 'L\'assistente non va a cercare dati da solo: vede soltanto ciò che gli scrivi, i record che menzioni con @, i file che alleghi e la guida in-app della pagina in cui ti trovi.',
        },
      ],
    },
    {
      id: 'how-to-open',
      title: 'Come aprirlo',
      blocks: [
        {
          type: 'list',
          items: [
            'Il pulsante tondo con le stelline in basso a destra apre una finestra di chat sopra la pagina in cui stai lavorando (a tutto schermo su telefono). Qui l\'assistente usa la guida della pagina corrente.',
            'Nella finestra, **Apri a pagina intera** porta alla pagina **Assistente AI** del menu laterale, con l\'elenco delle conversazioni a fianco della chat. In quella pagina l\'assistente usa la guida generale.',
            '**Nuova conversazione** inizia un discorso da zero; **Cronologia** mostra le conversazioni salvate.',
          ],
        },
        {
          type: 'warning',
          text: 'Il pulsante e la pagina compaiono solo se il tuo ruolo ha il permesso di usare l\'assistente e se l\'amministratore li ha attivati per la tua installazione: la finestra di chat e la pagina si attivano separatamente e di partenza sono spente. Se la pagina non è attiva, la voce di menu non compare e nella finestra manca **Apri a pagina intera**. Se l\'assistente non è ancora stato configurato dall\'amministratore, la chat mostra **Assistente non configurato** e non permette di scrivere.',
        },
      ],
    },
    {
      id: 'sending',
      title: 'Scrivere un messaggio',
      blocks: [
        {
          type: 'steps',
          items: [
            'Scrivi nel campo in basso. **Invio** manda il messaggio, **Maiusc+Invio** va a capo.',
            'La risposta compare mentre viene scritta. Con **Interrompi** puoi fermarla.',
            'Se l\'invio non riesce, il testo, i file e le menzioni restano nel campo: puoi riprovare senza riscrivere nulla.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Nelle risposte l\'assistente può usare grassetto, corsivo, elenchi, citazioni e codice. Il testo viene mostrato così com\'è, senza eseguire nulla.',
        },
      ],
    },
    {
      id: 'mentions',
      title: 'Menzionare un record con @',
      blocks: [
        {
          type: 'steps',
          items: [
            'Digita **@** nel campo (oppure usa il pulsante **@**): compare l\'elenco dei moduli.',
            'Scegli il modulo con le frecce e **Invio** (o con il clic). **Esc** chiude.',
            'Scrivi almeno 2 caratteri per cercare il record e sceglilo: appare come etichetta sopra il campo, rimovibile con la **x**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Moduli menzionabili: Aziende, Sedi aziendali, Referenti, Anagrafiche, Lead, Opportunità, Campagne, Preventivi, Commesse, Progetti, Task, Prodotti, Fatture, Fatture passive, Ordini di acquisto, Richieste di acquisto, Contratti, Utenti. Nell\'elenco compaiono solo quelli che il tuo ruolo può già vedere.',
        },
        {
          type: 'note',
          text: 'I dati del record vengono inviati all\'assistente solo nel messaggio in cui lo menzioni. Nei messaggi successivi della stessa conversazione resta soltanto il riferimento: se serve di nuovo il contenuto, menzionalo ancora. Puoi menzionare solo record che puoi aprire; per un record che non puoi vedere l\'invio viene rifiutato.',
        },
      ],
    },
    {
      id: 'attachments',
      title: 'Allegare file e immagini',
      blocks: [
        {
          type: 'paragraph',
          text: 'Con la graffetta puoi allegare PDF e immagini (PNG, JPG, WEBP). I limiti (numero di file e dimensione massima) vengono mostrati se li superi, prima dell\'invio. Anche i file, come le menzioni, vengono inviati all\'assistente solo nel messaggio in cui li aggiungi. I tipi accettati dipendono dal modello configurato: con alcuni modelli, ad esempio quelli installati in locale, i PDF non sono accettati oppure la graffetta non compare affatto.',
        },
        {
          type: 'tip',
          text: 'Gli allegati dei tuoi messaggi restano scaricabili dalla conversazione cliccando sul loro nome.',
        },
      ],
    },
    {
      id: 'privacy',
      title: 'Cosa viene inviato all\'esterno',
      blocks: [
        {
          type: 'warning',
          text: 'L\'assistente usa il modello di intelligenza artificiale scelto dall\'amministratore: un servizio esterno a QNet (OpenAI, Anthropic) oppure un modello installato sui server dell\'azienda. Con un servizio esterno, a ogni messaggio vengono inviati fuori dall\'azienda: il testo che scrivi, la guida della pagina, gli ultimi messaggi della conversazione, i file allegati e i dati dei record menzionati (inclusi dati personali di utenti e referenti, se li menzioni). Se non sai quale modello è in uso, non menzionare né allegare ciò che non può uscire dall\'azienda.',
        },
      ],
    },
    {
      id: 'history',
      title: 'Cronologia ed eliminazione',
      blocks: [
        {
          type: 'list',
          items: [
            'Le conversazioni sono salvate per ogni utente: nessun altro può leggere le tue.',
            'Una conversazione ferma da oltre **30 giorni** viene eliminata automaticamente, con i suoi file.',
            'Puoi eliminarla prima con l\'icona cestino accanto al suo titolo: dopo la conferma, messaggi e allegati vengono cancellati definitivamente.',
          ],
        },
      ],
    },
    {
      id: 'accuracy',
      title: 'Le risposte possono essere sbagliate',
      blocks: [
        {
          type: 'warning',
          text: 'L\'assistente può sbagliare o inventare dettagli. Verifica sempre le informazioni importanti (importi, date, scadenze, condizioni) sul record originale prima di agire o di comunicarle a terzi.',
        },
      ],
    },
  ],
}

export default guide
