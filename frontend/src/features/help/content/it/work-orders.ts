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
          type: 'note',
          text: 'Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura, oppure si sceglie solo in creazione (Commessa n., Offerta collegata, Modello di Task). Anagrafica cliente, Contratto, Società e sedi derivano dall\'offerta; Stato e Completamento sono calcolati dai task.',
        },
        {
          type: 'note',
          text: 'Cambiando le **Righe prodotto**, sotto il campo compaiono le Informazioni aggiuntive che le nuove righe portano con sé: compilale nello stesso salvataggio (quelle obbligatorie servono per salvare).',
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
      id: 'costs',
      title: 'Costi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio della Commessa, sotto la scheda principale, **Dati contrattuali**, **Task** e **Costi** stanno nella stessa card come schede da alternare: all\'apertura è attiva **Dati contrattuali** (se non hai il permesso di vederla, la prima disponibile). La scheda **Costi** confronta i costi **preventivati** (le righe di costo dell\'offerta imputate alle righe di ricavo della commessa) con i costi **effettivi** inseriti qui. Compare solo se hai il permesso di vedere i costi.',
        },
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['Scostamento', 'Costo effettivo meno costo preventivato: se positivo è uno **sforamento** (segnalato con icona e testo, non solo con il colore).'],
            ['Margine', 'Ricavo netto delle righe della commessa meno i costi, calcolato sull\'imponibile. Le provvigioni sono escluse.'],
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
          text: 'La scheda **Dati contrattuali** del dettaglio Commessa, accanto a **Task** e **Costi**, mostra per ogni riga prodotto gli importi già salvati sull\'offerta e **spiega come si arriva al ricavo**. Compare solo se hai il permesso di vedere i dati contrattuali. Su schermi stretti la tabella scorre dentro il suo riquadro.',
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
            ['Ricavo effettivo', 'Per una riga **Ente** è la sola commissione Fornitore; per ogni altra tipologia (Consulenza) è l\'imponibile.'],
            ['Pagamento', 'Stato di pagamento, accordo sui pagamenti e insoluti della riga.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Sotto ogni riga trovi la **formula in chiaro**, ad esempio "Imponibile 2 × 500,00 = 1.000,00 → ricavo 1.000,00" per una Consulenza, oppure "Commissione Fornitore 10% di 2.000,00 = 200,00 → ricavo 200,00" per un Ente. In alto i totali: Imponibile, poi per **ogni tipologia di prodotto** l\'imponibile e il ricavo effettivo (anche a 0,00), quindi Totale Ricavi e, se visibili, Commissioni e Netto commissioni. Se non vedi le commissioni, le relative colonne e totali non compaiono.',
        },
        {
          type: 'warning',
          text: 'Un avviso con icona e testo compare se a una riga Ente manca la commissione Fornitore (ricavo 0,00) oppure se la commissione è stata calcolata su una base precedente: salva di nuovo l\'offerta per aggiornarla.',
        },
        {
          type: 'steps',
          items: [
            'Premi la matita nella colonna **Pagamento** della riga (disponibile solo con il permesso di gestire i pagamenti).',
            'Scegli lo **stato di pagamento**, scrivi l\'**accordo sui pagamenti** e indica se ci sono **insoluti**.',
            'Premi **Fatto** per salvare la riga, oppure **Ripristina** per annullare le modifiche.',
          ],
        },
        {
          type: 'note',
          text: 'Gli stati di pagamento si configurano in **Stati pagamento commessa**. Quando una riga passa a uno stato con **Si può consegnare**, supervisori e partecipanti della commessa ricevono una notifica. I dati di pagamento si modificano anche su una commessa completata o chiusa.',
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
