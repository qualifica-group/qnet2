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
          text: 'La pagina è sotto **Magazzino e Dotazioni › Acquisti › ODA** e mostra gli **ordini di acquisto** ai fornitori. L’ODA è **solo l’ordine**: non diventa una fattura. La fattura passiva è un documento separato e una stessa fattura può raccogliere più ODA. Gli imponibili delle righe collegate a una **commessa** alimentano il **consuntivo** della commessa finché l’ODA è impegnato e non ancora fatturato.',
        },
        {
          type: 'paragraph',
          text: 'Le **schede per stato** in alto restringono l’elenco a un solo stato (**Tutti** li mostra tutti). La freccia a sinistra di ogni riga ne apre le **righe**. Puoi filtrare, ordinare ed esportare; in fondo trovi i totali di imponibile, IVA e totale del filtro corrente. Sugli ordini in **Bozza** o **In validazione** puoi **modificare direttamente nelle celle** Data ordine, Descrizione, Fornitore (solo anagrafiche fornitore), Società/Sede (solo sedi della società dell’ordine), Sede operativa, Metodo di pagamento, Cliente, Commessa, Contratto/Offerta, Opportunità, Tag EA e Fruitore interno: fai doppio clic sulla cella, scegli il valore e conferma. Dopo l’approvazione, finché l’ordine non è annullato, restano modificabili solo Sede operativa, Metodo di pagamento, Cliente, Commessa, Contratto/Offerta, Opportunità, Tag EA e Fruitore interno. Valgono le stesse regole della scheda. La **Società** non si cambia: la numerazione è per società. L’ordine si apre con l’icona **Visualizza**.',
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
            'Facoltativo: nel campo **Copie** indica da 1 a 12 (vedi sotto), poi premi **Salva** (in alto o in fondo alla scheda). Se esci senza salvare ti viene chiesta conferma.',
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
      id: 'edit',
      title: 'Modificare una ODA',
      blocks: [
        {
          type: 'paragraph',
          text: 'Aprendo un’ODA vedi la sua **scheda**: in alto stato, azioni e totali; sotto **Testata**, **Organizzazione e collegamenti**, **Tag EA e note**, **Righe** e **Documenti**. Non c’è un pulsante Salva generale: ogni campo si modifica da solo. In **Bozza** e **In validazione** si modifica tutto; dopo l’approvazione, finché l’ordine non è **Annullato**, restano modificabili solo i campi di servizio: **Note generali**, **Tag EA** e **Specifica**, **Metodo di pagamento**, **Sede operativa**, **Fruitore interno**, **Cliente**, **Commessa**, **Contratto/Offerta** e **Opportunità**. Fornitore, data, descrizione, sede e righe restano come sul documento inviato.',
        },
        {
          type: 'steps',
          items: [
            'Passa sul campo e premi la **matita** (o fai clic sul valore): si apre il campo da modificare.',
            'Cambia il valore e premi **Salva**: viene salvato solo quel campo. **Annulla**, il tasto Esc o un clic fuori dal campo lo lasciano com’era.',
            'Le righe si mostrano in sola lettura: la **matita** della riga la apre, **Salva** salva solo quella riga, **Annulla** la riporta com’era. **Aggiungi riga** apre una riga nuova allo stesso modo; il **cestino** chiede conferma ed elimina subito (l’ultima riga non si elimina).',
          ],
        },
        {
          type: 'note',
          text: 'La **Società** è in sola lettura dopo la creazione. Si apre un campo alla volta: aprendone un altro le modifiche non salvate del primo vengono scartate. Sulle righe e sui campi bloccati, su un ordine annullato o senza permesso di modifica, le matite non compaiono.',
        },
      ],
    },
    {
      id: 'lines',
      title: 'Le righe',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni riga può avere un **Prodotto** del catalogo oppure solo una **Descrizione** libera (obbligatoria). Scegliendo un prodotto il form propone **descrizione**, **U.m.**, **prezzo** (il costo d’acquisto del prodotto) e **Aliquota IVA**: restano modificabili. Se il prodotto ha un **tipo di costo** predefinito, viene proposto solo se la riga non ne ha già uno. Ogni riga può avere una **Commessa** propria, proposta da quella di testata.',
        },
        {
          type: 'table',
          headers: ['Elemento', 'Significato'],
          rows: [
            ['**Tipo costo**', '**Obbligatorio** su ogni riga. Nel menu compare con il colore del tipo. I tipi si gestiscono in Configurazione › Acquisti › Tipi di costo (vedi la sua guida).'],
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
          text: 'Alla creazione ogni ODA riceve un numero progressivo per **società** e **anno della data ordine**, nel formato **n/anno** (per esempio 4/2026). Il numero viene assegnato una volta sola: non cambia se poi modifichi la data. La **società** si sceglie alla creazione e **non si può cambiare** dopo.',
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
          type: 'note',
          text: 'I nomi degli stati in questa guida sono quelli **predefiniti**: da **Configurazione › Acquisti › Stati ODA** si possono rinominare, ricolorare, cambiare di icona e riordinare. Schede, badge e finestra **Cambia stato** seguono subito il nome, il colore, l’icona e l’ordine configurati. I passaggi consentiti e le regole qui sotto non cambiano.',
        },
        {
          type: 'table',
          headers: ['Stato', 'Può passare a'],
          rows: [
            ['**Bozza**', 'In validazione, Approvato, Annullato'],
            ['**In validazione**', 'Bozza, Approvato, Annullato'],
            ['**Approvato**', 'In validazione, Inviato al fornitore, Annullato'],
            ['**Inviato al fornitore**', 'Annullato (senza ricezioni)'],
            ['**Parzialmente evaso**', 'Nessun passaggio manuale'],
            ['**Evaso**', 'Nessun passaggio manuale'],
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
        {
          type: 'paragraph',
          text: '**Parzialmente evaso** ed **Evaso** non si scelgono a mano: seguono le ricezioni. Quando tutte le righe sono ricevute o hanno il residuo chiuso l’ordine diventa **Evaso**; se solo alcune lo sono, **Parzialmente evaso**; eliminando tutte le ricezioni torna a **Inviato al fornitore**. Un ordine con ricezioni non può tornare in validazione o in bozza né essere annullato.',
        },
        {
          type: 'paragraph',
          text: 'Gli stati influiscono anche sulle righe RDA collegate (vedi **Collegamento con le RDA**): **Annullato** le libera, la ricezione delle righe le porta a **Ricevuta** (vedi **Evasione delle righe e ricezioni**). Tornare da **Annullato** a **Bozza** non cambia le RDA.',
        },
      ],
    },
    {
      id: 'receipts',
      title: 'Evasione delle righe e ricezioni',
      blocks: [
        {
          type: 'paragraph',
          text: 'Negli ordini che non sono in Bozza la pagina mostra la tabella **Evasione righe**, con per ogni riga le colonne **Ordinato**, **Ricevuto**, **Fatturato** e **Residuo** e un badge di stato (aperta, parziale, completa). Il **Ricevuto** di una riga è la somma delle sue quantità nelle ricezioni; il **Fatturato** somma le quantità presenti nelle fatture passive e nei proforma collegati.',
        },
        {
          type: 'steps',
          items: [
            'Da un ordine **Approvato**, **Inviato al fornitore** o **Parzialmente evaso** premi **Registra ricezione** (serve il permesso **Registra ricezioni**).',
            'Indica la **data di ricezione** (non può essere futura) e, se vuoi, **numero** e **data del DDT** e le **note**.',
            'Per ogni riga la **quantità** è precompilata con il **residuo**: modificala oppure premi **Ricevi tutto**. Le righe a 0 vengono ignorate e la quantità non può superare il residuo.',
            'Conferma: la ricezione viene registrata e lo stato dell’ordine si aggiorna da solo.',
          ],
        },
        {
          type: 'list',
          items: [
            'La sezione **Ricezioni** elenca le ricezioni dell’ordine, dalla più recente. Le ricezioni create da una fattura compaiono come “da fattura n. X” (un link alla fattura, se puoi vederla) e non si possono annullare finché la fattura esiste; le altre si possono **annullare**.',
            'La sezione **Fatture collegate** (negli ordini non in Bozza) elenca le fatture passive e i proforma collegati all’ordine come etichette con tipo, numero (se registrato) e data; l’etichetta apre il documento se hai il permesso di vedere le fatture passive, altrimenti resta un testo. Se non ce ne sono, la sezione lo dice.',
            '**Chiudi residuo** (azione di riga) si usa quando la merce mancante **non arriverà**: il **motivo è obbligatorio**, la riga conta come completa e il residuo non è più impegnato. Non è consentito se un proforma copre più di quanto ricevuto: riduci prima il proforma.',
            '**Riapri residuo** riporta la riga tra quelle aperte.',
            'Le righe RDA collegate diventano **Ricevute** quando la loro riga d’ordine è completa e tornano **Ordinate** se si riapre (a meno che la RDA sia già chiusa).',
          ],
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
            'Chiudi prima il campo o la riga che stai modificando (**Salva** o **Annulla**): l’unificazione ha effetto immediato e, con un campo aperto, il pulsante ti avvisa e non si apre.',
            'Apri la pagina dell’ordine di destinazione (in Bozza o In validazione) e premi **Unifica**.',
            'La finestra elenca le altre ODA dello **stesso fornitore e della stessa società** in Bozza o In validazione, con le loro righe: seleziona singole righe o un’intera ODA.',
            'Conferma con **Unifica N righe**: le righe vengono **spostate subito** in coda all’ordine corrente e i totali di entrambi gli ordini sono ricalcolati.',
          ],
        },
        {
          type: 'list',
          items: [
            'Oltre al permesso **Unificare** sulla destinazione servono il permesso di **modifica** sulle ODA d’origine e quello di **eliminazione** su quelle che restano senza righe: se manca, l’operazione è rifiutata e non si sposta nulla.',
            'Le ODA che restano **senza righe** vengono **eliminate** (con i loro documenti); i settori e i documenti delle ODA sorgente non si spostano.',
            'Un’ODA d’origine **collegata a una fattura o proforma passiva** non può essere svuotata dall’unificazione: l’operazione è rifiutata e non si sposta nulla.',
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
          text: '**Eliminando una riga collegata**, **eliminando l’ODA** o **annullandola**, le righe RDA collegate vengono liberate: una riga **Ordinata** torna **Approvata** e potrà generare un nuovo ordine; se la sua RDA è già **chiusa** passa invece a **Stand by**. Le righe Ricevute, Rifiutate o in Stand by mantengono il proprio stato e perdono solo il riferimento all’ODA.',
        },
        {
          type: 'paragraph',
          text: 'Un’ODA **annullata** perde il collegamento con le righe RDA anche se la riapri (torna in Bozza): le righe RDA non cambiano. Quando una riga dell’ODA è completa, la riga RDA **Ordinata** collegata diventa **Ricevuta** e la RDA si chiude da sola se tutte le sue righe sono concluse.',
        },
      ],
    },
    {
      id: 'delete',
      title: 'Eliminazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un’ODA si elimina solo in **Bozza** o **In validazione**, con conferma. Non si può eliminare se è **collegata a una fattura o proforma passiva** (compare in **Contabilità › Passiva › Scadenze passive**, colonna **ODA collegati**): in quel caso l’operazione è rifiutata con un messaggio. Le righe RDA collegate vengono liberate come descritto in **Collegamento con le RDA**.',
        },
      ],
    },
    {
      id: 'register-invoice',
      title: 'Registrare la fattura',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella pagina di un’ODA **Approvata**, **Inviata al fornitore**, **Parzialmente evasa** o **Evasa** compare il pulsante **Registra fattura** (serve il permesso di creazione delle fatture passive). Apre l’editor della fattura passiva con le **righe dell’ordine già precompilate** e l’ODA già collegata; da lì puoi aggiungere altri ODA dello stesso fornitore e società, modificare, aggiungere o togliere righe.',
        },
        {
          type: 'note',
          text: 'Il documento fattura le **quantità**: sia un **proforma** sia una **fattura** possono coprire la quantità ordinata non ancora fatturata (se il residuo è chiuso, il tetto è la quantità ricevuta). Salvare come **Fattura** registra in automatico una ricezione sull’ODA per le quantità fatturate non ancora ricevute (data del documento, nota “Ricezione da fattura n. X”), aggiornando lo stato dell’ODA e le righe RDA collegate; il proforma non riceve. Se l’ODA non è ricevibile (bozza, in validazione, annullato) la fattura è rifiutata. Vedi la guida **Fatture passive**.',
        },
      ],
    },
    {
      id: 'pdf-email',
      title: 'Stampa PDF e invio al fornitore',
      blocks: [
        {
          type: 'paragraph',
          text: '**Stampa PDF** (nella pagina dell’ordine) e **Scarica PDF** (nell’elenco) generano il documento con il nome **ODA-numero-anno**. Serve un **Layout** del modulo **ODA** attivo (o predefinito) in **Configurazione › Documenti › Layout**; senza, la stampa è rifiutata.',
        },
        {
          type: 'paragraph',
          text: '**Invia al fornitore** apre la finestra email con il **PDF allegato** e come destinatario l’email del fornitore: modifica oggetto e testo, poi invia. L’azione richiede il permesso di invio ed è disponibile **solo con l’ODA Approvata o Inviata al fornitore**: negli altri stati il pulsante non compare.',
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
            ['**Registra ricezioni**', 'Registrare e annullare ricezioni, chiudere e riaprire il residuo delle righe.'],
            ['**Registra fattura** (permesso Crea delle fatture passive)', 'Aprire l’editor della fattura passiva dall’ODA.'],
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
