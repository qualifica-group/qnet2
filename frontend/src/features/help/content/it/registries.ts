import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'registries',
  title: 'Anagrafiche',
  summary: 'Le Anagrafiche raccolgono le schede di clienti e fornitori della tua organizzazione.',
  sections: [
    {
      id: 'overview',
      title: 'Il gruppo Anagrafiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il gruppo Anagrafiche contiene le schede di clienti e fornitori, delle persone di contatto (Referenti) e della tua organizzazione (Società aziendali, Società sedi, Sedi operative).',
        },
        {
          type: 'list',
          items: [
            "Un'anagrafica può avere più Referenti, oltre a un Commerciale e un Segnalatore.",
            'Supervisore e Gestori account sono utenti di QNet, non referenti.',
            'Ogni Società sede appartiene a una Società aziendale; le Sedi operative sono indipendenti e si scelgono, per esempio, nelle opportunità.',
          ],
        },
      ],
    },
    {
      id: 'searching-a-registry',
      title: "Cercare un'anagrafica",
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri Anagrafiche › Anagrafiche.',
            "Scrivi nel campo Cerca… in alto nella tabella: l'elenco si aggiorna mentre scrivi.",
            'Usa le schede sopra la tabella per separare le anagrafiche: Tutte, Persone fisiche o Aziende. La colonna Tipo mostra a quale gruppo appartiene ogni riga.',
            'Per restringere la ricerca usa i filtri sulle colonne, per esempio Fonte, Fornitore o Stato convenzione.',
            "Le colonne Commerciale, Supervisore, Segnalatore e Operatori mostrano il team dell'anagrafica: Operatori riporta i Gestori account nel loro ordine. Puoi filtrarle per nome e ordinarle, tranne Operatori che si filtra soltanto.",
            'Per vedere la scheda, apri il menu Azioni della riga e scegli Visualizza.',
          ],
        },
        {
          type: 'tip',
          text: 'Se usi spesso gli stessi filtri, salvali con Salva vista in Filtri salvati. La vista può essere Privata o Condivisa; per ripartire da zero usa Azzera filtri.',
        },
      ],
    },
    {
      id: 'registry-record',
      title: 'La scheda anagrafica',
      blocks: [
        {
          type: 'paragraph',
          text: "Per creare una scheda premi **Nuova anagrafica**: il modulo ha lo stesso aspetto del dettaglio, con le stesse sezioni e **righe chiuse**. Fai clic sulla riga (o sulla matita) per aprirla, poi **Fatto** per tenere il valore o **Ripristina** per riportarlo com'era. Nei **Dati anagrafici** scegli prima il Tipo, Persona fisica o Azienda (i campi cambiano di conseguenza): con Fatto il nome compare in testa alla scheda. Contatti e Indirizzi sono nella colonna di destra, con i campi già pronti da compilare: Email, Telefono (obbligatorio), PEC e Fax, più **Aggiungi contatto** per altri recapiti, e un indirizzo con il suo Tipo sede, più **Aggiungi indirizzo** per le altre sedi. **Salva** controlla tutto e crea l'anagrafica; se esci senza salvare ti viene chiesta conferma.",
        },
        {
          type: 'table',
          headers: ['Sezione', 'Cosa contiene'],
          rows: [
            ['Dati anagrafici', 'Ragione sociale (aziende) oppure Nome e Cognome (persone fisiche), codice fiscale, partita IVA e, per le persone fisiche, data e luogo di nascita.'],
            ['Relazioni', 'Fonte, Settori merceologici, Commerciale e Segnalatore.'],
            ['Team', 'Supervisore e Gestori account, in ordine di importanza dal primo in alto; riordinali con Sposta su e Sposta giù.'],
            ['Dati commerciali', 'Gruppo IVA, Fornitore, Fornitore qualificato (solo per i fornitori), Stato convenzione (In trattativa, Respinta o Concordata), Classe dimensionale, Numero dipendenti e Note convenzione.'],
            ['Note generali', "Il riquadro giallo in cima alla colonna di destra, lo stesso di Gestione richieste: un testo libero (fino a 5000 caratteri) con ciò che chi apre l'anagrafica deve leggere per primo."],
            ['Contatti', 'Email, Telefono (obbligatorio), PEC e Fax; con Aggiungi contatto ne inserisci altri e indichi il Contatto principale.'],
            ['Indirizzi', 'Uno o più indirizzi, ciascuno con un Tipo sede: Sede legale, Consegna, Fatturazione o Sede operativa.'],
            ['Referenti', 'Le persone di contatto del cliente, a tutta larghezza sotto le altre sezioni.'],
            ['Altri campi', "I campi personalizzati delle anagrafiche, tra cui Tag: scegli uno o più tag dall'elenco gestito nel modulo Tag. Compaiono anche nel dettaglio, raggruppati come nel modulo."],
          ],
        },
        {
          type: 'warning',
          text: 'Solo le anagrafiche segnate come Fornitore compaiono tra i fornitori selezionabili nella scheda prodotto.',
        },
      ],
    },
    {
      id: 'editing-a-registry',
      title: "Modificare un'anagrafica",
      blocks: [
        {
          type: 'paragraph',
          text: "Non esiste una pagina di modifica separata: l'anagrafica si modifica **direttamente dal suo dettaglio**, un campo alla volta, campi personalizzati compresi.",
        },
        {
          type: 'steps',
          items: [
            "Apri l'anagrafica dall'elenco.",
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            "Premi **Salva** (o Invio nei campi di testo e numero) per salvare solo quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) per chiuderlo lasciandolo com'era, senza salvare.",
          ],
        },
        {
          type: 'note',
          text: "I **Dati anagrafici** (tipo, nome o ragione sociale, codice fiscale, partita IVA…) si modificano insieme: la matita apre l'intera scheda e Salva aggiorna anche il nome dell'anagrafica. **Contatti** e **Indirizzi**, nella colonna di destra, si aggiungono, modificano ed eliminano lì stesso e si salvano subito.",
        },
        {
          type: 'note',
          text: 'Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura.',
        },
        {
          type: 'note',
          text: "Le **Note generali** si scrivono dentro il loro riquadro in cima alla colonna di destra: fai clic sul testo (o sulla matita), scrivi e premi **Salva**. Se l'anagrafica non ha note e non puoi modificarle, il riquadro non compare.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Modifica rapida dall'elenco",
      blocks: [
        {
          type: 'paragraph',
          text: "Un clic su una cella modificabile (Fonte, Fornitore, Stato convenzione, Classe dimensionale, Commerciale, Supervisore, Segnalatore, Operatori) la modifica **direttamente nell'elenco**, con le **stesse regole del dettaglio**: un valore non valido o un campo che non puoi modificare viene rifiutato con un messaggio e la cella torna al valore di prima.",
        },
        {
          type: 'note',
          text: "Togliendo la spunta **Fornitore** si toglie anche Fornitore qualificato, come dal dettaglio. Togliendo o aggiungendo persone negli **Operatori** gli altri restano nella loro posizione. Il **Nome** deriva dai dati anagrafici e si cambia dal dettaglio; Contatto principale e Creato il restano in sola lettura.",
        },
      ],
    },
    {
      id: 'registry-documents',
      title: "Documenti dell'anagrafica",
      blocks: [
        {
          type: 'paragraph',
          text: "Ogni anagrafica raccoglie i suoi file (visure, contratti quadro, certificazioni) nella scheda Documenti, nella colonna di destra del dettaglio. Dalla tabella li apri anche con l'azione Documenti della riga, che mostra quanti file ci sono.",
        },
        {
          type: 'steps',
          items: [
            "Apri il dettaglio dell'anagrafica e scegli la scheda Documenti, oppure premi Documenti nella riga della tabella.",
            "Trascina uno o più file nell'area di caricamento, oppure cliccala per sceglierli.",
            'Attendi la fine del caricamento: il file compare nella lista, pronto da scaricare.',
          ],
        },
        {
          type: 'paragraph',
          text: "Gli stessi file si consultano anche dal dettaglio di Opportunità, Offerte e Commesse di quell'anagrafica, nella scheda Documenti anagrafica: lì sono in sola lettura, si caricano ed eliminano solo dall'anagrafica.",
        },
        {
          type: 'tip',
          text: "La scheda e l'azione Documenti compaiono solo con il permesso Visualizza documenti delle Anagrafiche; per caricare o eliminare file servono anche i permessi sui Documenti. Eliminando un'anagrafica si eliminano anche i suoi documenti.",
        },
      ],
    },
    {
      id: 'related-records',
      title: 'Opportunità, offerte, commesse e task del cliente',
      blocks: [
        {
          type: 'paragraph',
          text: "Sotto la scheda dell'anagrafica trovi le schede Opportunità, Offerte, Commesse e Task: ognuna mostra la stessa tabella del modulo, con i soli record di quel cliente. Il numero accanto al nome compare dopo aver aperto la scheda.",
        },
        {
          type: 'list',
          items: [
            'Ricerca, filtri, colonne ed esportazione funzionano come nella pagina del modulo, ma restano limitati al cliente.',
            "Le azioni della riga (Visualizza, Note, Documenti, Elimina…) aprono il record in un pannello sopra l'anagrafica, senza lasciarla.",
            'Le Offerte sono quelle delle opportunità del cliente; le Commesse quelle nate dalle sue offerte; i Task quelli collegati al cliente, anche tramite una sua commessa.',
          ],
        },
        {
          type: 'steps',
          items: [
            'Apri la scheda del modulo che ti serve.',
            'Premi il pulsante Nuova opportunità, Nuova offerta, Nuova commessa o Nuovo task sopra la tabella.',
            "Compila il modulo: l'Opportunità e il Task partono già sull'anagrafica (l'opportunità prende anche Commerciale, Segnalatore, Supervisore e Gestori account del cliente); per l'Offerta e la Commessa la scelta dell'opportunità o dell'offerta propone solo quelle del cliente.",
            'Salva: la tabella della scheda si aggiorna.',
          ],
        },
        {
          type: 'tip',
          text: 'Vedi solo le schede dei moduli che puoi consultare, e il pulsante di creazione solo se puoi creare in quel modulo.',
        },
      ],
    },
    {
      id: 'configured-commissions',
      title: 'Commissioni configurate',
      blocks: [
        {
          type: 'paragraph',
          text: "Su ogni anagrafica, che sia segnata come **Fornitore** o no, accanto alle altre schede trovi **Commissioni configurate**: la tabella del Configuratore Commissioni con le sole regole che hanno questa anagrafica come **Destinatario**. Le regole Fornitore valide per tutti i fornitori non compaiono qui.",
        },
        {
          type: 'steps',
          items: [
            'Apri la scheda **Commissioni configurate**.',
            'Usa **Visualizza** sulla riga per aprire la regola in un pannello sopra l\'anagrafica, oppure **Elimina** se non è in uso.',
            "Per una nuova regola premi **Nuova configurazione**: il modulo parte con Ruolo destinatario **Fornitore**, ambito **Destinatario specifico** e questa anagrafica come Destinatario. Scrivi il nome, completa Calcolo e Validità e premi **Salva**.",
          ],
        },
        {
          type: 'tip',
          text: 'La scheda compare solo se puoi consultare il Configuratore Commissioni; il pulsante Nuova configurazione solo se puoi crearne.',
        },
      ],
    },
    {
      id: 'new-client-flow',
      title: 'Flusso tipico: un nuovo cliente con referenti e sedi',
      blocks: [
        {
          type: 'steps',
          items: [
            'Se i referenti non esistono ancora, creali da Anagrafiche › Referenti con Nuovo referente.',
            'Apri Anagrafiche › Anagrafiche e premi Nuova anagrafica.',
            'Apri i Dati anagrafici, scegli il Tipo, compilali e premi Fatto.',
            'Se compare Possibile duplicato, in cima alla colonna di destra, controlla prima di continuare.',
            'Nei Contatti, nella colonna di destra, compila almeno il Telefono.',
            'Compila l\'indirizzo con il Tipo sede giusto; con Aggiungi indirizzo inserisci le altre sedi.',
            'Apri le righe che ti servono: in Relazioni, se serve, Commerciale e Segnalatore; in Team Supervisore e Gestori account; in Referenti le persone di contatto. Conferma ogni riga con Fatto.',
            'Premi Salva.',
          ],
        },
      ],
    },
    {
      id: 'managing-duplicates',
      title: 'Gestire i duplicati',
      blocks: [
        {
          type: 'paragraph',
          text: "Quando crei un'anagrafica o un referente, QNet cerca schede simili. Se ne trova, mostra Possibile duplicato, con il tipo di scheda (Utente, Anagrafica o Referente) e il dato uguale: email, telefono, codice fiscale o partita IVA. Lo stesso avviso compare quando inserisci un nuovo cliente da Gestione Richieste.",
        },
        {
          type: 'paragraph',
          text: 'Codice fiscale e partita IVA vengono confrontati tra loro: per le aziende il codice fiscale di solito coincide con la partita IVA, quindi un codice fiscale uguale alla partita IVA di un\'altra scheda è un duplicato.',
        },
        {
          type: 'steps',
          items: [
            'Leggi il nome indicato nel riquadro.',
            'Cerca quella scheda nella tabella per verificare.',
            'Se è la stessa persona o azienda, premi Annulla e usa la scheda esistente.',
            'Se è un caso diverso, puoi salvare lo stesso.',
          ],
        },
        {
          type: 'warning',
          text: "Codice fiscale, partita IVA e telefono già presenti su un'altra scheda bloccano il salvataggio, ovunque li inserisci: creazione, modifica, contatti del dettaglio e Gestione Richieste. L'email invece genera solo l'avviso: tocca a te decidere se la scheda è davvero un duplicato.",
        },
      ],
    },
    {
      id: 'protected-fields',
      title: 'Campi protetti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Alcuni campi sono protetti: la modifica va proposta e poi approvata da un responsabile. Le Anagrafiche non hanno campi protetti.',
        },
        {
          type: 'note',
          text: 'Il campo Fonte è protetto in Gestione Richieste e in Gestione Iscritti: la procedura per proporne la modifica è descritta nella guida di Gestione Richieste.',
        },
      ],
    },
  ],
}

export default guide
