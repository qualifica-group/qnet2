import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-requests',
  title: 'RDA - Richieste di acquisto',
  summary: 'Le richieste di acquisto: compilazione, approvazione delle righe, documenti e chiusura.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Magazzino e Dotazioni › Acquisti › RDA**. Ogni **RDA** (richiesta di acquisto) ha una testata, una o più **righe** da acquistare, un piede con note e condizioni e dei **documenti**. Il **Responsabile di funzione** assegnato approva o rifiuta le singole righe; chi ha il permesso di evasione le porta a **Ordinato**, **Ricevuto** o **Stand by**.',
        },
        {
          type: 'paragraph',
          text: 'Dall’elenco puoi filtrare, ordinare ed esportare, e **modificare direttamente nelle celle** Oggetto, Data richiesta, Priorità, Richiedente, Responsabile di funzione, Cliente, Commessa, Società / Sede (solo tra le sedi della società della RDA), Sede operativa e Funzione aziendale: fai doppio clic sulla cella, scegli il valore e conferma. Valgono le stesse regole della scheda; le RDA chiuse e i campi che il tuo ruolo non può modificare restano in sola lettura. La **Società aziendale** si cambia dalla scheda, insieme alla sede. Le **schede per stato** in alto mostrano solo le RDA che hanno almeno una riga in quello stato. Espandi una riga con la freccia per vederne le righe e cambiarne lo stato senza aprire la RDA. Vedi solo le RDA di cui sei richiedente, responsabile o autore, salvo che tu abbia il permesso di vedere tutto.',
        },
        {
          type: 'note',
          text: 'I nomi, i colori e le icone degli stati delle righe (Da approvare, Approvato, Ordinato, Ricevuto, Rifiutato, Stand by) sono quelli predefiniti e si possono personalizzare in **Configurazione › Acquisti › Stati righe RDA**: schede, badge e finestra di cambio stato seguono la configurazione. Il significato degli stati e i passaggi consentiti non cambiano.',
        },
      ],
    },
    {
      id: 'create',
      title: 'Creare una RDA',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova RDA** e compila la testata: **Oggetto**, **Data richiesta**, **Priorità** (scelta dall’elenco configurabile, precompilata con quella predefinita e mostrata come badge colorato), **Richiedente** e i campi organizzativi (**Società aziendale**, **Società / Sede**, **Sede operativa**, **Funzione aziendale**).',
            'Il **Responsabile di funzione** si sceglie tra i **diretti superiori del richiedente** (profilo aziendale in **Utenti**): con un solo superiore viene proposto in automatico, cambiando il richiedente l’elenco si aggiorna. Se il richiedente non ha superiori si usa il responsabile della **Funzione aziendale**; se manca anche quello un messaggio sotto il campo lo segnala e la RDA non si può salvare. Chi è il richiedente non può approvare la propria RDA. **Società / Sede** mostra solo le sedi della società scelta.',
            'Facoltativi: **Cliente** e **Commessa**. Il **Fornitore** non è più nella testata ma sulle singole righe. Il pulsante **+** accanto a un campo crea al volo un nuovo fornitore, cliente o prodotto.',
            'Aggiungi le righe (almeno una) e, se serve, i documenti, poi premi **Salva** (in alto o in fondo alla scheda). Se esci senza salvare ti viene chiesta conferma.',
          ],
        },
        {
          type: 'note',
          text: '**Creata da** è in sola lettura. I campi che il tuo ruolo non può modificare appaiono disabilitati o nascosti.',
        },
      ],
    },
    {
      id: 'edit',
      title: 'Modificare una RDA',
      blocks: [
        {
          type: 'paragraph',
          text: 'Aprendo una RDA salvata vedi la sua **scheda**: in alto stato, azioni e totali; sotto le sezioni **Richiesta**, **Organizzazione e riferimenti**, **Note e condizioni**, **Righe** e **Documenti**. Non c’è un pulsante Salva generale: ogni campo si modifica da solo.',
        },
        {
          type: 'steps',
          items: [
            'Passa sul campo e premi la **matita** (o fai clic sul valore): si apre il campo da modificare.',
            'Cambia il valore e premi **Salva**: viene salvato solo quel campo. **Annulla**, il tasto Esc o un clic fuori dal campo lo lasciano com’era.',
            '**Società aziendale** e **Società / Sede** si modificano insieme. Cambiando **Richiedente** o **Funzione aziendale** compare anche il **Responsabile di funzione**, che si riallinea come in creazione.',
          ],
        },
        {
          type: 'note',
          text: 'Si apre un campo alla volta: aprendone un altro, le modifiche non salvate del primo vengono scartate. Senza permesso di modifica, o su una RDA chiusa, le matite non compaiono.',
        },
      ],
    },
    {
      id: 'lines',
      title: 'Le righe',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni riga può avere un **Prodotto** del catalogo oppure solo una **Descrizione** libera. Scegliendo un prodotto il form propone descrizione, **U.m.**, **Prezzo** e **Aliquota IVA** del prodotto: restano modificabili. Ogni riga può avere un **Fornitore** (solo anagrafiche fornitore), così una stessa RDA può raccogliere righe di fornitori diversi. Sul desktop ogni riga è una sola fascia sotto un’unica intestazione; su schermi piccoli le righe diventano schede. Le azioni della riga sono **solo icone**: passa il mouse per leggerne il nome.',
        },
        {
          type: 'table',
          headers: ['Elemento', 'Significato'],
          rows: [
            ['**Imponibile / IVA / Totale**', 'Calcolati dal sistema (quantità × prezzo, poi IVA) e in sola lettura. Il totale della RDA è la somma delle righe.'],
            ['**Scorpora IVA**', 'Se conosci solo il prezzo finale IVA inclusa, inseriscilo e premi Scorpora IVA: il prezzo viene riportato all’imponibile con l’aliquota della riga (122,00 al 22% diventa 100,00). Serve un’aliquota maggiore di zero.'],
            ['**Fornitore**', 'Facoltativo quando crei la riga, ma **obbligatorio per portarla a Ordinato**: se manca, la finestra di cambio stato te lo chiede riga per riga. Si modifica finché la riga è Da approvare (da chi può modificare la RDA) e Approvata (da chi ha il permesso di evasione, anche con il resto della riga bloccato); da **Ordinato** in poi è bloccato. Scegliendo un prodotto che ha un fornitore, questo viene proposto.'],
            ['**Totale**', 'Passa il mouse (o seleziona con la tastiera) sul totale per vedere imponibile e IVA della riga.'],
            ['**Motivazione**', 'L’icona con il fumetto apre una finestrella con il testo della motivazione; è evidenziata quando la motivazione è scritta e in sola lettura sulle righe bloccate.'],
            ['**Stato**', 'Da approvare, Approvato, Ordinato, Ricevuto, Rifiutato o Stand by. Non si modifica come un campo: si usa l’icona **Cambia stato della riga**.'],
            ['**ODA**', 'In sola lettura: mostra il codice dell’ODA generata dalla riga, come link all’ordine, oppure un trattino se la riga non è collegata. Il collegamento viene tolto se l’ODA è eliminata o annullata, o se la riga è rimossa dall’ODA.'],
            ['**Documenti della riga**', 'Allegati della singola riga.'],
            ['**Cambia stato della riga**', 'Compare su una riga salvata solo se puoi cambiarne lo stato: il Responsabile di funzione assegnato approva o rifiuta le righe Da approvare, chi ha il permesso di evasione porta le righe Approvate a Ordinato o Stand by e quelle Ordinate a Ricevuto o Stand by. Si sceglie il nuovo stato e una motivazione, **obbligatoria** per **Rifiutato** e **Stand by** (facoltativa per gli altri stati). Scegliendo **Ordinato** la finestra mostra una colonna **Fornitore**: le righe che ne hanno già uno lo mostrano, quelle senza hanno un elenco da compilare, e la conferma resta disabilitata finché ne manca uno.'],
            ['**Cestino**', 'Elimina la riga dopo conferma, se il tuo ruolo lo consente e se è Da approvare o Rifiutata.'],
          ],
        },
        {
          type: 'warning',
          text: 'Nella scheda le righe si mostrano in sola lettura: la **matita** della riga la apre per la modifica, **Salva** salva solo quella riga e **Annulla** la riporta com’era. **Aggiungi riga** apre una riga nuova allo stesso modo; il **cestino** chiede conferma ed elimina subito. Una riga si modifica **solo finché è Da approvare** (di una riga Approvata si cambia solo il fornitore, se puoi): dopo l’approvazione o il rifiuto resta in sola lettura. Una RDA **chiusa** è interamente in sola lettura.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'I documenti si allegano alla RDA e alle singole righe. Sulla RDA salvata il caricamento e l’eliminazione sono immediati. Finché la RDA non è salvata i file scelti restano **in coda** e vengono caricati subito dopo il salvataggio; se qualche caricamento fallisce, lo vedi in un avviso e puoi riprovare dalla RDA salvata.',
        },
      ],
    },
    {
      id: 'notes',
      title: 'Note',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella colonna di destra del dettaglio c’è la scheda **Note**, la prima: qui discuti la richiesta con i colleghi, menzionandoli con **@** come nelle Opportunità. Dalla tabella apri le stesse note con l’azione **Note** della riga, che mostra quante ce ne sono. Le note scritte nel vecchio QNet arrivano con la migrazione.',
        },
        {
          type: 'tip',
          text: 'La scheda e l’azione Note compaiono a chi può vedere la RDA (richiedente, responsabile di funzione, autore o chi vede tutte le RDA); per scrivere una nota serve anche il permesso di creare note. Le note sono diverse dal campo **Note** dell’intestazione, che resta un testo del record.',
        },
      ],
    },
    {
      id: 'close',
      title: 'Chiusura, invio ed eliminazione',
      blocks: [
        {
          type: 'list',
          items: [
            '**Chiusura automatica**: la RDA si chiude da sola quando non restano righe aperte, cioè tutte sono in uno stato finale (Ricevuto, Stand by o Rifiutato), anche dopo aver eliminato delle righe.',
            '**Chiudi RDA**: chiusura a mano. Se restano righe non finali la chiusura è **forzata** e la **motivazione è obbligatoria**. Una RDA chiusa non si riapre.',
            '**Invia al responsabile**: rinvia al Responsabile di funzione la notifica (app ed email) che riceve alla creazione.',
            '**Elimina**: rimuove la RDA con righe, storico e documenti dopo conferma; non è possibile se una riga è già Ordinata o Ricevuta.',
          ],
        },
        {
          type: 'tip',
          text: 'Le righe si approvano ed evadono una alla volta con **Cambia stato della riga** nella RDA, oppure in blocco dalla pagina **Gestione righe RDA** (vedi la sua guida). La RDA va prima salvata; il Responsabile di funzione deve essere l’utente collegato.',
        },
      ],
    },
  ],
}

export default guide
