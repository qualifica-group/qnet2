import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'

/** The red unread counter of the dock bar and of a channel; nothing at zero. */
export function ChatUnreadBadge({ count }: { count: number }) {
  const { t } = useTranslation()

  return count > 0 ? (
    <Badge
      variant="destructive"
      className="h-5 min-w-5 shrink-0 justify-center rounded-full px-1 text-xs tabular-nums"
      aria-label={t('workOrders.chat.unreadBadge', { count })}
    >
      {count}
    </Badge>
  ) : null
}
