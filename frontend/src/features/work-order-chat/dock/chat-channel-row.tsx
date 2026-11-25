import { useTranslation } from 'react-i18next'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import { formatChatListTime } from '@/features/work-order-chat/chat-timeline'
import { ChatUnreadBadge } from '@/features/work-order-chat/dock/chat-unread-badge'
import type { ChatChannel } from '@/features/work-order-chat/types'

interface ChatChannelRowProps {
  channel: ChatChannel
  now: number
  onOpen: (workOrderId: number) => void
}

/** One channel of the list, like a CRM inbox row: project avatar, title and time, client, last message and unread. */
export function ChatChannelRow({ channel, now, onOpen }: ChatChannelRowProps) {
  const { t, i18n } = useTranslation()
  const { last_message: last } = channel
  const name = channel.title ?? channel.code
  const hasUnread = channel.unread_count > 0
  const preview = !last
    ? t('workOrders.chat.dock.noMessages')
    : last.is_deleted
      ? t('workOrders.chat.deletedMessage')
      : t('workOrders.chat.dock.lastMessage', {
          author: last.author_name ?? t('workOrders.chat.unknownAuthor'),
          body: last.body_preview ?? '',
        })

  return (
    <button
      type="button"
      onClick={() => onOpen(channel.work_order_id)}
      className="flex w-full min-w-0 items-center gap-2.5 px-3 py-2 text-left outline-none hover:bg-muted/40 focus-visible:bg-muted/40"
    >
      <span aria-hidden="true" className="flex shrink-0">
        <UserAvatar name={name} size="default" />
      </span>
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="flex min-w-0 items-baseline gap-2">
          <span className={cn('min-w-0 flex-1 truncate text-xs', hasUnread ? 'font-semibold' : 'font-medium')}>{name}</span>
          {last ? (
            <time dateTime={last.created_at} className={cn('shrink-0 text-[0.65rem]', hasUnread ? 'font-semibold text-primary' : 'text-muted-foreground')}>
              {formatChatListTime(last.created_at, now, i18n.language, {
                today: t('workOrders.chat.today'),
                yesterday: t('workOrders.chat.yesterday'),
              })}
            </time>
          ) : null}
        </span>
        {channel.registry_name ? (
          <span className="truncate text-[0.7rem] text-muted-foreground">{channel.registry_name}</span>
        ) : null}
        <span className="flex min-w-0 items-center gap-2">
          <span
            className={cn(
              'min-w-0 flex-1 truncate text-[0.7rem]',
              hasUnread ? 'font-medium text-foreground' : 'text-muted-foreground',
              last?.is_deleted && 'italic',
            )}
          >
            {preview}
          </span>
          <ChatUnreadBadge count={channel.unread_count} />
        </span>
      </span>
    </button>
  )
}
