import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'migrations',
  title: 'Migrazioni',
  summary: 'Il modulo Migrazioni importa nel gestionale i dati di un sistema esterno, di solito all’avvio: ruoli, utenti, società, sedi, referenti, prodotti, anagrafiche, opportunità, offerte e commesse.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Amministrazione › Migrazioni** ed è riservato al ruolo **super-admin**. Ogni **Sorgente** rappresenta un tipo di dato da importare: Ruoli, Utenti, Funzioni aziendali, Società aziendali, Società sedi, Sedi operative, Tipi referente, Referenti, Fonti, Tag, Settori, Aliquote IVA, Attributi, Categorie prodotto, Prodotti, Prodotti di costo (**Cost products**), Modelli email (**Email templates**), Modelli documenti (**Document bundles**), Modelli di task, Layout attributi (**Attribute layouts**), Anagrafiche, Opportunità, Offerte e Commesse.' },
        { type: 'note', text: 'La sorgente **Cost products** importa i costi del QNet legacy: articoli di magazzino, veicoli, attrezzature e note spese. Ogni voce diventa un prodotto **Utilizzabile come costo** (solo tab Costi dell\'offerta) sotto la categoria **Costi**, nelle sotto-categorie **Articoli**, **Veicoli**, **Attrezzature** e **Note spese**; un articolo finisce in una sotto-categoria di Articoli col nome della sua categoria legacy. Targa, matricola, marca, modello, barcode, codice ministeriale e posizione di magazzino diventano attributi del prodotto. Per gli articoli il costo è il prezzo di acquisto legacy. Esegui prima **Aliquote IVA**, altrimenti l\'aliquota resta vuota con un avviso.' },
        { type: 'note', text: 'Le sorgenti **Email templates** e **Document bundles** importano i Modelli email e i Modelli documenti del QNet legacy, questi ultimi con tutti i loro file. Un nome già usato riceve un suffisso numerico; un file troppo grande o con un formato non ammesso viene saltato con un avviso, senza far fallire la riga.' },
        { type: 'note', text: 'La sorgente **Attribute layouts** importa le sezioni della Commessa di tutte le card legacy (ISO, SOA, Sicurezza, Avvalimenti, GDPR, R&S, PAL, Finanza agevolata, Gare, Partnership sedi, Istruzione, Formazione GOL) e, per l\'Offerta, le "Info preliminari" dell\'opportunità legacy (ISO con i gruppi della sola norma della categoria e la tabella Sedi, SOA, Avvalimenti, GDPR, R&S, Gare, Partnership, Progetti, Istruzione). Il campo **Stato Lavorazione** riceve gli stati della famiglia (radice) della categoria. Una categoria riceve i campi (Commessa e Offerta) solo dalle card dei propri prodotti. Esegui prima **Attributi**, **Categorie prodotto** e **Categorie prodotto — collega attributi**: un layout che usa un campo non collegato alla categoria viene scartato. Un layout già presente non viene sovrascritto. I valori dei campi delle singole commesse non vengono importati, né le note libere delle Info preliminari e i campi Formazione GOL dell\'Offerta. I campi e i layout del ramo Formazione configurati in QNet non vengono modificati.' },
        { type: 'note', text: 'La sorgente **Modelli di task** importa modello, fasi, attività e sotto-attività. Un modello col titolo che contiene "non attivo" arriva disattivato. Un titolo già usato da un altro modello riceve il suffisso "(old_id N)". Una sotto-attività con un problema (padre inesistente, padre in un altro modello, ciclo) diventa un\'attività principale, con un avviso nel report.' },
      ],
    },
    {
      id: 'preview-step',
      title: 'Fase 1: controllo e anteprima',
      blocks: [
        { type: 'steps', items: ['Apri **Amministrazione › Migrazioni**.', 'Nel campo **Sorgente** scegli il tipo di dati da importare, ad esempio **Utenti** o **Prodotti**.', 'Il riquadro **Template atteso** elenca i campi che QNet si aspetta dal sistema esterno.', 'Fai clic su **Mostra anteprima dati esterni**. Usa **Precedente** e **Successiva** per sfogliare i dati.'] },
        { type: 'note', text: 'In questa fase non viene creato nulla.' },
      ],
    },
    {
      id: 'import-step',
      title: 'Fase 2: importazione',
      blocks: [
        { type: 'steps', items: ['Dopo aver controllato l’anteprima, fai clic su **Importa questa sorgente**.', 'Leggi la finestra di conferma e fai clic su **Avvia import**.', 'Segui l’avanzamento. Alla fine vedi il riepilogo: **Righe totali**, **Creati**, **Saltati** e **Falliti**.', 'Controlla **Avvisi ed errori** per le righe con problemi.'] },
        { type: 'tip', text: 'L’import continua anche se chiudi la finestra. Le schede già importate vengono saltate, quindi puoi ripetere l’import senza creare doppioni.' },
      ],
    },
    {
      id: 'operational-records',
      title: 'Anagrafiche, opportunità, offerte e commesse',
      blocks: [
        { type: 'paragraph', text: 'Le sorgenti **Anagrafiche**, **Opportunità**, **Offerte** e **Commesse** importano i dati operativi del QNet legacy. Vanno eseguite in quest’ordine e dopo **Utenti**, **Referenti**, **Fonti**, **Settori**, **Sedi operative**, **Categorie prodotto**, **Prodotti**, **Aliquote IVA**, **Metodi di pagamento** e **Società sedi**: ogni sorgente collega i record già importati dalle precedenti.' },
        { type: 'list', items: [
          '**Anagrafiche**: un cliente privato diventa una scheda persona fisica, gli altri una scheda azienda. La sede legale è l’indirizzo principale, le altre sedi legacy diventano indirizzi aggiuntivi. Le partite IVA segnaposto (0, 1, punto) non vengono importate.',
          '**Opportunità**: arrivano solo quelle con un cliente. Le richieste di corso GOL senza cliente non vengono importate. Un cliente può avere più opportunità aperte.',
          '**Offerte**: arrivano le offerte delle opportunità importate, con codice **QUO-** seguito dall’id legacy, le righe di ricavo, l’IVA e le provvigioni con percentuale o importo. Lo stato segue quello legacy: presentata resta aperta, rifiutata diventa chiusa negativa, accettata o contrattualizzata diventa chiusa positiva e crea il contratto con lo stato e le date del legacy.',
          '**Commesse**: arrivano con codice **COM-** seguito dall’id legacy, le righe dell’offerta collegate, supervisori e partecipanti. Le commesse chiuse, annullate o disdette nel legacy arrivano chiuse forzatamente con il motivo.',
        ] },
        { type: 'note', text: 'Durante questo import non partono notifiche né email e non vengono scritte voci nel registro attività. Note, documenti, valori dei campi flessibili, fasi e task delle commesse non vengono importati. Una riga d’offerta senza prodotto importato (ad esempio uno sconto) viene saltata con un avviso; una riga già collegata a un’altra commessa resta sulla prima.' },
      ],
    },
    {
      id: 'mass-import',
      title: 'Importare tutto insieme',
      blocks: [
        { type: 'steps', items: ['Fai clic su **Configura ordine**.', 'Scegli le sorgenti da includere, trascinale nell’ordine giusto e fai clic su **Salva ordine**.', 'Fai clic su **Importa tutto** e poi su **Avvia import**.'] },
        { type: 'paragraph', text: 'Le sorgenti vengono importate una dopo l’altra. Se una fallisce, l’import si ferma e le successive risultano **Non eseguita**.' },
        { type: 'tip', text: 'Metti prima i dati da cui dipendono gli altri: i ruoli prima degli utenti, le società prima delle sedi.' },
      ],
    },
  ],
}

export default guide
