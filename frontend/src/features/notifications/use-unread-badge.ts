import { useTranslation } from 'react-i18next'
import { useUnreadSummary } from '@/features/notifications/use-notifications'

/** Above this, the sidebar footer's badge caps to "99+" instead of the exact count. */
export const SIDEBAR_UNREAD_BADGE_MAX = 99

/**
 * Caps a raw unread count into the sidebar badge's compact display text
 * (spec 0150 D-4/AC-016). Pure and separately testable from the hook below,
 * which wires it to the polled summary.
 */
export function formatUnreadBadgeCount(count: number): string {
  return count > SIDEBAR_UNREAD_BADGE_MAX ? `${SIDEBAR_UNREAD_BADGE_MAX}+` : String(count)
}

export interface UnreadBadge {
  /** Raw unread count (0 while loading/none). */
  count: number
  /** Compact display text ("7", "99+"), or `null` when there is nothing to show (count is 0). */
  label: string | null
  /** Accessible label carrying the EXACT count, e.g. "7 unread notifications" — never the capped label. */
  ariaLabel: string
}

/**
 * Formats the sidebar footer's "Notifiche" unread badge off the SAME polled
 * summary the bell already reads (`useUnreadSummary`, `use-notifications.ts`)
 * — no extra query, just a different presentation of the one count both
 * surfaces share.
 */
export function useUnreadBadge(): UnreadBadge {
  const { t } = useTranslation()
  const { data } = useUnreadSummary()
  const count = data?.count ?? 0

  return {
    count,
    label: count > 0 ? formatUnreadBadgeCount(count) : null,
    ariaLabel: t('notifications.sidebarUnreadAriaLabel', { count }),
  }
}
