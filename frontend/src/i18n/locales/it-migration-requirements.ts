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
    'Senza Ruoli, Società aziendali e Sedi operative, ruolo, società e sede restano vuoti con un avviso. Il responsabile di ogni utente si collega da solo a fine import. Email, nome e cognome sono obbligatori. Un utente già presente in QNet viene riconosciuto per email, oppure per nome e cognome se non ha ancora un id legacy: riceve l’id legacy e si completano solo società, sede e responsabile vuoti; ruolo, password e contatti restano quelli di QNet. Se più utenti hanno lo stesso nome, la riga fallisce. Per gli utenti nuovi la password deve arrivare già cifrata (bcrypt). Città e provincia vengono riconosciute dall’elenco dei comuni di QNet.',
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
    'Senza Funzioni aziendali la categoria resta senza funzione, con un avviso. La categoria padre si collega da sola a fine import e una categoria con lo stesso nome già presente in QNet viene riusata. Le categorie del ramo APL legacy e quelle delle funzioni “FORMAZIONE OLD” e “APL OLD” arrivano non selezionabili.',
  'product-category-attributes':
    'Non crea record: collega gli attributi alle categorie già importate. I collegamenti già presenti in QNet non vengono modificati.',
  'attribute-layouts':
    'Un layout che usa un campo non collegato alla categoria fallisce, quindi esegui prima “Categorie prodotto — collega attributi”. Un layout già presente non viene sovrascritto.',
  products:
    'Un prodotto senza categoria nel legacy finisce nella categoria «No categoria», da cui riclassificarlo; una categoria non ancora importata fa fallire la riga. Senza Aliquote IVA l’aliquota resta vuota con un avviso. Il fornitore lo imposta “Prodotti — collega fornitori”.',
  'cost-products':
    'Senza Aliquote IVA l’aliquota resta vuota con un avviso. Crea da sé la categoria Costi e le sue sotto-categorie, senza bisogno di Categorie prodotto.',
  'email-templates':
    'Nessun prerequisito. Il corpo viene ripulito come nell’editor, perdendo immagini, tabelle e stili; un nome già usato riceve un suffisso.',
  'document-bundles':
    'Nessun prerequisito. I file vengono copiati con i limiti degli allegati di QNet: un file mancante, troppo grande o di formato non ammesso viene saltato con un avviso.',
  referents:
    'Senza Tipi di referente il tipo resta vuoto con un avviso. Nome e cognome sono obbligatori. Città e provincia vengono riconosciute dall’elenco dei comuni di QNet.',
  registries:
    'Senza Utenti, Referenti, Fonti e Settori, responsabili, supervisore, referenti, fonte e settore restano vuoti con un avviso: l’anagrafica viene creata comunque. Il settore viene riconosciuto per nome solo tra i settori importati. Il nome è obbligatorio; le partite IVA segnaposto (0, 1, punto) non vengono importate. Durante l’import non partono notifiche.',
  opportunities:
    'Senza la sua anagrafica importata la riga fallisce: esegui prima Anagrafiche. Referenti, utenti, fonte, categoria e sede operativa mancanti restano vuoti con un avviso. Le richieste di corso GOL senza cliente non vengono importate.',
  quotes:
    'Senza la sua opportunità importata la riga fallisce. Una riga senza prodotto importato, ad esempio uno sconto, viene saltata con un avviso. Aliquote, modalità di pagamento, sedi, referenti, utenti e destinatari delle provvigioni mancanti restano vuoti con un avviso. Un’offerta accettata o contrattualizzata crea anche il contratto.',
  'work-orders':
    'Senza la sua offerta importata la riga fallisce. Una riga d’offerta già collegata a un’altra commessa viene copiata nell’offerta, con le sue provvigioni, e il totale dell’offerta cresce. Note, documenti, fasi e task non vengono importati.',
  'work-order-line-payments':
    'Non crea record: copia stato pagamento, accordo di pagamento e insoluti della commessa su ogni sua riga. Senza la commessa importata la riga fallisce. Ogni import riscrive i dati di pagamento delle righe.',
  'product-suppliers':
    'Non crea record: imposta il fornitore sui prodotti importati. Senza il prodotto la riga fallisce; un fornitore non ancora importato da Anagrafiche viene saltato con un avviso. Un fornitore già scelto in QNet non viene sovrascritto.',
  'commission-configurations':
    'Ogni regola vuole già importati il suo destinatario (anagrafica, referente o utente) e il prodotto o la categoria: se mancano, la riga fallisce. Una regola su una categoria madre, come ISO o GDPR, si importa e vale per tutte le sue sottocategorie.',
}
