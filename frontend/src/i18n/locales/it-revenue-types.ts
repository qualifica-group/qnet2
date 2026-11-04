/** Classificazione dei ricavi (spec 0350): selettore sottoconto condiviso, campo prodotto/categoria, riga fattura, azione massiva. */

export const revenueTypes = {
  unclassified: 'Da classificare',
  select: {
    label: 'Sottoconto ricavo',
    placeholder: 'Seleziona un sottoconto…',
    search: 'Cerca per codice o nome…',
    empty: 'Nessun sottoconto trovato.',
    error: 'Impossibile caricare i sottoconti.',
    clear: 'Svuota il sottoconto',
  },
  effective: {
    none: 'Nessun sottoconto impostato: le righe di fattura restano da classificare.',
    inherited: 'Ereditato da categoria {{category}}: {{revenueType}}',
  },
  field: {
    label: 'Sottoconto ricavo',
    productHint: 'Proposto sulle righe di fattura di questo prodotto.',
  },
  bulk: {
    bulkAction: 'Imposta sottoconto ricavo',
    title: 'Imposta sottoconto ricavo',
    description: 'Il sottoconto di ricavo scelto sarà impostato su tutti i {{count}} prodotti selezionati.',
    selected: 'Prodotti selezionati',
    none: 'Nessuno (rimuovi il sottoconto)',
    confirm: 'Applica a {{count}} prodotti',
    done: 'Sottoconto ricavo aggiornato su {{count}} prodotto/i.',
    error: 'Impossibile impostare il sottoconto ricavo. Nessun prodotto è stato modificato.',
  },
  invoiceLine: {
    automatic: 'Automatico (proposto al salvataggio)',
    column: 'Sottoconto ricavo',
    change: 'Cambia il sottoconto ricavo della riga {{line}}',
    saved: 'Sottoconto ricavo aggiornato.',
    error: 'Impossibile aggiornare il sottoconto ricavo. Riprova.',
    hint: 'Dato solo gestionale: non compare sul documento.',
  },
  commissionDefault: {
    label: 'Predefinito per provvigioni Ente',
    hint: 'Le righe di fattura delle richieste di provvigione Ente ricevono questo sottoconto. Solo un sottoconto può essere il predefinito.',
    badge: 'Provvigioni Ente',
  },
}
