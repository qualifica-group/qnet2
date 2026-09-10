import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { env } from '@/config/env'
import { fetchNotifications, fetchUnreadSummary } from '@/features/notifications/api'
import { notificationKeys } from '@/features/notifications/query-keys'
import type { NotificationFilter } from '@/features/notifications/types'
import { useAuth } from '@/features/auth/use-auth'

/** Page size requested per infinite-scroll fetch. */
export const NOTIFICATIONS_PAGE_SIZE = 15

/**
 * Lightweight, always-on poll for the unread summary: the badge count plus the
 * most recent unread notification. Only runs while the user is authenticated.
 *
 * Unlike the panel list, this one KEEPS polling while the tab sits in the
 * background: that is exactly when the browser tab title announces the
 * notification, and a poll that stopped on blur would leave the title stale
 * until the user came back — where the announcement is no longer needed.
 */
export function useUnreadSummary() {
  const { isAuthenticated } = useAuth()

  return useQuery({
    queryKey: notificationKeys.unreadSummary,
    queryFn: fetchUnreadSummary,
    enabled: isAuthenticated,
    refetchInterval: isAuthenticated ? env.notificationsPollInterval : false,
    refetchIntervalInBackground: true,
  })
}

/**
 * Infinite-scroll list for the panel. Only enabled while the panel is open (and
 * the user authenticated) to avoid useless calls; keeps polling while open so
 * the loaded pages stay fresh. The next page offset is derived from the backend
 * pagination envelope; `getNextPageParam` returns undefined once every row is
 * loaded, which sets `hasNextPage` to false.
 */
export function useNotificationList(open: boolean, filter: NotificationFilter) {
  const { isAuthenticated } = useAuth()

  return useInfiniteQuery({
    queryKey: notificationKeys.list(filter),
    queryFn: ({ pageParam }) =>
      fetchNotifications({
        filter,
        offset: pageParam,
        limit: NOTIFICATIONS_PAGE_SIZE,
      }),
    initialPageParam: 0,
    getNextPageParam: (lastPage) => {
      const { offset, limit, total } = lastPage.pagination
      const nextOffset = offset + limit
      return nextOffset < total ? nextOffset : undefined
    },
    enabled: isAuthenticated && open,
    refetchInterval: open ? env.notificationsPollInterval : false,
    refetchIntervalInBackground: false,
  })
}
