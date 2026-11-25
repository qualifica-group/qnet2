import { useTranslation } from 'react-i18next'
import { MessageSquare } from 'lucide-react'
import { RecordTabCount, RecordTabIcon } from '@/components/detail/record-tab-icon'
import { TABS_LIST_CLASS, TABS_TRIGGER_CLASS } from '@/components/ui/tabs'
import { cn } from '@/lib/utils'
import { useChatDock } from '@/features/work-order-chat/dock/chat-dock-context'
import { useChatChannels } from '@/features/work-order-chat/use-work-order-chat'
import type { ChatChannel } from '@/features/work-order-chat/types'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** The work order as a channel when the dock's list does not hold it (a super-admin outside the team). */
function channelOf(workOrder: WorkOrderDetailWithPermissions): ChatChannel {
  return {
    work_order_id: workOrder.id,
    code: workOrder.code,
    title: workOrder.title,
    registry_name: workOrder.registry?.name ?? null,
    unread_count: 0,
    can_write: workOrder.permissions.actions.write_chat === true,
    channel: '',
    last_message: null,
  }
}

/**
 * "Chat" in the work order's tab strip (user directive 2026-11-25), with the
 * unread dot of the Note tab: not a panel of its own, it opens the
 * conversation in the chat dock (spec 0432). Borrows the strip's classes, like
 * the title "tab". Shown only with `view_chat` (none when CHAT_ENABLED is off).
 */
export function WorkOrderChatTabButton({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()
  const dock = useChatDock()
  // Same query as the dock: the dot follows its realtime.
  const listed = useChatChannels().data?.find((channel) => channel.work_order_id === workOrder.id)

  if (!dock) {
    return null
  }

  return (
    <div className={cn(TABS_LIST_CLASS, 'w-auto')}>
      <button type="button" className={TABS_TRIGGER_CLASS} onClick={() => dock.openChannel(listed ?? channelOf(workOrder))}>
        <RecordTabIcon
          icon={<MessageSquare className="size-3.5" aria-hidden="true" />}
          badge={<RecordTabCount count={listed?.unread_count} />}
        />
        {t('workOrders.chat.tab')}
      </button>
    </div>
  )
}
