import { useCallback, useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Bell, CheckCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Separator } from '@/components/ui/separator'
import { NotificationList } from '@/features/notifications/notification-list'
import { useNotificationActions } from '@/features/notifications/use-notification-actions'
import {
  useNotificationList,
  useUnreadCount,
} from '@/features/notifications/use-notifications'
import type { NotificationFilter } from '@/features/notifications/types'
import { cn } from '@/lib/utils'

const MAX_BADGE_COUNT = 9
const FILTER_OPTIONS: readonly NotificationFilter[] = ['all', 'unread', 'read']

/** Caps the displayed unread count (e.g. "9+") to keep the badge compact. */
function formatBadgeCount(count: number): string {
  return count > MAX_BADGE_COUNT ? `${MAX_BADGE_COUNT}+` : String(count)
}

/**
 * Reusable, domain-agnostic notifications entry point. A consumer just renders
 * `<NotificationBell />`. The list only fetches while the panel is open; the
 * unread count polls in the background.
 */
export function NotificationBell() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [selectedFilter, setSelectedFilter] = useState<NotificationFilter>('all')

  const unreadCountQuery = useUnreadCount()
  const serverFilter = selectedFilter === 'read' ? 'all' : selectedFilter
  const listQuery = useNotificationList(open, serverFilter)
  const { markAsRead, markAllAsRead } = useNotificationActions()

  const unreadCount = unreadCountQuery.data ?? 0
  // Flatten the loaded infinite-scroll pages into a single list.
  const notifications = useMemo(
    () => listQuery.data?.pages.flatMap((page) => page.items) ?? [],
    [listQuery.data],
  )
  const visibleNotifications = useMemo(() => {
    if (selectedFilter === 'read') {
      return notifications.filter((notification) => notification.read_at !== null)
    }

    return notifications
  }, [notifications, selectedFilter])
  const isResolvingReadFilter =
    selectedFilter === 'read' &&
    visibleNotifications.length === 0 &&
    !listQuery.isError &&
    (listQuery.isPending || listQuery.isFetchingNextPage || listQuery.hasNextPage)

  const { fetchNextPage } = listQuery
  const loadMore = useCallback(() => {
    void fetchNextPage()
  }, [fetchNextPage])

  // Radix keeps this menu open on a click that is not a DropdownMenuItem, and
  // the menu is modal: it would sit on top of the page the row just navigated
  // to, with the body still inert.
  const closePanel = useCallback(() => setOpen(false), [])

  // The backend currently exposes only `all` and `unread`. While the user is
  // viewing `read`, keep fetching `all` pages until at least one read row is
  // visible or the dataset is exhausted.
  useEffect(() => {
    if (
      !open ||
      selectedFilter !== 'read' ||
      listQuery.isPending ||
      listQuery.isError ||
      listQuery.isFetchingNextPage ||
      !listQuery.hasNextPage ||
      visibleNotifications.length > 0
    ) {
      return
    }

    void fetchNextPage()
  }, [
    fetchNextPage,
    listQuery.hasNextPage,
    listQuery.isError,
    listQuery.isFetchingNextPage,
    listQuery.isPending,
    open,
    selectedFilter,
    visibleNotifications.length,
  ])

  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative size-7 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground [&_svg]:size-3.5"
          aria-label={t('notifications.open')}
        >
          <Bell />
          {unreadCount > 0 ? (
            <Badge
              variant="destructive"
              className="absolute -right-1 -top-1 h-5 min-w-5 justify-center rounded-full px-1 text-xs tabular-nums"
              aria-label={t('notifications.unreadCount', { count: unreadCount })}
            >
              {formatBadgeCount(unreadCount)}
            </Badge>
          ) : null}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80 p-0">
        <div className="flex items-center justify-between gap-2 px-3 py-2">
          <span className="text-sm font-semibold">
            {t('notifications.title')}
          </span>
          <Button
            variant="ghost"
            size="sm"
            disabled={unreadCount === 0 || markAllAsRead.isPending}
            onClick={() => markAllAsRead.mutate()}
          >
            <CheckCheck />
            {t('notifications.markAllAsRead')}
          </Button>
        </div>
        <div className="px-3 pb-2">
          <div
            className="grid grid-cols-3 gap-1 rounded-md bg-muted p-1"
            role="group"
            aria-label={t('notifications.filterLabel')}
          >
            {FILTER_OPTIONS.map((filter) => (
              <Button
                key={filter}
                type="button"
                size="sm"
                variant="ghost"
                className={cn(
                  'h-8 px-2 text-xs',
                  selectedFilter === filter && 'bg-background shadow-sm',
                )}
                aria-pressed={selectedFilter === filter}
                onClick={() => setSelectedFilter(filter)}
              >
                {t(`notifications.filters.${filter}`)}
              </Button>
            ))}
          </div>
        </div>
        <Separator />
        <NotificationList
          notifications={visibleNotifications}
          isLoading={isResolvingReadFilter || listQuery.isPending}
          isError={listQuery.isError}
          onRetry={() => listQuery.refetch()}
          onMarkAsRead={(id) => markAsRead.mutate(id)}
          markingId={markAsRead.isPending ? markAsRead.variables : null}
          hasNextPage={listQuery.hasNextPage}
          isFetchingNextPage={listQuery.isFetchingNextPage}
          onLoadMore={loadMore}
          onNavigate={closePanel}
        />
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
