import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { ExternalLink } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import { ChatUnreadBadge } from '@/features/work-order-chat/dock/chat-unread-badge'
import { CHAT_DOCK_HEADER_BUTTON_CLASS } from '@/features/work-order-chat/dock/dock-styles'
import { DockWindowFrame } from '@/features/work-order-chat/dock/dock-window-frame'
import { WorkOrderChatPanel } from '@/features/work-order-chat/work-order-chat-panel'
import type { ChatChannel } from '@/features/work-order-chat/types'
import { workOrderPath } from '@/features/work-orders/work-order-type-routing'

interface ChatDockWindowProps {
  channel: ChatChannel
  minimized: boolean
  onToggleMinimized: () => void
  onClose: () => void
}

/** The conversation window of a channel (spec 0432): project avatar, title and client, open the work order. */
export function ChatDockWindow({ channel, minimized, onToggleMinimized, onClose }: ChatDockWindowProps) {
  const { t } = useTranslation()
  const name = channel.title ?? channel.code

  return (
    <DockWindowFrame
      title={name}
      subtitle={channel.registry_name}
      leading={<UserAvatar name={name} size="xs" />}
      minimized={minimized}
      onToggleMinimized={onToggleMinimized}
      onClose={onClose}
      minimizedBadge={<ChatUnreadBadge count={channel.unread_count} />}
      openWidthClass="sm:w-80"
      actions={
        <Button asChild variant="ghost" size="icon-xs" className={CHAT_DOCK_HEADER_BUTTON_CLASS}>
          <Link to={workOrderPath('project', channel.work_order_id)} aria-label={t('workOrders.chat.dock.openWorkOrder')}>
            <ExternalLink aria-hidden="true" />
          </Link>
        </Button>
      }
    >
      <WorkOrderChatPanel workOrderId={channel.work_order_id} canWriteAction={channel.can_write} />
    </DockWindowFrame>
  )
}
