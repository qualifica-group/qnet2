/**
 * Le colonne e i filtri avanzati della tabella sono backend-driven: il server
 * invia la CHIAVE i18n (`commissionConfigurations.columns.*` /
 * `commissionConfigurations.advancedFilters.*`, vedi
 * `CommissionConfigurationColumnCatalog`), il frontend la traduce — quindi le
 * chiavi sotto sono obbligatorie e devono combaciare con gli id di colonna.
 */
export const commissionConfigurations = {
  forbidden: 'Non hai il permesso di visualizzare le configurazioni commissioni.',
  columns: {
    name: 'Nome configurazione', recipient_role: 'Ruolo destinatario', application_scope: 'Ambito',
    category: 'Categoria prodotto', product: 'Prodotto', commission_type: 'Tipologia',
    value: 'Valore', priority: 'Priorità', status: 'Stato', updated_at: 'Ultima modifica',
    supplier_commission_direction: 'Verso commissione', recipient: 'Destinatario',
  },
  advancedFilters: { name: 'Nome configurazione', validFrom: 'Data inizio validità', validUntil: 'Data fine validità' },
  detail: { recipientRow: 'Destinatario', validityRow: 'Validità', validityRange: 'Dal {{from}} al {{until}}', validityOpenEnded: 'Dal {{from}}, senza scadenza', title: 'Configurazione commissione', loadError: 'Impossibile caricare la configurazione commissione.', scope: 'Destinatario e ambito', calculation: 'Calcolo', validity: 'Validità e note', updated_at: 'Ultima modifica' },
  form: {
    new: 'Nuova configurazione', save: 'Salva', saving: 'Salvataggio…',
    created: 'Configurazione commissione creata.', updated: 'Configurazione commissione aggiornata.', deleted: 'Configurazione commissione eliminata.',
    genericError: 'Impossibile salvare la configurazione commissione.', deleteError: 'Impossibile eliminare la configurazione commissione.',
    deleteReferenced: 'La configurazione è utilizzata da un altro modulo e non può essere eliminata.',
    name: 'Nome configurazione', recipient_role: 'Ruolo destinatario', application_scope: 'Ambito di applicazione',
    product_category_id: 'Categoria prodotto', product_id: 'Prodotto', recipient_type: 'Tipo destinatario', recipient_id: 'Destinatario', supplier_commission_direction: 'Verso commissione Fornitore', commission_type: 'Tipologia commissione',
    value: 'Valore commissione', priority: 'Priorità regola', valid_from: 'Data inizio validità', valid_until: 'Data fine validità',
    status: 'Stato', internal_note: 'Nota di servizio interna', searchCategory: 'Cerca categorie prodotto…', categoryBranchHint: 'Vale anche per tutte le sottocategorie; se più regole coprono il prodotto vince la categoria più vicina.',
    searchProduct: 'Cerca prodotti…', searchRecipient: 'Cerca destinatario…', selectPlaceholder: 'Seleziona…', selectEmpty: 'Nessun risultato.', selectError: 'Impossibile caricare le opzioni.',
    createSubtitle: 'Aggiungi una nuova configurazione commissione.',
    leaveConfirm: { title: 'Uscire senza salvare?', description: 'La configurazione commissione non è ancora stata creata: i dati inseriti andranno persi.', confirm: 'Esci senza salvare', cancel: 'Continua a compilare' },
    hints: { supplierCommissionDirection: 'Ricevuta: la commissione è incassata da noi ed è il ricavo della riga. Pagata: è un costo verso il fornitore.', priority: 'Decide quale regola vince quando più configurazioni valide competono per lo stesso ruolo e lo stesso ambito: vince il numero più alto, poi la data di inizio più recente. Una regola su Prodotto batte comunque sempre una regola su Categoria.' },
    errors: { directionRequired: 'Il verso è obbligatorio per una regola Fornitore.', nameRequired: 'Il nome configurazione è obbligatorio.', valueInvalid: 'Il valore deve essere maggiore o uguale a zero.', priorityInvalid: 'La priorità deve essere un numero intero.', validFromRequired: 'La data iniziale è obbligatoria.', categoryRequired: 'Seleziona una categoria prodotto.', productRequired: 'Seleziona un prodotto.', recipientRequired: 'Seleziona un destinatario.', validUntilInvalid: 'La data finale non può precedere quella iniziale.', recipientTypeInvalid: 'Questo tipo di destinatario non è ammesso per il ruolo selezionato.' },
  },
  propagation: {
    title: 'Aggiornare anche le offerte esistenti?',
    description: 'La regola è già salvata. Scegli se applicarla anche alle offerte non chiuse che la usano.',
    summary: '{{quotes}} offerte non chiuse, {{lines}} righe, di cui {{manual}} commissioni modificate a mano.',
    choiceLabel: 'Come applicare la regola alle offerte esistenti',
    none: "Solo le nuove righe d'offerta",
    noneHint: 'Le offerte esistenti restano come sono.',
    keepManual_one: 'Aggiorna solo le commissioni da regola, tieni la {{count}} manuale',
    keepManual_other: 'Aggiorna solo le commissioni da regola, tieni le {{count}} manuali',
    regenerateAll_one: 'Rigenera tutte dalle regole, anche la {{count}} manuale',
    regenerateAll_other: 'Rigenera tutte dalle regole, anche le {{count}} manuali',
    confirm: 'Conferma',
    started: "Ricalcolo avviato: riceverai una notifica al termine.",
    error: 'Impossibile avviare il ricalcolo delle offerte.',
    impactError: "Regola salvata, ma non è stato possibile verificare l'impatto sulle offerte.",
  },
  options: {
    recipient_role: { COMMERCIAL: 'Commerciale', REPORTER: 'Segnalatore', SUPERVISOR: 'Supervisore', SUPPLIER: 'Fornitore' },
    application_scope: { PRODUCT_CATEGORY: 'Categoria prodotto', PRODUCT: 'Prodotto', RECIPIENT: 'Destinatario specifico' },
    commission_type: { FIXED_AMOUNT: 'Importo fisso', PERCENTAGE: 'Percentuale' },
    status: { ACTIVE: 'Attiva', SUSPENDED: 'Sospesa' },
    supplier_commission_direction: { RECEIVED: 'Ricevuta', PAID: 'Pagata' },
    recipient_type: { referent: 'Referente', user: 'Utente', registry: 'Anagrafica' },
  },
}
