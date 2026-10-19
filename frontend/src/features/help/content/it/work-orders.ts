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
          text: 'Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure si sceglie solo in creazione (Commessa n., Offerta collegata, Modello di Task). Anagrafica cliente, Contratto (codice e titolo), Società e sedi derivano dall\'offerta, come la Scadenza contratto mostrata tra gli indicatori in alto (si modifica sul contratto); Stato e Completamento sono calcolati dai task.',
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
          text: "La **Chiusura forzata** resta un'azione di riga (chiede il motivo), non una cella. La colonna **Anagrafica** mostra il cliente della commessa, ripreso dall'offerta collegata: si può filtrare e ordinare ma non modificare. Lo stesso vale per la colonna **Scadenza contratto**, la data di scadenza del contratto da cui nasce la commessa (filtro per data, vuota se il contratto non ha una scadenza). Restano in sola lettura Commessa n., Contratto n., Offerta collegata, Anagrafica, Scadenza contratto, Stato, Completamento, Creata il e Aggiornata il.",
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
            ['Consuntivo complessivo', 'Somma, al netto dell\'IVA, di tre fonti: i **costi inseriti a mano**, le righe degli **ODA impegnati** della commessa (approvati, inviati al fornitore, parzialmente o interamente evasi) **non ancora fatturati** (l’impegnato di una riga è la quantità ordinata non ancora fatturata per il prezzo unitario, oppure la quantità ricevuta se il residuo è chiuso), e le righe delle **fatture passive e proforma** della commessa. Una riga ODA fatturata conta una sola volta, con l\'importo del documento passivo. Bozze, ODA in validazione e annullati sono esclusi.'],
            ['Margine consuntivo', 'Ricavo meno il consuntivo complessivo; lo scostamento complessivo è il consuntivo complessivo meno il costo preventivato.'],
            ['Per tipo di costo', 'Tabella con, per ogni **tipo di costo** (colore e natura: diretto, indiretto, generale), l\'importo delle tre fonti e il totale. I costi senza tipo sono nella riga **Senza tipo di costo**. Il budget non è ripartito per tipo di costo.'],
          ],
        },
        {
          type: 'note',
          text: 'I costi generici dell\'offerta, non imputati a nessuna riga di ricavo, sono mostrati a parte come informazione e non entrano nei totali del confronto.',
        },
        {
          type: 'steps',
          items: [
            'Apri la scheda **Costi effettivi**: un unico riquadro con a sinistra il **Catalogo prodotti** e a destra i costi effettivi come card (su schermi stretti uno sotto l\'altro). Senza il permesso di gestire i costi il catalogo non compare e le card sono in sola lettura.',
            'Nel **Catalogo prodotti** cerca per nome o descrizione: ogni prodotto mostra nome, codice, tipologia, prezzo di costo con unità di misura, categoria e descrizione.',
            'Spunta uno o più prodotti e premi **Aggiungi N selezionati**: ogni prodotto diventa un costo con data di oggi, quantità 1, prezzo di costo, IVA, unità di misura e **tipo di costo** del prodotto. Lo stesso prodotto si può aggiungere più volte.',
            'In ogni card trovi in alto prodotto, codice e categoria con il cestino per rimuoverla; sotto quantità per prezzo unitario, IVA, **Riga offerta di riferimento** e imponibile, IVA e totale; in fondo data, **tipo di costo**, **fornitore**, **riferimento documento** e descrizione aggiuntiva. In fondo al riquadro c\'è il **Totale costi**.',
            'Per cambiare prodotto rimuovi la card e scegli il prodotto dal catalogo.',
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
      id: 'multi-quote-work-order',
      title: 'Commessa multi-offerta',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una commessa **multi-offerta** raccoglie righe di più offerte (e quindi di più clienti). Si crea dalla tabella **Contratti** con **Programma commessa unica** (vedi la guida Contratti) e si riconosce dal badge **Multi-offerta**.',
        },
        {
          type: 'list',
          items: [
            'Nel dettaglio non ci sono le sezioni cliente, contratto, azienda e sede: ogni riga prodotto mostra sotto il nome l\'**anagrafica** a cui si riferisce, con il collegamento alla sua scheda.',
            'Le righe, dopo la creazione, si possono solo **togliere** (almeno una deve restare): non se ne possono aggiungere di altre offerte.',
            'Nell\'elenco le colonne Cliente e Offerta mostrano più valori separati da " · "; filtrando per uno dei clienti la commessa viene trovata. La scadenza contratto resta vuota.',
            'La commessa compare nel tab **Commesse** del contratto di ciascuna offerta di origine.',
            'Il budget dei costi è dato dalle righe di costo di **tutte** le offerte di origine.',
            'I task generati dal modello non hanno un cliente.',
            'Nella scheda Email non ci sono variabili di cliente o offerta e i destinatari non sono precompilati.',
          ],
        },
        {
          type: 'note',
          text: 'Le richieste proforma di una commessa multi-offerta sono una per offerta: vedi la guida Richieste Proforma. Un\'offerta con righe in una commessa multi-offerta non si può eliminare.',
        },
      ],
    },
    {
      id: 'proforma-request',
      title: 'Richiesta di proforma (pulsante €)',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se hai il permesso di creare richieste proforma, nella colonna **Azioni** dell\'elenco Commesse (anche nel tab Commesse del contratto e dell\'anagrafica) trovi il pulsante **€**. Il colore indica lo stato: **grigio** nessuna richiesta, **blu** richiesta inviata e non ancora evasa, **giallo** richieste già evase (proforma emessa).',
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
          text: 'Con il pulsante **€** blu la finestra mostra **Ultima richiesta del** e la data: finché la richiesta non è evasa non se ne può inviare un\'altra e **Invia richiesta** resta disattivato. Con il pulsante giallo non si apre nulla: le richieste sono già evase e non se ne possono generare di nuove. Una commessa genera le richieste una sola volta.',
        },
        {
          type: 'tip',
          text: 'Le richieste inviate si consultano in **Contabilità › Attiva › Richieste Proforma**.',
        },
      ],
    },
    {
      id: 'deleting-a-work-order',
      title: 'Eliminare una commessa',
      blocks: [
        {
          type: 'warning',
          text: 'Una commessa **non si può eliminare** se ha richieste proforma, fatture attive, ordini di acquisto (ODA) o fatture passive collegati: in questo caso il pulsante **Elimina** non compare. Task, ore e costi inseriti a mano non bloccano l\'eliminazione.',
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
