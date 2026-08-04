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
}

/**
 * Renders a single notification row. Domain-agnostic: it only reads the generic
 * `data` payload with fallbacks. Shows an unread dot and bold title while
 * unread, plus a "mark as read" affordance that appears only when unread.
 * When `data.action_url` resolves to a safe internal path, the row's content
 * becomes a keyboard-reachable button that navigates there and, if the
 * notification was unread, marks it as read in the same click (AC-027).
 */
export function NotificationItem({
  notification,
  onMarkAsRead,
  isMarking = false,
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
  }

  return (
    <div className="flex items-start gap-2 px-3 py-2">
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
          className="min-w-0 flex-1 rounded-sm text-left outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
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
