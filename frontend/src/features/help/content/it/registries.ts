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
            'Per restringere la ricerca usa i filtri sulle colonne, per esempio Fonte, Fornitore o Stato convenzione.',
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
          text: 'Per creare una scheda premi Nuova anagrafica. Il modulo è diviso in sezioni; a destra un riquadro Riepilogo si aggiorna mentre compili. Scegli prima il Tipo, Persona fisica o Azienda: i campi cambiano di conseguenza.',
        },
        {
          type: 'table',
          headers: ['Sezione', 'Cosa contiene'],
          rows: [
            ['Dati anagrafici', 'Ragione sociale (aziende) oppure Nome e Cognome (persone fisiche), codice fiscale, partita IVA e, per le persone fisiche, data e luogo di nascita.'],
            ['Relazioni', 'Fonte, Settori merceologici, Referenti, Commerciale e Segnalatore.'],
            ['Team', 'Supervisore e Gestori account, in ordine di importanza dal primo in alto; riordinali con Sposta su e Sposta giù.'],
            ['Dati commerciali', 'Gruppo IVA, Fornitore, Fornitore qualificato, Stato convenzione (In trattativa, Respinta o Concordata) e Classe dimensionale.'],
            ['Contatti', 'Email, Telefono (obbligatorio), PEC e Fax; con Aggiungi contatto ne inserisci altri e indichi il Contatto principale.'],
            ['Indirizzi', 'Uno o più indirizzi, ciascuno con un Tipo sede: Sede legale, Consegna, Fatturazione o Sede operativa.'],
            ['Altri campi', "I campi personalizzati delle anagrafiche, tra cui Tag: scegli uno o più tag dall'elenco gestito nel modulo Tag."],
          ],
        },
        {
          type: 'warning',
          text: 'Solo le anagrafiche segnate come Fornitore compaiono tra i fornitori selezionabili nella scheda prodotto.',
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
      id: 'new-client-flow',
      title: 'Flusso tipico: un nuovo cliente con referenti e sedi',
      blocks: [
        {
          type: 'steps',
          items: [
            'Se i referenti non esistono ancora, creali da Anagrafiche › Referenti con Nuovo referente.',
            'Apri Anagrafiche › Anagrafiche e premi Nuova anagrafica.',
            'Scegli il Tipo e compila i Dati anagrafici.',
            'Se compare Possibile duplicato, controlla prima di continuare.',
            'In Relazioni scegli i Referenti e, se serve, Commerciale e Segnalatore.',
            'In Team assegna Supervisore e Gestori account.',
            'Inserisci almeno il telefono nei Contatti.',
            'In Indirizzi aggiungi un indirizzo per ogni sede con il Tipo sede giusto e premi Salva.',
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
          text: "Quando crei un'anagrafica o un referente, QNet cerca schede simili. Se ne trova, mostra Possibile duplicato, con il tipo di scheda (Utente, Anagrafica o Referente) e il dato uguale: email, telefono, codice fiscale o partita IVA.",
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
          text: "L'avviso non blocca il salvataggio. Tocca a te decidere se la scheda è davvero un duplicato.",
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
