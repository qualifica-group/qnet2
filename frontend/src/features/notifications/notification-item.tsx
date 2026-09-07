import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Check } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { safeInternalPath } from '@/features/notifications/safe-internal-path'
import type { Notification } from '@/features/notifications/types'
import { formatDateTime } from '@/lib/formatting/date-display'

interface NotificationItemProps {
  notification: Notification
  /** Invoked when the user marks this notification as read. */
  onMarkAsRead: (id: string) => void
  /** Disables the per-item action while a mutation is in flight. */
  isMarking?: boolean
  /**
   * Called right after a successful navigation, so the host can dismiss the
   * surface this row lives in. Without it the bell's Radix menu — modal, so
   * it holds `pointer-events: none` on the body — stays open on top of the
   * page it just navigated to, and the click reads as "nothing happened".
   */
  onNavigate?: () => void
}

/**
 * Renders a single notification row. Domain-agnostic: it only reads the generic
 * `data` payload with fallbacks. Shows an unread dot and bold title while
 * unread, plus a "mark as read" affordance that appears only when unread.
 * When `data.action_url` resolves to a safe internal path, the row's content
 * becomes a keyboard-reachable button that navigates there and, if the
 * notification was unread, marks it as read in the same click (AC-027).
 * `cursor-pointer` is explicit: Tailwind 4 dropped the pointer cursor from
 * its button reset and this app declares none globally, so without it the
 * only clickable element of the row looks inert.
 *
 * The hover tint sits on the ROW, not on the inner button, so the whole strip
 * reacts as one item. `--muted`, not `--accent`: the message and the timestamp
 * keep `text-muted-foreground` (they do not inherit an `accent-foreground`),
 * and that ink measures 4.54:1 on the muted tint but only 4.17:1 light /
 * 2.71:1 dark on the accent one — below AA. `--muted` is also the token
 * `ui-design.md` §1-bis names for row hover; `--accent` is the outline
 * Button's. Unconditional: it reads the row under the cursor in a dense list,
 * while the pointer cursor stays the signal of what is actually clickable.
 */
export function NotificationItem({
  notification,
  onMarkAsRead,
  isMarking = false,
  onNavigate,
}: NotificationItemProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const isUnread = notification.read_at === null

  const title = notification.data.title ?? t('notifications.untitled')
  const message = notification.data.message
  const timestamp = formatDateTime(notification.created_at)
  const targetPath = safeInternalPath(notification.data.action_url)

  const content = (
    <>
      <p
        className={cn(
          'truncate text-sm',
          isUnread ? 'font-semibold' : 'font-normal',
        )}
      >
        {title}
      </p>
      {message ? (
        <p className="mt-0.5 text-sm text-muted-foreground">{message}</p>
      ) : null}
      {timestamp ? (
        <p className="mt-1 text-xs text-muted-foreground">{timestamp}</p>
      ) : null}
    </>
  )

  const handleOpen = () => {
    if (!targetPath) {
      return
    }
    if (isUnread) {
      onMarkAsRead(notification.id)
    }
    navigate(targetPath)
    onNavigate?.()
  }

  return (
    <div className="flex items-start gap-2 px-3 py-2 transition-colors hover:bg-muted">
      <span
        aria-hidden="true"
        className={cn(
          'mt-1.5 size-2 shrink-0 rounded-full',
          isUnread ? 'bg-primary' : 'bg-transparent',
        )}
      />
      {targetPath ? (
        <button
          type="button"
          onClick={handleOpen}
          className="min-w-0 flex-1 cursor-pointer rounded-sm text-left outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          {content}
        </button>
      ) : (
        <div className="min-w-0 flex-1">{content}</div>
      )}
      {isUnread ? (
        <Button
          variant="ghost"
          size="icon-sm"
          // Lifts BACK to the panel surface instead of down to a tint: the row
          // it sits on is already `bg-muted` while hovered, and the ghost
          // variant's own `hover:bg-accent` is 76 against that 79 in light —
          // invisible. `--popover` separates in both themes (100 / 23).
          className="hover:bg-popover"
          aria-label={t('notifications.markAsRead')}
          disabled={isMarking}
          onClick={() => onMarkAsRead(notification.id)}
        >
          <Check />
        </Button>
      ) : null}
    </div>
  )
}
