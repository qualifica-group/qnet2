/**
 * "Cosa serve per migrare bene" per ogni sorgente di migrazione registrata,
 * mostrato nel template della sorgente. Le sorgenti da importare prima
 * arrivano dal backend (`dependencies`); qui stanno i requisiti che non sono
 * sorgenti e i comportamenti da sapere. Mirrors `en-migration-requirements.ts` 1:1.
 */
export const migrationRequirements = {
  roles:
    'Nessun prerequisito. Un ruolo con lo stesso nome già presente in QNet, super-admin compreso, viene riusato e la descrizione si completa solo se è vuota. I permessi non vengono importati: assegnali da Amministrazione › Ruoli.',
  users:
    'Senza Ruoli, Società aziendali e Sedi operative, ruolo, società e sede restano vuoti con un avviso. Il responsabile di ogni utente si collega da solo a fine import. Email, nome e cognome sono obbligatori. Un utente già presente in QNet viene riconosciuto per email, oppure per nome e cognome se non ha ancora un id legacy: riceve l’id legacy e si completano solo società, sede e responsabile vuoti; ruolo, password e contatti restano quelli di QNet. Se più utenti hanno lo stesso nome, la riga fallisce. Un utente attivo non riconosciuto viene creato con [OLD] davanti al nome. Per gli utenti nuovi la password deve arrivare già cifrata (bcrypt). Città e provincia vengono riconosciute dall’elenco dei comuni di QNet.',
  'business-functions':
    'Nessun prerequisito. Responsabile e operatori non si collegano qui: dopo Utenti esegui “Funzioni aziendali — collega responsabile e operatori”. Al primo import il report segnala gli utenti non ancora trovati: è normale.',
  companies:
    'Nessun prerequisito. L’indirizzo viene creato solo se c’è la via. Città e provincia vengono riconosciute dall’elenco dei comuni di QNet: un nome non trovato resta vuoto con un avviso.',
  'company-sites':
    'Senza Società aziendali la sede resta senza società, con un avviso. Città e provincia vengono riconosciute dall’elenco dei comuni di QNet.',
  'operational-sites':
    'Nessun prerequisito. La via è obbligatoria: senza, la riga fallisce. Città e provincia vengono riconosciute dall’elenco dei comuni di QNet.',
  'business-function-members':
    'Non crea record: collega responsabile e operatori alle funzioni aziendali già importate. Gli operatori vengono solo aggiunti; il responsabile sostituisce quello impostato in QNet, se diverso.',
  'referent-types': 'Nessun prerequisito.',
  sources: 'Nessun prerequisito. Una fonte con lo stesso nome già presente in QNet viene riusata.',
  tags: 'Nessun prerequisito.',
  sectors:
    'Nessun prerequisito. Il settore padre si collega da solo a fine import. I settori già presenti in QNet non vengono riusati: restano accanto a quelli importati, anche con lo stesso nome.',
  'task-templates':
    'Nessun prerequisito. Un titolo già usato riceve il suffisso “(old_id N)”; un titolo che contiene “non attivo” arriva disattivato.',
  'vat-rates':
    'Nessun prerequisito. L’aliquota deve essere un numero maggiore o uguale a zero, altrimenti la riga fallisce.',
  'payment-methods':
    'Nessun prerequisito. Una modalità con lo stesso codice già presente in QNet viene riusata.',
  attributes:
    'Nessun prerequisito. Un attributo con lo stesso codice già presente in QNet viene riusato.',
  'product-categories':
    'Senza Funzioni aziendali la categoria resta senza funzione, con un avviso. La categoria padre si collega da sola a fine import. Ogni categoria legacy viene creata con il prefisso [OLD], anche se in QNet esiste già una categoria con lo stesso nome, e mantiene la sua funzione aziendale, Formazione e APL comprese. Le categorie del ramo [OLD] APL arrivano non selezionabili.',
  'product-category-attributes':
    'Non crea record: collega gli attributi alle categorie già importate. I collegamenti già presenti in QNet non vengono modificati.',
  'attribute-layouts':
    'Un layout che usa un campo non collegato alla categoria fallisce, quindi esegui prima “Categorie prodotto — collega attributi”. Un layout già presente non viene sovrascritto.',
  products:
    'Un prodotto senza categoria nel legacy finisce nella categoria «No categoria», da cui riclassificarlo; una categoria non ancora importata fa fallire la riga. Senza Aliquote IVA l’aliquota resta vuota con un avviso. Il fornitore lo imposta “Prodotti — collega fornitori”.',
  'cost-products':
    'Senza Aliquote IVA l’aliquota resta vuota con un avviso. Crea da sé la categoria Costi e le sue sotto-categorie, senza bisogno di Categorie prodotto. Importa anche i livelli dei dipendenti, usati dai costi dipendenti di offerte e commesse.',
  'email-templates':
    'Nessun prerequisito. Il corpo viene ripulito come nell’editor, perdendo immagini, tabelle e stili; un nome già usato riceve un suffisso.',
  'document-bundles':
    'Nessun prerequisito. I file vengono copiati con i limiti degli allegati di QNet: un file mancante, troppo grande o di formato non ammesso viene saltato con un avviso.',
  referents:
    'Senza Tipi di referente il tipo resta vuoto con un avviso. Nome e cognome sono obbligatori. Città e provincia vengono riconosciute dall’elenco dei comuni di QNet.',
  registries:
    'Senza Utenti, Referenti, Fonti e Settori, responsabili, supervisore, referenti, fonte e settore restano vuoti con un avviso: l’anagrafica viene creata comunque. Il settore viene riconosciuto per nome solo tra i settori importati. Il nome è obbligatorio; le partite IVA segnaposto (0, 1, punto) non vengono importate. Durante l’import non partono notifiche.',
  opportunities:
    'Senza la sua anagrafica importata la riga fallisce: esegui prima Anagrafiche. Referenti, utenti, fonte e sede operativa mancanti restano vuoti con un avviso. Un’opportunità senza cliente (per esempio una richiesta di corso GOL) o con una categoria non importata fallisce con il motivo.',
  quotes:
    'Senza la sua opportunità importata la riga fallisce. Anche una sola riga che non si può importare (senza prodotto, per esempio uno sconto, prodotto non importato, provvigione con destinatario non importato, costo senza prodotto di costo) fa fallire l’intera offerta con il motivo. Aliquote, modalità di pagamento, sedi, referenti e utenti mancanti restano vuoti con un avviso. Un’offerta accettata o contrattualizzata crea anche il contratto. Un fornitore del costo diverso da quello del prodotto e un cliente diverso da quello dell’opportunità generano un avviso.',
  'work-orders':
    'Senza la sua offerta importata la riga fallisce. Una riga d’offerta già collegata a un’altra commessa viene copiata nell’offerta, con le sue provvigioni, e il totale dell’offerta cresce. Arrivano anche i campi flessibili, solo quelli previsti dalle categorie delle righe della commessa (esegui prima Categorie prodotto — collega attributi): un campo non previsto, un valore non valido o un utente o un’anagrafica non importati vengono saltati con un avviso. Arrivano le fasi, aperte o chiuse come nel legacy, senza task. Stato pagamento, accordo di pagamento e insoluti arrivano su ogni riga della commessa. I costi del riquadro Offerta del legacy (note spese e dipendenti) arrivano come costi della commessa, collegati alla riga del loro servizio: un costo senza prodotto di costo importato fa fallire la commessa (esegui prima Prodotti di costo). Una commessa senza righe, o con una riga d’offerta non importata, fallisce con il motivo. Note e documenti non vengono importati; i task arrivano con la sorgente Task.',
  tasks:
    'Richiede già importati Utenti e Commesse (obbligatori); conviene importare prima anche Anagrafiche, Referenti e Opportunità. Arrivano tutti i task legacy, aperti e completati, con sotto-task, assegnatari, osservatori, richiedente, creatore, tipo, categoria, priorità, importanza, stato, date e orari, stima, blocco e feedback di chiusura, la fase della commessa (per nome) e le ricorrenze attive con le occorrenze già generate. Un task di una commessa non importata, o con un utente non importato, fallisce con il motivo. Anagrafica, referente, opportunità o fase non trovati restano vuoti con un avviso. Un sotto-task il cui padre non è importato diventa task principale, con un avviso. Un creatore mancante diventa l’utente che lancia la migrazione. Non parte alcuna notifica. Il flag “Attività privata” non viene importato (tutti i task arrivano non privati); note, documenti e ore del timesheet non vengono importati.',
  'product-suppliers':
    'Non crea record: imposta il fornitore sui prodotti importati. Senza il prodotto la riga fallisce; un fornitore non ancora importato da Anagrafiche viene saltato con un avviso. Un fornitore già scelto in QNet non viene sovrascritto.',
  'commission-configurations':
    'Ogni regola vuole già importati il suo destinatario (anagrafica, referente o utente) e il prodotto o la categoria: se mancano, la riga fallisce. Una regola su una categoria madre, come ISO o GDPR, si importa e vale per tutte le sue sottocategorie. Una regola senza destinatario diventa una regola per tutto il ruolo (del legacy arriva solo la più recente per ruolo e categoria); le regole di destinatari cancellati, senza ruolo o senza valore non vengono importate.',
  'purchase-requests':
    'Richiede già importati Utenti, Società aziendali, Società sedi, Sedi operative, Funzioni aziendali e Commesse (obbligatori); conviene importare prima anche Anagrafiche (cliente e fornitore) e Aliquote IVA. Arrivano tutte le RDA legacy, aperte e chiuse, con righe, fornitore, unità di misura, quantità, prezzo, aliquota, motivo, stato e approvatore della riga e lo storico dei cambi di stato. Importi e totali sono ricalcolati da QNet. Una RDA con un utente, una società, una sede, una funzione o una commessa non importati fallisce con il motivo. Cliente, fornitore, aliquota e unità di misura non trovati restano vuoti con un avviso: per tenere le unità “confezioni” e “metri cubi” crea prima in Unità di misura i codici package e cubic_metre. Una RDA aperta con tutte le righe ricevute, rifiutate o in stand by arriva chiusa. Non parte alcuna notifica. Gli allegati non vengono importati.',
  'purchase-orders':
    'Richiede già importati Utenti, Società aziendali, Società sedi e Anagrafiche (il fornitore è obbligatorio); conviene importare prima anche Sedi operative, Modalità di pagamento, Aliquote IVA, Commesse e Richieste di acquisto (RDA). Arrivano tutti gli ODA legacy, anche quelli già convertiti in fattura passiva, con righe, tipologia di costo, quantità, prezzo, aliquota, commessa e il collegamento alla riga RDA. Importi e totali sono ricalcolati da QNet. Ogni ODA tiene il suo numero legacy; se la società l’ha già usato nello stesso anno, riceve il primo numero libero dopo il più alto, con un avviso e il numero legacy nelle note. Un ODA evaso arriva con un ricevimento di tutte le quantità; uno parzialmente evaso arriva senza ricevimenti, con un avviso. Un ODA con fornitore, società, sede aziendale o creatore non importati fallisce con il motivo. Non parte alcuna notifica. Allegati, dati di fattura e scadenze non vengono importati.',
  'financial-accounts':
    'Conviene importare prima Società aziendali, per collegare ogni conto alla sua società. Arrivano i conti bancari e la cassa del legacy con nome, IBAN, numero conto, indirizzo e note; la società è quella che nel legacy ha lo stesso IBAN tra le banche delle società, altrimenti quella citata nel nome del conto. Un IBAN non valido resta vuoto con un avviso. Un conto già creato a mano in QNet con lo stesso IBAN non viene duplicato: viene collegato al conto legacy. Le carte non vengono importate e i loro dati non lasciano mai il legacy.',
}
