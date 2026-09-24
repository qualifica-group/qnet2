/**
 * Stringhe della campanella notifiche. File satellite per mantenere `it.ts`
 * entro i limiti dimensionali (vedi `.claude/rules/engineering.md` §6).
 */

export const notifications = {
  title: 'Notifiche',
  open: 'Apri notifiche',
  filterLabel: 'Filtra notifiche',
  filters: {
    all: 'Tutte',
    unread: 'Non lette',
    read: 'Lette',
  },
  empty: 'Non hai notifiche.',
  untitled: 'Notifica',
  // Contrassegno su una notifica per conoscenza (spec 0153 D-14: gli
  // osservatori ricevono una copia di una "richiesta di aggiornamento"
  // inviata agli assegnatari).
  cc: 'In copia',
  markAllAsRead: 'Segna tutte come lette',
  markAsRead: 'Segna come letta',
  unreadCount: '{{count}} notifiche non lette',
  loadError: 'Impossibile caricare le notifiche. Riprova.',
  actionError: 'Si è verificato un errore. Riprova.',
  // Link in fondo al pannello della campanella verso la pagina /notifications (spec 0150).
  viewAll: 'Vedi tutte',
  // Aria-label del contatore nel footer della sidebar (spec 0150 D-4/AC-016):
  // porta sempre il numero ESATTO, anche quando l'etichetta visibile è
  // troncata a "99+".
  sidebarUnreadAriaLabel_one: '{{count}} notifica non letta',
  sidebarUnreadAriaLabel_other: '{{count}} notifiche non lette',
  // Etichette di colonna della pagina /notifications, risolte dalle chiavi
  // testuali inviate dal backend (`NotificationColumnCatalog`, spec 0150
  // `data_contract`). `actionUrlOpen` è il solo testo puramente frontend: il
  // contenuto del pulsante "Apri" della colonna Collegamento.
  columns: {
    status: 'Stato',
    title: 'Titolo',
    message: 'Messaggio',
    level: 'Livello',
    createdAt: 'Ricevuta il',
    readAt: 'Letta il',
    actionUrl: 'Collegamento',
    actionUrlOpen: 'Apri',
  },
  // Etichette delle azioni riga, risolte dalle chiavi inviate dal backend
  // (`NotificationColumnCatalog::actions()`).
  actions: {
    markRead: 'Segna come letta',
    markUnread: 'Segna come non letta',
  },
  // Testi propri della pagina /notifications (bottone di testata + azione bulk),
  // non guidati dal backend.
  page: {
    markAllAsRead: 'Segna tutte come lette',
    markSelectedAsRead: 'Segna selezionate come lette',
  },
}
