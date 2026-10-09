import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-orders',
  title: 'Commesse',
  summary: 'Il lavoro da svolgere su un contratto vinto.',
  sections: [
    {
      id: 'editing-a-work-order',
      title: 'Modificare una commessa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non esiste una pagina di modifica separata: la commessa si modifica **direttamente dal suo dettaglio**, un campo alla volta, comprese le **Informazioni aggiuntive** (ogni campo flessibile ha la sua riga).',
        },
        {
          type: 'steps',
          items: [
            'Apri la commessa dall\'elenco.',
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            'Premi **Salva** (o Invio nei campi di testo e data) per salvare solo quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) per chiuderlo lasciandolo com\'era, senza salvare.',
          ],
        },
        {
          type: 'tip',
          text: "Le **Note commessa** stanno nel riquadro giallo in cima al dettaglio, come le Note generali in Gestione Richieste: se sono vuote il riquadro invita a scriverle; con la matita (o un clic sul testo) le scrivi direttamente nel riquadro.",
        },
        {
          type: 'note',
          text: 'Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure si sceglie solo in creazione (Commessa n., Offerta collegata, Modello di Task). Anagrafica cliente, Contratto, Società e sedi derivano dall\'offerta; Stato e Completamento sono calcolati dai task.',
        },
        {
          type: 'note',
          text: 'Le **Righe prodotto** si scelgono alla creazione della commessa e poi non si modificano: nel dettaglio le vedi nella sezione **Dati contrattuali**. Le Informazioni aggiuntive che le righe portano con sé si compilano in creazione (quelle obbligatorie servono per salvare).',
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Modifica rapida dall'elenco",
      blocks: [
        {
          type: 'paragraph',
          text: "Un clic su una cella modificabile (Titolo, Tipo commessa, Data richiamo, Data inizio, Responsabili) la modifica **direttamente nell'elenco**, con le **stesse regole del dettaglio**: per esempio il Titolo, la Data inizio e almeno un Responsabile sono obbligatori; un valore non valido viene rifiutato e la cella torna al valore di prima.",
        },
        {
          type: 'note',
          text: "La **Chiusura forzata** resta un'azione di riga (chiede il motivo), non una cella. La colonna **Anagrafica** mostra il cliente della commessa, ripreso dall'offerta collegata: si può filtrare e ordinare ma non modificare. Restano in sola lettura Commessa n., Contratto n., Offerta collegata, Anagrafica, Stato, Completamento, Creata il e Aggiornata il.",
        },
      ],
    },
    {
      id: 'force-close',
      title: 'Chiusura forzata e riapertura',
      blocks: [
        {
          type: 'paragraph',
          text: 'La chiusura forzata è un\'**azione**, non un campo: la trovi in alto nel dettaglio della commessa e tra le azioni di riga della tabella Commesse (anche nella scheda Commesse del Contratto). Compare solo se puoi modificare la commessa.',
        },
        {
          type: 'steps',
          items: [
            'Premi **Chiusura forzata**.',
            'Scrivi il **motivo** (obbligatorio). Se la commessa ha task ancora aperti, il dialog ti avvisa quanti verranno chiusi con esito negativo.',
            'Premi **Chiudi commessa**: lo stato diventa Chiusa e il motivo compare tra i dati della commessa.',
          ],
        },
        {
          type: 'note',
          text: 'Su una commessa chiusa forzatamente l\'azione diventa **Riapri**: dopo la conferma la chiusura viene annullata e il motivo cancellato. I task chiusi dalla chiusura forzata restano chiusi.',
        },
      ],
    },
    {
      id: 'creating-a-work-order',
      title: 'Creare una commessa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo di creazione ha lo stesso aspetto del dettaglio: le stesse sezioni e righe, **chiuse**. Apri una riga con la matita, compila il campo e premi **Fatto** per tenerlo (o **Ripristina** per riportarlo com\'era). Il campo **Commessa n.** è già proposto.',
        },
        {
          type: 'note',
          text: 'Nella sezione **Offerta e righe prodotto** scegli prima l\'offerta, poi le sue righe: le Informazioni aggiuntive compaiono in fondo appena le righe le prevedono. **Salva** in alto (o in fondo) controlla tutti i campi e crea la commessa; gli errori compaiono sotto le righe da correggere. Uscendo senza salvare ti viene chiesta conferma.',
        },
      ],
    },
    {
      id: 'automatic-title',
      title: 'Titolo automatico',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il **Titolo** della commessa è facoltativo. Se lo lasci vuoto, la commessa prende il titolo automatico **codice - prodotti delle sue righe** (es. COM-0042 - ISO 9001 + SOA), che segue le righe quando cambiano.',
        },
        {
          type: 'note',
          text: 'Se scrivi un titolo tuo, resta il tuo e non viene più ricalcolato. Per tornare all\'automatico **svuota il campo** e salva. Le commesse già esistenti mantengono il titolo che hanno. Lo stesso vale per il dialogo **Programma** del contratto.',
        },
      ],
    },
    {
      id: 'costs',
      title: 'Costi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio della Commessa, sotto la scheda principale, **Task** e **Costi** stanno nella stessa card come schede da alternare: all\'apertura è attiva **Task** (se non hai il permesso di vederla, la prima disponibile). I **Dati contrattuali** non sono una scheda: stanno nella card principale, nella sezione **Dati contrattuali**. La scheda **Costi** confronta i costi **preventivati** (le righe di costo dell\'offerta imputate alle righe di ricavo della commessa) con i costi **effettivi** inseriti qui. Compare solo se hai il permesso di vedere i costi.',
        },
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['Scostamento', 'Costo effettivo meno costo preventivato: se positivo è uno **sforamento** (segnalato con icona e testo, non solo con il colore).'],
            ['Margine', 'Ricavo delle righe della commessa meno i costi. Il ricavo è l\'imponibile, tranne sulle righe con commissione Fornitore **ricevuta**, dove è la sola commissione Fornitore (come nei Dati contrattuali). Le altre provvigioni sono escluse.'],
            ['Non attribuiti', 'Costi effettivi senza una riga offerta di riferimento: entrano nel costo effettivo totale.'],
          ],
        },
        {
          type: 'note',
          text: 'I costi generici dell\'offerta, non imputati a nessuna riga di ricavo, sono mostrati a parte come informazione e non entrano nei totali del confronto.',
        },
        {
          type: 'steps',
          items: [
            'Apri la scheda **Costi effettivi** (solo lettura senza il permesso di gestire i costi).',
            'Premi **Aggiungi riga**: la data del costo è precompilata a oggi.',
            'Scegli il **prodotto** di costo: prezzo unitario, aliquota IVA e unità di misura si precompilano dal prodotto.',
            'Indica quantità, prezzo, IVA, **fornitore**, **riferimento documento** e la **riga offerta di riferimento**.',
            'Premi **Salva** per sostituire l\'intero elenco dei costi, oppure **Annulla** per tornare all\'ultimo salvataggio.',
          ],
        },
        {
          type: 'tip',
          text: 'I costi effettivi si possono inserire anche su una commessa completata o chiusa, perché spesso arrivano dopo la chiusura.',
        },
      ],
    },
    {
      id: 'contract-data',
      title: 'Dati contrattuali',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella card principale del dettaglio Commessa, la sezione **Dati contrattuali** mostra per ogni riga prodotto gli importi già salvati sull\'offerta e **spiega come si arriva al ricavo**. Senza il permesso di vedere i dati contrattuali vedi solo l\'elenco dei prodotti. Il nome del prodotto è un **link** al prodotto. Ogni riga prodotto sta su **una sola riga** (codice, nome e tipologia affiancati); su schermi stretti ogni riga diventa una scheda compatta.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['Prodotto', 'Codice, nome e tipologia del prodotto.'],
            ['Q.tà e Prezzo unitario', 'Quantità e prezzo della riga.'],
            ['Imponibile', 'Quantità per prezzo unitario, come salvato sull\'offerta.'],
            ['Commissione Fornitore', 'La commissione del fornitore sulla riga, se la vedi.'],
            ['Netto commissioni', 'Imponibile meno tutte le commissioni della riga, se le vedi.'],
            ['Ricavo effettivo', 'Se la tipologia della riga ha la commissione Fornitore **ricevuta** è la sola commissione Fornitore; se è **pagata** o non calcolata è l\'imponibile.'],
            ['Pagamento', 'Stato di pagamento (badge del colore dello stato), badge **Insoluto** se ci sono insoluti; l\'accordo sui pagamenti si legge nel tooltip della cella.'],
            ['Azioni', 'Il pulsante con la matita apre il popup **Pagamento riga** (solo con il permesso di gestire i pagamenti).'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Il **come si calcola** ogni importo sta nel tooltip: passa il mouse, naviga con la tastiera (Tab) o tocca l\'importo sottolineato a tratti per vedere la formula, ad esempio "2 × 500,00 = 1.000,00" per l\'Imponibile, "10% di 2.000,00 = 200,00" per la Commissione Fornitore (con tipo, base = margine della riga e importo), "1.000,00 − 250,00 = 750,00" per il Netto commissioni e il perché del Ricavo effettivo (commissione ricevuta, oppure imponibile). In alto una riga di indicatori: Imponibile, Totale Ricavi e, se visibili, Commissioni e Netto commissioni, ciascuno col suo tooltip; sotto, **Ricavo per tipologia** con imponibile → ricavo di ogni tipologia (quelle a 0,00 sono attenuate). Se non vedi le commissioni, le relative colonne e indicatori non compaiono.'
        },
        {
          type: 'warning',
          text: 'Un\'icona di avviso accanto al prodotto (con il testo nel tooltip) compare se a una riga con commissione Fornitore ricevuta manca la commissione (ricavo 0,00) oppure se la commissione è stata calcolata su una base precedente: salva di nuovo l\'offerta per aggiornarla.',
        },
        {
          type: 'steps',
          items: [
            'Premi il pulsante con la matita a fine riga (disponibile solo con il permesso di gestire i pagamenti): si apre il popup **Pagamento riga**.',
            'Scegli lo **stato di pagamento**, scrivi l\'**accordo sui pagamenti** e indica se ci sono **insoluti**.',
            'Premi **Salva** per salvare la riga e chiudere il popup, oppure **Annulla** per scartare le modifiche.',
          ],
        },
        {
          type: 'note',
          text: 'Gli stati di pagamento si configurano in **Stati pagamento commessa**. Quando una riga passa a uno stato con **Si può consegnare**, supervisori e partecipanti della commessa ricevono una notifica. I dati di pagamento si modificano anche su una commessa completata o chiusa.',
        },
      ],
    },
    {
      id: 'proforma-request',
      title: 'Richiesta di proforma (pulsante €)',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se hai il permesso di creare richieste proforma, nella colonna **Azioni** dell\'elenco Commesse (anche nel tab Commesse del contratto e dell\'anagrafica) trovi il pulsante **€**. Il colore indica lo stato: **grigio** nessuna richiesta, **blu** richiesta inviata e non ancora evasa, **giallo** proforma emessa.',
        },
        {
          type: 'steps',
          items: [
            'Premi il pulsante **€** grigio: si apre la finestra **Richiesta emissione Proforma: Commessa #numero**.',
            'Controlla la **Modalità di pagamento** presa dall\'offerta (se manca compare **Non indicata**).',
            'Scrivi le **Note per la Contabilità** (obbligatorie, massimo 5000 caratteri): il testo è precompilato con il titolo della finestra.',
            'Premi **Invia richiesta**. Il sistema crea una richiesta per le righe di tipo Consulenza e una per ciascun fornitore delle righe di tipo Ente.',
          ],
        },
        {
          type: 'note',
          text: 'Con il pulsante **€** blu la finestra mostra **Ultima richiesta del** e la data: finché la richiesta non è evasa non se ne può inviare un\'altra e **Invia richiesta** resta disattivato. Con il pulsante giallo non si apre nulla.',
        },
        {
          type: 'tip',
          text: 'Le richieste inviate si consultano in **Contabilità › Attiva › Richieste Proforma**.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. La guida verrà pubblicata quando il modulo sarà completato.',
        },
      ],
    },
  ],
}

export default guide
