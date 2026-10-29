/** Catalogo Stati righe RDA (spec 0248). Namespace radice: le etichette colonna del backend sono `purchaseRequestLineStatuses.columns.*`. */

export const purchaseRequestLineStatuses = {
  forbidden: 'Non hai i permessi per visualizzare gli stati delle righe RDA.',
  columns: {
    name: 'Nome',
    system_key: 'Chiave di sistema',
    color: 'Colore',
    icon: 'Icona',
    sort_order: 'Ordine',
  },
  detail: {
    title: 'Dettaglio stato riga RDA',
    subtitle: 'Dettaglio del record selezionato, modificabile sul posto.',
    loadError: 'Impossibile caricare lo stato della riga RDA. Riprova.',
    system_key: 'Chiave di sistema',
    sort_order: 'Ordine',
  },
  form: {
    name: 'Nome',
    color: 'Colore',
    icon: 'Icona',
    updated: 'Stato riga RDA aggiornato correttamente.',
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
    title: 'Riordina stati righe RDA',
    subtitle: 'Trascina le righe per cambiarne l’ordine. L’ordine si riflette su schede di stato, finestra di cambio stato e menu.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare l’elenco. Riprova.',
    saved: 'Ordine aggiornato correttamente.',
    genericError: 'Impossibile aggiornare l’ordine. Riprova.',
    inactiveBadge: 'Disattivato',
  },
}
