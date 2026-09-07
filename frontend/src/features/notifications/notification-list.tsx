import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { NotificationItem } from '@/features/notifications/notification-item'
import type { Notification } from '@/features/notifications/types'

interface NotificationListProps {
  notifications: Notification[]
  isLoading: boolean
  isError: boolean
  onRetry: () => void
  onMarkAsRead: (id: string) => void
  /** Id currently being marked, used to disable that single row's action. */
  markingId?: string | null
  /** Whether another page is available for infinite scroll. */
  hasNextPage: boolean
  /** Whether the next page is currently being fetched. */
  isFetchingNextPage: boolean
  /** Requests the next page; called when the bottom sentinel becomes visible. */
  onLoadMore: () => void
  /** Forwarded to every row: dismisses the panel once a row has navigated. */
  onNavigate?: () => void
}

const SKELETON_ROWS = 4

/** Skeleton placeholder shaped like a notification row. */
function NotificationRowSkeleton() {
  return (
    <div className="flex items-start gap-2 px-3 py-2">
      <Skeleton className="mt-1.5 size-2 shrink-0 rounded-full" />
      <div className="min-w-0 flex-1 space-y-2">
        <Skeleton className="h-4 w-[60%]" />
        <Skeleton className="h-3 w-[85%]" />
        <Skeleton className="h-3 w-20" />
      </div>
    </div>
  )
}

/**
 * Scrollable body of the notifications panel. Handles the loading / error /
 * empty triad, then maps items to {@link NotificationItem}.
 */
export function NotificationList({
  notifications,
  isLoading,
  isError,
  onRetry,
  onMarkAsRead,
  markingId,
  hasNextPage,
  isFetchingNextPage,
  onLoadMore,
  onNavigate,
}: NotificationListProps) {
  const { t } = useTranslation()
  const scrollRef = useRef<HTMLDivElement>(null)
  const sentinelRef = useRef<HTMLDivElement>(null)

  // Load the next page when the bottom sentinel scrolls into view. The observer
  // is scoped to the scroll container (root) so it only fires while the user
  // reaches the end of the panel, never on the surrounding page.
  useEffect(() => {
    const sentinel = sentinelRef.current
    const root = scrollRef.current
    if (!sentinel || !root || !hasNextPage) {
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          onLoadMore()
        }
      },
      { root, rootMargin: '0px 0px 80px 0px' },
    )
    observer.observe(sentinel)

    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, onLoadMore, notifications.length])

  if (isLoading) {
    return (
      <div className="max-h-80 overflow-y-auto">
        {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
          <NotificationRowSkeleton key={index} />
        ))}
      </div>
    )
  }

  if (isError) {
    return (
      <div className="flex flex-col items-center gap-2 px-3 py-6 text-center">
        <p className="text-sm text-muted-foreground">
          {t('notifications.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (notifications.length === 0) {
    return (
      <div className="px-3 py-6 text-center text-sm text-muted-foreground">
        {t('notifications.empty')}
      </div>
    )
  }

  return (
    <div ref={scrollRef} className="max-h-80 overflow-y-auto">
      {notifications.map((notification) => (
        <NotificationItem
          key={notification.id}
          notification={notification}
          onMarkAsRead={onMarkAsRead}
          isMarking={markingId === notification.id}
          onNavigate={onNavigate}
        />
      ))}
      {isFetchingNextPage ? <NotificationRowSkeleton /> : null}
      {/* Sentinel observed to trigger loading the next page. */}
      {hasNextPage ? <div ref={sentinelRef} aria-hidden="true" /> : null}
    </div>
  )
}
