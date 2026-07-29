export const commissionConfigurations = {
  forbidden: 'Non hai il permesso di visualizzare le configurazioni commissioni.',
  detail: { title: 'Configurazione commissione', loadError: 'Impossibile caricare la configurazione commissione.', scope: 'Destinatario e ambito', calculation: 'Calcolo', validity: 'Validità e note', updated_at: 'Ultima modifica' },
  form: {
    new: 'Nuova configurazione', save: 'Salva', saving: 'Salvataggio…',
    created: 'Configurazione commissione creata.', updated: 'Configurazione commissione aggiornata.', deleted: 'Configurazione commissione eliminata.',
    genericError: 'Impossibile salvare la configurazione commissione.', deleteError: 'Impossibile eliminare la configurazione commissione.',
    deleteReferenced: 'La configurazione è utilizzata da un altro modulo e non può essere eliminata.',
    name: 'Nome configurazione', recipient_role: 'Ruolo destinatario', application_scope: 'Ambito di applicazione',
    product_category_id: 'Categoria prodotto', product_id: 'Prodotto', commission_type: 'Tipologia commissione',
    value: 'Valore commissione', priority: 'Priorità regola', valid_from: 'Data inizio validità', valid_until: 'Data fine validità',
    status: 'Stato', internal_note: 'Nota di servizio interna', searchCategory: 'Cerca categorie prodotto…',
    searchProduct: 'Cerca prodotti…', selectPlaceholder: 'Seleziona…', selectEmpty: 'Nessun risultato.', selectError: 'Impossibile caricare le opzioni.',
    sections: { scope: 'Identità e ambito', calculation: 'Calcolo', validity: 'Validità', notes: 'Nota interna' },
    errors: { nameRequired: 'Il nome configurazione è obbligatorio.', valueInvalid: 'Il valore deve essere maggiore o uguale a zero.', priorityInvalid: 'La priorità deve essere un numero intero.', validFromRequired: 'La data iniziale è obbligatoria.', categoryRequired: 'Seleziona una categoria prodotto.', productRequired: 'Seleziona un prodotto.', validUntilInvalid: 'La data finale non può precedere quella iniziale.' },
  },
  options: {
    recipient_role: { COMMERCIAL: 'Commerciale', REPORTER: 'Segnalatore', SUPERVISOR: 'Supervisore', SUPPLIER: 'Fornitore' },
    application_scope: { PRODUCT_CATEGORY: 'Categoria prodotto', PRODUCT: 'Prodotto' },
    commission_type: { FIXED_AMOUNT: 'Importo fisso', PERCENTAGE: 'Percentuale' },
    status: { ACTIVE: 'Attiva', SUSPENDED: 'Sospesa' },
  },
}
