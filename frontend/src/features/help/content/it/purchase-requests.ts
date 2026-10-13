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
          text: 'Il modulo si trova in **Acquisti › RDA**. Ogni **RDA** (richiesta di acquisto) ha una testata, una o più **righe** da acquistare, un piede con note e condizioni e dei **documenti**. Il **Responsabile di funzione** assegnato approva o rifiuta le singole righe; chi ha il permesso di evasione le porta a **Ordinato**, **Ricevuto** o **Stand by**.',
        },
        {
          type: 'paragraph',
          text: 'Dall’elenco puoi filtrare, ordinare ed esportare. Le **schede per stato** in alto mostrano solo le RDA che hanno almeno una riga in quello stato. Espandi una riga con la freccia per vederne le righe e cambiarne lo stato senza aprire la RDA. Vedi solo le RDA di cui sei richiedente, responsabile o autore, salvo che tu abbia il permesso di vedere tutto.',
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
            'Premi **Nuova RDA** e compila la testata: **Oggetto**, **Data richiesta**, **Priorità**, **Richiedente** e i campi organizzativi (**Società aziendale**, **Società / Sede**, **Sede operativa**, **Funzione aziendale**).',
            'Scegliendo la **Funzione aziendale** il **Responsabile di funzione** viene proposto in automatico: puoi cambiarlo con qualsiasi utente attivo. **Società / Sede** mostra solo le sedi della società scelta.',
            'Facoltativi: **Cliente** e **Commessa**. Il **Fornitore** non è più nella testata ma sulle singole righe. Il pulsante **+** accanto a un campo crea al volo un nuovo fornitore, cliente o prodotto.',
            'Aggiungi le righe (almeno una) e, se serve, i documenti, poi premi **Salva**.',
          ],
        },
        {
          type: 'note',
          text: '**Creata da** è in sola lettura. I campi che il tuo ruolo non può modificare appaiono disabilitati o nascosti.',
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
            ['**ODA**', 'In sola lettura: mostra il codice dell’ODA generata dalla riga, come link all’ordine, oppure un trattino se la riga non è collegata.'],
            ['**Documenti della riga**', 'Allegati della singola riga.'],
            ['**Cambia stato della riga**', 'Compare su una riga salvata solo se puoi cambiarne lo stato: il Responsabile di funzione assegnato approva o rifiuta le righe Da approvare, chi ha il permesso di evasione porta le righe Approvate a Ordinato o Stand by e quelle Ordinate a Ricevuto o Stand by. Si sceglie il nuovo stato e una motivazione facoltativa. Scegliendo **Ordinato** la finestra mostra una colonna **Fornitore**: le righe che ne hanno già uno lo mostrano, quelle senza hanno un elenco da compilare, e la conferma resta disabilitata finché ne manca uno.'],
            ['**Cestino**', 'Elimina la riga, se il tuo ruolo lo consente e se è Da approvare o Rifiutata.'],
          ],
        },
        {
          type: 'warning',
          text: 'Una riga si modifica **solo finché è Da approvare**: dopo l’approvazione o il rifiuto resta in sola lettura. Una RDA **chiusa** è interamente in sola lettura.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'I documenti si allegano alla RDA e alle singole righe. Finché la RDA non è salvata i file scelti restano **in coda** e vengono caricati subito dopo il salvataggio; se qualche caricamento fallisce, lo vedi in un avviso e puoi riprovare dalla RDA salvata.',
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
            '**Chiusura automatica**: la RDA si chiude da sola quando tutte le righe sono in uno stato finale (Ricevuto, Stand by o Rifiutato).',
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
