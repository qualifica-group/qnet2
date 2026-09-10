import type { NotificationFilter } from '@/features/notifications/types'

export const notificationKeys = {
  all: ['notifications'] as const,
  list: (filter: NotificationFilter) => ['notifications', 'list', filter] as const,
  unreadSummary: ['notifications', 'unread-summary'] as const,
}
