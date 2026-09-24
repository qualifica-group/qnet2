/**
 * Notification bell strings. Sibling file so `en.ts` stays within the
 * engineering size limits (see `.claude/rules/engineering.md` §6).
 */

export const notifications = {
  title: 'Notifications',
  open: 'Open notifications',
  filterLabel: 'Filter notifications',
  filters: {
    all: 'All',
    unread: 'Unread',
    read: 'Read',
  },
  empty: 'You have no notifications.',
  untitled: 'Notification',
  // Marker shown on a courtesy-copy notification (spec 0153 D-14: watchers
  // CC'd on a "request update" sent to the assignees).
  cc: 'CC',
  markAllAsRead: 'Mark all as read',
  markAsRead: 'Mark as read',
  unreadCount: '{{count}} unread notifications',
  loadError: 'Unable to load notifications. Please try again.',
  actionError: 'Something went wrong. Please try again.',
  // Link at the bottom of the bell panel towards the /notifications page (spec 0150).
  viewAll: 'View all',
  // Aria-label of the sidebar footer's badge (spec 0150 D-4/AC-016): always
  // carries the EXACT count, even when the visible label is capped to "99+".
  sidebarUnreadAriaLabel_one: '{{count}} unread notification',
  sidebarUnreadAriaLabel_other: '{{count}} unread notifications',
  // Column labels for the /notifications page, resolved from the text keys the
  // backend sends (`NotificationColumnCatalog`, spec 0150 `data_contract`).
  // `actionUrlOpen` is the only purely-frontend one: the Collegamento column's
  // "Apri" button content.
  columns: {
    status: 'Status',
    title: 'Title',
    message: 'Message',
    level: 'Level',
    createdAt: 'Received at',
    readAt: 'Read at',
    actionUrl: 'Link',
    actionUrlOpen: 'Open',
  },
  // Row action labels, resolved from the keys the backend sends
  // (`NotificationColumnCatalog::actions()`).
  actions: {
    markRead: 'Mark as read',
    markUnread: 'Mark as unread',
  },
  // Text owned by the /notifications page itself (header button + bulk
  // action), not backend-driven.
  page: {
    markAllAsRead: 'Mark all as read',
    markSelectedAsRead: 'Mark selected as read',
  },
}
