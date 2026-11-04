import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { useChatChannel } from '@/features/work-order-chat/use-chat-channel'
import { useWorkOrderChat } from '@/features/work-order-chat/use-work-order-chat'

/**
 * Trigger content of the "Chat" tab: label plus the unread badge. It is always
 * mounted while the tab exists, so it loads the thread (shared cache with the
 * panel) and owns the realtime subscription: the badge stays live on other tabs.
 */
export function WorkOrderChatTabLabel({ workOrderId }: { workOrderId: number }) {
  const { t } = useTranslation()
  const { data } = useWorkOrderChat(workOrderId)
  useChatChannel(workOrderId, data?.channel ?? null)
  const unread = data?.unread_count ?? 0

  return (
    <>
      {t('workOrders.chat.tab')}
      {unread > 0 ? (
        <Badge variant="destructive" className="px-1.5 py-0" aria-label={t('workOrders.chat.unreadBadge', { count: unread })}>
          {unread}
        </Badge>
      ) : null}
    </>
  )
}
