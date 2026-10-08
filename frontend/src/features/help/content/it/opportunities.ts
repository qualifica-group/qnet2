import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'opportunities',
  title: 'Opportunità',
  summary: "L'opportunità è una trattativa commerciale con un'anagrafica, nata da un lead o creata a mano.",
  sections: [
    {
      id: 'create-an-opportunity',
      title: "Creare un'opportunità",
      blocks: [
        {
          type: 'paragraph',
          text: "L'opportunità è una trattativa con un'**Anagrafica**. Nasce da un lead oppure viene creata a mano.",
        },
        {
          type: 'steps',
          items: [
            'Apri **Opportunità e Commesse › Opportunità**.',
            'Clicca **Nuova opportunità**.',
            'Compila le sezioni (vedi tabella): il modulo ha lo stesso aspetto del dettaglio, con le righe **chiuse**. Apri una riga con la matita, compila il campo e premi **Fatto** per tenerlo (o **Ripristina** per riportarlo com\'era).',
            'Clicca **Salva** (in alto o in fondo): controlla tutti i campi e crea l\'opportunità; gli errori compaiono sotto le righe da correggere. Uscendo senza salvare ti viene chiesta conferma.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi'],
          rows: [
            ['Lead di origine', '**Lead** (facoltativo, precompila e blocca i campi derivati)'],
            ['Note generali', 'Testo libero'],
            ['Dettagli', '**Titolo** (facoltativo), **Data inizio**, **Data chiusura prevista**, **Valore stimato**, **Probabilità di successo (%)**'],
            ['Anagrafica e contatti', '**Anagrafica** (obbligatoria), **Referente**, **Commerciale**, **Segnalatore** con i **Buoni assegnati**'],
            ['Classificazione', '**Fonte**, **Linee prodotto** (almeno una riga: categoria genitore, **Filtro** intermedio facoltativo, categoria prodotto), **Prodotti di interesse**'],
            ['Team', '**Supervisore**, **Gestori account**'],
          ],
        },
        {
          type: 'tip',
          text: "Il **Titolo** è proposto in automatico: il codice dell'opportunità seguito dai prodotti delle righe di ricavo delle offerte, non dai prodotti di interesse (per esempio OPP_12 - ISO 9001 + SOA); finché non ci sono prodotti vale solo il codice. Nel dettaglio il campo mostra il titolo attuale: se scrivi un titolo tuo resta quello, anche quando cambiano le offerte; svuota il campo per tornare al titolo automatico.",
        },
        {
          type: 'tip',
          text: "Scegliendo l'**Anagrafica**, **Commerciale**, **Segnalatore**, **Supervisore** e **Gestori account** vengono compilati con quelli dell'anagrafica. Se li hai già inseriti tu e sono diversi, QNet chiede se **Sostituire** i tuoi valori o **Mantenere i tuoi**: nulla di ciò che hai scelto viene cancellato senza conferma.",
        },
        {
          type: 'paragraph',
          text: "Chi inserisci come **Supervisore** o tra i **Gestori account** riceve la notifica **Sei stato inserito come Supervisore** o **Sei stato inserito come Gestore Account** (campanella ed email). Il link apre l'opportunità; chi non ha accesso alle Opportunità riceve la notifica senza link. Chi è anche Gestore account di un'offerta dell'opportunità, per esempio perché viene copiato nell'offerta collegata di un lead convertito o di una categoria con gestori sincronizzati, riceve solo la notifica dell'offerta.",
        },
      ],
    },
    {
      id: 'editing-an-opportunity',
      title: "Modificare un'opportunità",
      blocks: [
        {
          type: 'paragraph',
          text: "Non esiste una pagina di modifica separata: l'opportunità si modifica **direttamente dal suo dettaglio**, un campo alla volta.",
        },
        {
          type: 'steps',
          items: [
            "Apri l'opportunità dall'elenco.",
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            "Premi **Salva** (o Invio nei campi di testo e data) per salvare solo quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) per chiuderlo lasciandolo com'era, senza salvare.",
          ],
        },
        {
          type: 'tip',
          text: "Le **Note generali** stanno nel riquadro giallo in cima al dettaglio, come in Gestione Richieste: se sono vuote il riquadro invita a scriverle; con la matita (o un clic sul testo) le scrivi direttamente nel riquadro.",
        },
        {
          type: 'note',
          text: "Alcuni campi si portano dietro altri valori nello stesso salvataggio: cambiando l'**Anagrafica** il Referente si svuota e Commerciale, Segnalatore, Supervisore e Gestori account vengono proposti da quelli dell'anagrafica (con conferma se sono diversi); cambiando le **Righe di classificazione** i Prodotti di interesse non più coperti vengono tolti; i **Buoni assegnati** si modificano dal campo **Segnalatore**, di cui sono il premio.",
        },
        {
          type: 'note',
          text: "Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure è ereditato e bloccato dal lead di origine (Anagrafica, Fonte), oppure cambiarlo modificherebbe un campo che non puoi toccare. Il **Lead di origine** non si cambia; lo **Stato** è calcolato dalle offerte.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Modifica rapida dall'elenco",
      blocks: [
        {
          type: 'paragraph',
          text: "Un clic su una cella modificabile (Nome, Anagrafica, Referente, Commerciale, Supervisore, Gestori account, Fonte, Categoria prodotto, Prodotti di interesse, Valore stimato, Probabilità, Data inizio, Data chiusura prevista) la modifica **direttamente nell'elenco**, con le **stesse regole del dettaglio**: un valore non valido o un campo che non puoi modificare viene rifiutato con un messaggio e la cella torna al valore di prima.",
        },
        {
          type: 'note',
          text: "Cambiando l'**Anagrafica** dall'elenco il Referente si svuota e i ruoli **vuoti** (Commerciale, Segnalatore, Supervisore, Gestori account) prendono quelli della nuova anagrafica; i ruoli già compilati restano com'erano (dall'elenco non c'è la richiesta di conferma). Il Referente si sceglie tra quelli dell'anagrafica della riga; un Nome vuoto torna al titolo automatico.",
        },
        {
          type: 'paragraph',
          text: "Togliendo o aggiungendo persone nei **Gestori account** gli altri restano nella loro posizione; i nuovi occupano il primo posto libero. Restano in sola lettura Sede operativa, Stato (calcolato dalle offerte), Funzione aziendale (deriva dalla categoria) e Creato il.",
        },
      ],
    },
    {
      id: 'from-a-lead',
      title: 'Ereditarietà da un lead',
      blocks: [
        {
          type: 'paragraph',
          text: "Se l'opportunità nasce da un lead, alcuni campi sono ereditati e bloccati: un avviso in alto lo segnala. I **Gestori account** sono sincronizzati con l'offerta collegata.",
        },
        {
          type: 'warning',
          text: "Un'anagrafica può avere una sola opportunità aperta alla volta. Se ne esiste già una, QNet propone **Aggiungi l'offerta a questa opportunità**.",
        },
      ],
    },
    {
      id: 'status-and-constraints',
      title: 'Stato e vincoli',
      blocks: [
        {
          type: 'paragraph',
          text: 'Lo **Stato** dell\'opportunità non si imposta a mano: è calcolato dagli stati delle sue offerte (per esempio "2 stati" se sono diversi). Nella scheda trovi l\'elenco delle **Offerte** e il pulsante **Nuova offerta**.',
        },
        {
          type: 'warning',
          text: "Alcune categorie prodotto ammettono una sola offerta per opportunità.",
        },
      ],
    },
  ],
}

export default guide
