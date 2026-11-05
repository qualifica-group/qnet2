import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'

/** Unread chat messages of a project work order (`chat_unread_count`, spec 0354): a badge only when above zero. */
export function ChatUnreadCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const count = typeof value === 'number' ? value : 0

  return (
    <div className="flex h-full items-center px-2">
      {count > 0 ? (
        <Badge aria-label={t('workOrders.chatUnread', { count })}>{count}</Badge>
      ) : null}
    </div>
  )
}
