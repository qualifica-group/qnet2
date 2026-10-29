/** Catalogo Stati ODA (spec 0248). Namespace radice: le etichette colonna del backend sono `purchaseOrderStatuses.columns.*`. */

export const purchaseOrderStatuses = {
  forbidden: 'Non hai i permessi per visualizzare gli stati ODA.',
  columns: {
    system_key: 'Valore di sistema',
    name: 'Nome',
    color: 'Colore',
    icon: 'Icona',
    sort_order: 'Ordine',
  },
  detail: {
    loadError: 'Impossibile caricare lo stato ODA. Riprova.',
    systemKey: 'Valore di sistema',
    sort_order: 'Ordine',
  },
  form: {
    name: 'Nome',
    color: 'Colore',
    icon: 'Icona',
    updated: 'Stato ODA aggiornato correttamente.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    colorRequired: 'Il colore è obbligatorio.',
    colorInvalid: 'Scegli un colore dalla palette.',
    iconInvalid: 'Scegli un’icona dal catalogo.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    sections: {
      identity: {
        title: 'Dettagli',
      },
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati ODA',
    subtitle: 'Trascina le righe per cambiarne l’ordine. L’ordine si riflette su schede, tabella e finestra di cambio stato.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare l’elenco. Riprova.',
    saved: 'Ordine aggiornato correttamente.',
    genericError: 'Impossibile aggiornare l’ordine. Riprova.',
  },
}
