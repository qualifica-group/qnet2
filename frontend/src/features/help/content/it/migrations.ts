import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'migrations',
  title: 'Migrazioni',
  summary: 'Il modulo Migrazioni importa nel gestionale i dati di un sistema esterno, di solito all’avvio: ruoli, utenti, società, sedi, prodotti e le tabelle di appoggio con i loro collegamenti.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Develop › Migrazioni** ed è riservato al ruolo **super-admin**. Ogni **Sorgente** rappresenta un tipo di dato da importare: Ruoli, Utenti, Funzioni aziendali, Società aziendali, Società sedi, Sedi operative, Tipi di referente, Fonti, Tag, Settori, Modelli di task, Aliquote IVA, Modalità di pagamento, Attributi, Categorie prodotto, Layout attributi, Prodotti, Prodotti di costo, Modelli email e Modelli documenti.' },
        { type: 'note', text: 'Le sorgenti con **— collega** nel nome non creano nuovi record: collegano tra loro record già importati, quindi vanno eseguite dopo le sorgenti di quei record. **Funzioni aziendali — collega responsabile e operatori** assegna responsabile e operatori alle funzioni aziendali (dopo **Utenti**). **Categorie prodotto — collega attributi** associa gli attributi alle categorie (dopo **Attributi** e **Categorie prodotto**).' },
        { type: 'note', text: 'La sorgente **Prodotti di costo** importa i costi del QNet legacy: articoli di magazzino, veicoli, attrezzature e note spese. Ogni voce diventa un prodotto **Utilizzabile come costo** (solo tab Costi dell\'offerta) sotto la categoria **Costi**, nelle sotto-categorie **Articoli**, **Veicoli**, **Attrezzature** e **Note spese**; un articolo finisce in una sotto-categoria di Articoli col nome della sua categoria legacy. Targa, matricola, marca, modello, barcode, codice ministeriale e posizione di magazzino diventano attributi del prodotto. Per gli articoli il costo è il prezzo di acquisto legacy. Esegui prima **Aliquote IVA**, altrimenti l\'aliquota resta vuota con un avviso.' },
        { type: 'note', text: 'Le sorgenti **Modelli email** e **Modelli documenti** importano i Modelli email e i Modelli documenti del QNet legacy, questi ultimi con tutti i loro file. Un nome già usato riceve un suffisso numerico; un file troppo grande o con un formato non ammesso viene saltato con un avviso, senza far fallire la riga.' },
        { type: 'note', text: 'La sorgente **Layout attributi** importa le sezioni della Commessa di tutte le card legacy (ISO, SOA, Sicurezza, Avvalimenti, GDPR, R&S, PAL, Finanza agevolata, Gare, Partnership sedi, Istruzione, Formazione GOL) e, per l\'Offerta, le "Info preliminari" dell\'opportunità legacy (ISO con i gruppi della sola norma della categoria e la tabella Sedi, SOA, Avvalimenti, GDPR, R&S, Gare, Partnership, Progetti, Istruzione). Il campo **Stato Lavorazione** riceve gli stati della famiglia (radice) della categoria. Una categoria riceve i campi (Commessa e Offerta) solo dalle card dei propri prodotti. Esegui prima **Attributi**, **Categorie prodotto** e **Categorie prodotto — collega attributi**: un layout che usa un campo non collegato alla categoria viene scartato. Un layout già presente non viene sovrascritto. I valori dei campi delle singole commesse non vengono importati, né le note libere delle Info preliminari e i campi Formazione GOL dell\'Offerta. I campi e i layout del ramo Formazione configurati in QNet non vengono modificati.' },
        { type: 'note', text: 'La sorgente **Modelli di task** importa modello, fasi, attività e sotto-attività. Un modello col titolo che contiene "non attivo" arriva disattivato. Un titolo già usato da un altro modello riceve il suffisso "(old_id N)". Una sotto-attività con un problema (padre inesistente, padre in un altro modello, ciclo) diventa un\'attività principale, con un avviso nel report.' },
      ],
    },
    {
      id: 'preview-step',
      title: 'Fase 1: controllo e anteprima',
      blocks: [
        { type: 'steps', items: ['Apri **Develop › Migrazioni**.', 'Nel campo **Sorgente** scegli il tipo di dati da importare, ad esempio **Utenti** o **Prodotti**.', 'Il riquadro **Template atteso** elenca i campi che QNet si aspetta dal sistema esterno.', 'Fai clic su **Mostra anteprima dati esterni**. Usa **Precedente** e **Successiva** per sfogliare i dati.'] },
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
      id: 'not-imported',
      title: 'Cosa non viene importato',
      blocks: [
        { type: 'paragraph', text: '**Referenti**, **Anagrafiche**, **Opportunità**, **Offerte** e **Commesse** non vengono importati dal QNet legacy: sono moduli troppo grandi e si gestiscono direttamente in QNet. Per lo stesso motivo non vengono importati i dati che dipendono da loro: il fornitore dei prodotti, le regole del **Configuratore commissioni** e i dati di pagamento delle commesse.' },
        { type: 'note', text: 'Un prodotto importato arriva senza fornitore, anche se nel legacy ne aveva uno: lo scegli tu in QNet. Le regole delle commissioni si configurano in QNet.' },
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
