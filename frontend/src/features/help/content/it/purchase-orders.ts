import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-orders',
  title: 'ODA',
  summary: 'Ordini di acquisto ai fornitori: creazione, righe, stati, unificazione, stampa PDF e invio.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Acquisti › ODA** e mostra gli **ordini di acquisto** ai fornitori. L’ODA è **solo l’ordine**: non diventa una fattura. La fattura passiva è un documento separato e una stessa fattura può raccogliere più ODA.',
        },
        {
          type: 'paragraph',
          text: 'Le **schede per stato** in alto restringono l’elenco a un solo stato (**Tutti** li mostra tutti). La freccia a sinistra di ogni riga ne apre le **righe**. Puoi filtrare, ordinare ed esportare; in fondo trovi i totali di imponibile, IVA e totale del filtro corrente.',
        },
        {
          type: 'table',
          headers: ['Icona', 'Azione'],
          rows: [
            ['**Visualizza** / **Modifica**', 'Apre la pagina dell’ordine (in sola lettura se non è modificabile).'],
            ['**Scarica PDF**', 'Genera il PDF dell’ordine.'],
            ['**Elimina**', 'Elimina l’ordine dopo conferma, se è consentito.'],
            ['**Attività**', 'Mostra lo storico delle modifiche.'],
          ],
        },
      ],
    },
    {
      id: 'create',
      title: 'Creare una ODA',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova ODA** e compila la **Testata**: **Fornitore**, **Società**, **Società/Sede**, **Data ordine** e **Descrizione** sono obbligatori; **Sede operativa**, **Metodo di pagamento**, **Fruitore interno**, **Cliente**, **Commessa**, **Contratto/Offerta**, **Opportunità**, **Tag EA** con **Specifica** e **Note generali** sono facoltativi. **Società/Sede** mostra solo le sedi della società scelta.',
            'Aggiungi almeno una riga (vedi **Le righe**) e, se serve, i **Documenti**: restano in coda e si caricano subito dopo il salvataggio.',
            'Facoltativo: nel campo **Copie** indica da 1 a 12 (vedi sotto), poi premi **Salva**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Se scegli un fornitore **non qualificato** si apre una finestra: **Abbandona** svuota il campo, **Forza il proseguo** mantiene il fornitore e lascia un avviso rosso sotto il campo. È solo un avviso, non un blocco. Il pulsante **+** accanto a fornitore, cliente e prodotto crea al volo una nuova anagrafica.',
        },
        {
          type: 'paragraph',
          text: '**Copie mensili**: con **Copie** maggiore di 1 vengono create altrettante ODA indipendenti, ciascuna con il proprio numero. La **Data ordine** avanza di un mese per ogni copia (31 gennaio, 28 febbraio, 31 marzo…). I documenti in coda vengono caricati solo sulla prima. Il campo esiste solo in creazione.',
        },
        {
          type: 'note',
          text: 'I campi che il tuo ruolo non può modificare appaiono disabilitati o nascosti.',
        },
      ],
    },
    {
      id: 'lines',
      title: 'Le righe',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni riga può avere un **Prodotto** del catalogo oppure solo una **Descrizione** libera (obbligatoria). Scegliendo un prodotto il form propone **descrizione**, **U.m.**, **prezzo** (il costo d’acquisto del prodotto) e **Aliquota IVA**: restano modificabili. Ogni riga può avere una **Commessa** propria, proposta da quella di testata.',
        },
        {
          type: 'table',
          headers: ['Elemento', 'Significato'],
          rows: [
            ['**Tipo costo**', '**Obbligatorio** su ogni riga. I tipi si gestiscono in Acquisti › Tipi di costo (vedi la sua guida).'],
            ['**Imponibile / IVA / Totale**', 'Calcolati dal server (quantità × prezzo, poi IVA) e in sola lettura; l’anteprima nel form si allinea al salvataggio. I totali della testata sono la somma delle righe.'],
            ['**Scorpora l’IVA dal prezzo**', 'Se conosci solo il prezzo IVA inclusa, inseriscilo e premi l’icona: il prezzo torna all’imponibile con l’aliquota della riga (122,00 al 22% diventa 100,00). Serve un’aliquota maggiore di zero.'],
            ['**Badge RDA #n**', 'Compare sulle righe generate da una richiesta di acquisto (vedi **Collegamento con le RDA**).'],
          ],
        },
        {
          type: 'note',
          text: 'Ogni ODA ha da 1 a 200 righe. Quantità maggiore di zero, prezzo non negativo.',
        },
      ],
    },
    {
      id: 'numbering',
      title: 'Numerazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Alla creazione ogni ODA riceve un numero progressivo per **società** e **anno della data ordine**, nel formato **n/anno** (per esempio 4/2026). Il numero viene assegnato una volta sola: non cambia se poi modifichi data o società.',
        },
      ],
    },
    {
      id: 'status',
      title: 'Stati e transizioni',
      blocks: [
        {
          type: 'paragraph',
          text: 'Lo stato non si modifica dal form: usa **Cambia stato** nella pagina dell’ordine, che propone solo i passaggi consentiti dal flusso e dai tuoi permessi. L’ordine nasce in **Bozza**.',
        },
        {
          type: 'table',
          headers: ['Stato', 'Può passare a'],
          rows: [
            ['**Bozza**', 'In validazione, Approvato, Annullato'],
            ['**In validazione**', 'Bozza, Approvato, Annullato'],
            ['**Approvato**', 'In validazione, Inviato al fornitore, Annullato'],
            ['**Inviato al fornitore**', 'Parzialmente evaso, Evaso, Annullato'],
            ['**Parzialmente evaso**', 'Inviato al fornitore, Evaso'],
            ['**Evaso**', 'Parzialmente evaso'],
            ['**Annullato**', 'Bozza'],
          ],
        },
        {
          type: 'warning',
          text: '**Inviato al fornitore** si raggiunge solo da **Approvato**. Portare un ordine ad **Approvato** richiede il permesso dedicato di approvazione. Testata, righe, eliminazione e unificazione sono possibili **solo in Bozza o In validazione**: negli altri stati l’ordine è in sola lettura.',
        },
        {
          type: 'note',
          text: 'Inviare l’email al fornitore non cambia lo stato da solo: il cambio si fa a mano.',
        },
      ],
    },
    {
      id: 'merge',
      title: 'Unifica ODA',
      blocks: [
        {
          type: 'steps',
          items: [
            '**Salva** prima le modifiche dell’ordine: l’unificazione ha effetto immediato e, con modifiche non salvate, il pulsante ti avvisa e non si apre.',
            'Apri la pagina dell’ordine di destinazione (in Bozza o In validazione) e premi **Unifica**.',
            'La finestra elenca le altre ODA dello **stesso fornitore e della stessa società** in Bozza o In validazione, con le loro righe: seleziona singole righe o un’intera ODA.',
            'Conferma con **Unifica N righe**: le righe vengono **spostate subito** in coda all’ordine corrente e i totali di entrambi gli ordini sono ricalcolati.',
          ],
        },
        {
          type: 'list',
          items: [
            'Le ODA che restano **senza righe** vengono **eliminate** (con i loro documenti); i settori e i documenti delle ODA sorgente non si spostano.',
            'Le righe collegate a una RDA mantengono il collegamento, che ora punta all’ordine di destinazione.',
            '**Storico unificazioni** (compare quando l’ordine ne ha): elenca, dal più recente, gli ordini unificati con le righe spostate, i totali e chi ha eseguito l’operazione.',
          ],
        },
      ],
    },
    {
      id: 'rda-link',
      title: 'Collegamento con le RDA',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le ODA nascono anche dalla pagina **Gestione righe RDA** con l’azione **Genera ODA** (vedi la sua guida): le righe RDA approvate vengono raggruppate per fornitore e per ognuno nasce una ODA **in Bozza**, con testata presa dalla RDA. Le righe RDA passano a **Ordinato** e nella loro colonna **ODA** compare il codice come link. I documenti della RDA non vengono copiati: restano consultabili dalla RDA.',
        },
        {
          type: 'paragraph',
          text: 'Nell’ODA la riga generata mostra il badge **RDA #n**; descrizione, quantità e prezzo restano modificabili.',
        },
        {
          type: 'warning',
          text: '**Eliminando una riga collegata** (o l’intera ODA) la riga RDA torna **Approvata** e potrà generare un nuovo ordine. **Annullare** l’ODA invece non scollega le righe.',
        },
      ],
    },
    {
      id: 'delete',
      title: 'Eliminazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un’ODA si elimina solo in **Bozza** o **In validazione**, con conferma. Non si può eliminare se è **collegata a una fattura o proforma passiva** (compare in **Contabilità › Passiva › Scadenze**, colonna **ODA collegati**): in quel caso l’operazione è rifiutata con un messaggio. Le righe RDA collegate tornano Approvate.',
        },
      ],
    },
    {
      id: 'pdf-email',
      title: 'Stampa PDF e invio al fornitore',
      blocks: [
        {
          type: 'paragraph',
          text: '**Stampa PDF** (nella pagina dell’ordine) e **Scarica PDF** (nell’elenco) generano il documento con il nome **ODA-numero-anno**. Serve un **Layout** del modulo **ODA** attivo (o predefinito) in **Configurazione › Layout**; senza, la stampa è rifiutata.',
        },
        {
          type: 'paragraph',
          text: '**Invia al fornitore** apre la finestra email con il **PDF allegato** e come destinatario l’email del fornitore: modifica oggetto e testo, poi invia. L’azione richiede il permesso di invio.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'table',
          headers: ['Permesso', 'Consente'],
          rows: [
            ['**Visualizzare**', 'Vedere tutte le ODA e scaricare il PDF.'],
            ['**Creare**, **Modificare**, **Eliminare**', 'Gestire testata e righe; creare anche da Gestione righe RDA.'],
            ['**Cambiare stato**', 'Tutti i passaggi di stato tranne l’approvazione.'],
            ['**Approvare**', 'Portare l’ordine allo stato Approvato.'],
            ['**Unificare**', 'Usare Unifica ODA.'],
            ['**Inviare al fornitore**', 'Inviare l’email con il PDF.'],
            ['**Esportare**, **Visualizzare attività**', 'Esportare l’elenco e vedere lo storico delle modifiche.'],
          ],
        },
        {
          type: 'tip',
          text: 'Chi ha il permesso di visualizzazione vede tutte le ODA, non solo le proprie.',
        },
      ],
    },
  ],
}

export default guide
