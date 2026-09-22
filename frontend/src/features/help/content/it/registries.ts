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
          ],
        },
        {
          type: 'warning',
          text: 'Solo le anagrafiche segnate come Fornitore compaiono tra i fornitori selezionabili nella scheda prodotto.',
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
