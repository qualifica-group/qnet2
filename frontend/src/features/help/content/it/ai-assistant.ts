import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ai-assistant',
  title: 'Assistente AI',
  summary:
    'Un assistente a cui fare domande su QNet: conosce la guida della pagina in cui sei, può leggere i record che menzioni con @.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è e a cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'L\'assistente AI risponde in una chat: dopo l\'invio la richiesta entra in coda, poi l\'assistente lavora e la risposta compare man mano che la prepara. Puoi chiedergli come si usa una pagina, di riassumere un record, o di preparare una bozza di email.',
        },
        {
          type: 'note',
          text: 'L\'assistente vede ciò che gli scrivi, il tipo e l\'identificativo dei record che menzioni con @ e la guida in-app della pagina in cui ti trovi. In più può leggere i dati di QNet con i tuoi stessi permessi (per esempio «mostrami le mie commesse aperte»): non vede mai ciò che tu non potresti vedere.',
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
            'Apri la barra **Chat** in basso a destra: l\'**Assistente AI** è la prima conversazione dell\'elenco, sopra i canali dei progetti. Cliccandola si apre la sua finestra sopra la pagina in cui stai lavorando (a tutto schermo su telefono). Qui l\'assistente usa la guida della pagina corrente.',
            'Con **Riduci** la finestra diventa una barra bassa: la risposta in corso e quello che stavi scrivendo restano. Con la **X** la chiudi.',
            'Nella finestra, **Apri a pagina intera** porta alla pagina **Assistente AI** del menu laterale, con l\'elenco delle conversazioni a fianco della chat. In quella pagina l\'assistente usa la guida generale.',
            '**Nuova conversazione** inizia un discorso da zero; **Cronologia** mostra le conversazioni salvate.',
          ],
        },
        {
          type: 'warning',
          text: 'La conversazione nella barra Chat e la pagina compaiono solo se il tuo ruolo ha il permesso di usare l\'assistente e se l\'amministratore li ha attivati per la tua installazione: la finestra di chat e la pagina si attivano separatamente e di partenza sono spente. Se la pagina non è attiva, la voce di menu non compare e nella finestra manca **Apri a pagina intera**. Se l\'assistente non è ancora stato configurato dall\'amministratore, la chat mostra **Assistente non configurato** e non permette di scrivere.',
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
            'Sotto il tuo messaggio compare lo stato: **In coda** finché non parte, poi i passi che l\'assistente sta facendo (per esempio «Leggo le commesse») e il testo della risposta man mano che si forma. Mentre lavora non puoi inviare un altro messaggio, ma puoi già scrivere il prossimo: resta nel campo.',
            'Se l\'invio viene rifiutato, il testo e le menzioni restano nel campo: puoi riprovare senza riscrivere nulla. Se invece è l\'assistente a fermarsi (errore, troppi passaggi, tempo scaduto, limite di spesa), il tuo messaggio resta salvato e sotto compare il motivo.',
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
          text: 'All\'assistente arrivano solo il tipo di record e il suo identificativo, non i suoi dati: il contenuto lo legge l\'assistente stesso, con i tuoi permessi e il mascheramento dei dati riservati. Nella chat la menzione resta come l\'hai scritta. Puoi menzionare solo record che puoi aprire; per un record che non puoi vedere l\'invio viene rifiutato.',
        },
      ],
    },
    {
      id: 'attachments',
      title: 'Allegare file e immagini',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli allegati compaiono solo se il modello configurato dall\'amministratore li accetta. Oggi il modello è di solo testo: nel campo non c\'è la graffetta e il server rifiuta nuovi allegati. Se l\'amministratore passerà a un modello che li accetta, comparirà la graffetta per allegare PDF e immagini (PNG, JPG, WEBP), con i limiti di numero e dimensione mostrati prima dell\'invio.',
        },
        {
          type: 'tip',
          text: 'Gli allegati già inviati restano scaricabili dalla conversazione cliccando sul loro nome, anche se il modello in uso è poi diventato di solo testo.',
        },
      ],
    },
    {
      id: 'confirm-changes',
      title: 'Chiedere una modifica e confermarla',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se chiedi una modifica a un record, l\'assistente non la fa subito: mostra nella conversazione una scheda con il record, il valore di ogni campo **prima e dopo** e gli effetti della modifica. La modifica avviene solo quando premi **Conferma**; con **Annulla** non viene scritto nulla.',
        },
        {
          type: 'list',
          items: [
            'La scheda mostra solo ciò che il sistema ha preparato, non quello che l\'assistente scrive nel testo: controlla sempre i valori nella scheda.',
            'La scheda è valida per **10 minuti**: il tempo rimasto è sempre visibile e, scaduto, non si può più confermare. In quel caso non è stato scritto nulla e puoi ripetere la richiesta.',
            'Se alla scheda mancano dati obbligatori, **Conferma** resta disattivato e una riga lo spiega: puoi solo annullare e ripetere la richiesta con i dati mancanti.',
            'Se chiedi più modifiche, le schede arrivano una alla volta: confermane o annullane una per continuare con la successiva.',
            'Se il record è cambiato dopo la scheda, la modifica non viene scritta: l\'assistente te lo dice e puoi ripetere la richiesta.',
            'Se dopo **Conferma** l\'esito è incerto (per esempio la risposta non è arrivata), QNet non ripete la scrittura da solo: l\'assistente rilegge il record per verificare se la modifica è stata applicata e ti dice l\'esito nella conversazione.',
          ],
        },
        {
          type: 'warning',
          text: 'Scrivere «sì» nella chat non conferma nulla: serve il pulsante **Conferma** della scheda.',
        },
      ],
    },
    {
      id: 'privacy',
      title: 'Cosa viene inviato all\'esterno',
      blocks: [
        {
          type: 'warning',
          text: 'L\'assistente usa il modello di intelligenza artificiale scelto dall\'amministratore: un servizio esterno a QNet (OpenRouter, che inoltra la richiesta al fornitore del modello scelto). A ogni messaggio vengono inviati fuori dall\'azienda: il testo che scrivi, la guida della pagina, gli ultimi messaggi della conversazione e il tipo e l\'identificativo dei record menzionati; con un modello che accetta allegati, anche i file allegati. Anche i dati di QNet che l\'assistente legge per rispondere (per esempio le commesse aperte) fanno parte della conversazione e vengono inviati allo stesso modo. Non scrivere nella chat ciò che non può uscire dall\'azienda.',
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
      id: 'long-conversations',
      title: 'Conversazioni lunghe',
      blocks: [
        {
          type: 'list',
          items: [
            'Quando una conversazione si allunga, l\'assistente **riassume la parte precedente** e tiene per intero gli ultimi messaggi. Non devi fare nulla.',
            'I dati che l\'assistente ha letto prima **non vengono conservati**: se gli serve di nuovo un valore lo rilegge, così la risposta riflette quello che QNet mostra adesso.',
            'Dopo alcuni riassunti la chat mostra un avviso con **Apri una nuova conversazione**. Una conversazione nuova riparte pulita e risponde in modo più preciso.',
            'Nulla passa da una conversazione all\'altra: l\'assistente non ricorda quanto hai detto nelle altre. Puoi aprirne una nuova quando vuoi con **Nuova conversazione**.',
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
