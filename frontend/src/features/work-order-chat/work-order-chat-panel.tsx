import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { MessagesSquare } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/features/auth/use-auth'
import { canManageMessage } from '@/features/work-order-chat/chat-cache'
import { buildChatTimeline, formatChatDay } from '@/features/work-order-chat/chat-timeline'
import { useChatMarkRead } from '@/features/work-order-chat/use-chat-mark-read'
import { useNow } from '@/features/work-order-chat/use-now'
import {
  useDeleteChatMessage,
  useEditChatMessage,
  useLoadOlderChatMessages,
  useSendChatMessage,
  useWorkOrderChat,
} from '@/features/work-order-chat/use-work-order-chat'
import { WorkOrderChatComposer } from '@/features/work-order-chat/work-order-chat-composer'
import { WorkOrderChatMessage } from '@/features/work-order-chat/work-order-chat-message'
import { CHAT_DOCK_SOFT_BUTTON_CLASS } from '@/features/work-order-chat/dock/dock-styles'

interface WorkOrderChatPanelProps {
  workOrderId: number
  /** `permissions.actions.write_chat` of the work order. */
  canWriteAction: boolean
}

/**
 * Body of a conversation window of the chat dock, laid out like the assistant's chat:
 * scrolling messages (older-page loader on top) above the composer, filling the
 * window. Live updates are NOT subscribed here: the chat dock owns every
 * channel (`useChatChannelsLive`, spec 0432). Mounting this panel (opening
 * the window) marks the thread read (spec 0359 AC-022).
 */
export function WorkOrderChatPanel({ workOrderId, canWriteAction }: WorkOrderChatPanelProps) {
  const { t, i18n } = useTranslation()
  const { user } = useAuth()
  const now = useNow()
  const chat = useWorkOrderChat(workOrderId)
  const send = useSendChatMessage(workOrderId)
  const edit = useEditChatMessage(workOrderId)
  const remove = useDeleteChatMessage(workOrderId)
  const loadOlder = useLoadOlderChatMessages(workOrderId)
  const listRef = useRef<HTMLUListElement>(null)
  const thread = chat.data
  const lastMessageId = thread?.messages.at(-1)?.id

  useChatMarkRead(workOrderId, thread)

  // Keep the newest message in view when one lands (DOM sync, not data).
  useEffect(() => {
    const list = listRef.current
    if (list) {
      list.scrollTop = list.scrollHeight
    }
  }, [lastMessageId])

  if (chat.isPending) {
    return (
      <div role="status" aria-label={t('workOrders.chat.loading')} className="flex flex-col gap-2 p-3">
        <Skeleton className="h-8 w-2/3" />
        <Skeleton className="h-8 w-1/2 self-end" />
      </div>
    )
  }

  if (!thread) {
    return (
      <div role="alert" className="flex flex-col items-start gap-2 p-3 text-sm">
        <span>{t('workOrders.chat.loadError')}</span>
        <Button type="button" size="xs" variant="outline" className={CHAT_DOCK_SOFT_BUTTON_CLASS} onClick={() => void chat.refetch()}>
          {t('workOrders.chat.retry')}
        </Button>
      </div>
    )
  }

  const canWrite = canWriteAction && thread.can_write
  const currentUserId = user?.id ?? null
  const oldestId = thread.messages[0]?.id

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <ul
        ref={listRef}
        aria-label={t('workOrders.chat.messagesLabel')}
        className="flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto px-3 py-2"
      >
        {thread.has_more && oldestId !== undefined ? (
          <li className="mb-2 self-center">
            <Button
              type="button"
              size="xs"
              variant="outline"
              className={CHAT_DOCK_SOFT_BUTTON_CLASS}
              disabled={loadOlder.isPending}
              onClick={() => loadOlder.mutate(oldestId)}
            >
              {t('workOrders.chat.loadPrevious')}
            </Button>
          </li>
        ) : null}
        {thread.messages.length === 0 ? (
          <li className="m-auto flex flex-col items-center gap-2 py-6 text-center">
            <span className="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground">
              <MessagesSquare className="size-4" aria-hidden="true" />
            </span>
            <span className="text-xs text-muted-foreground">{t('workOrders.chat.empty')}</span>
          </li>
        ) : null}
        {buildChatTimeline(thread.messages).map((item) =>
          item.kind === 'day' ? (
            <li key={`day-${item.key}`} className="my-2 flex items-center gap-2 text-[0.7rem] text-muted-foreground first:mt-0">
              <span className="h-px flex-1 bg-border" aria-hidden="true" />
              {formatChatDay(item.date, now, i18n.language, {
                today: t('workOrders.chat.today'),
                yesterday: t('workOrders.chat.yesterday'),
              })}
              <span className="h-px flex-1 bg-border" aria-hidden="true" />
            </li>
          ) : (
            <WorkOrderChatMessage
              key={item.message.id}
              message={item.message}
              isOwn={item.message.author?.id === currentUserId}
              startsGroup={item.startsGroup}
              canManage={canManageMessage(item.message, {
                canWrite,
                canModerate: thread.can_moderate,
                currentUserId,
                now,
              })}
              isPending={edit.isPending || remove.isPending}
              onEdit={(messageId, body) => edit.mutateAsync({ messageId, body })}
              onDelete={(messageId) => remove.mutate(messageId)}
            />
          ),
        )}
      </ul>
      <div className="border-t border-border bg-muted/40 p-2">
        {canWrite ? (
          <WorkOrderChatComposer
            isPending={send.isPending}
            submitLabel={t('workOrders.chat.send')}
            onSubmit={(body) => send.mutateAsync(body)}
          />
        ) : (
          <p role="status" className="px-1 py-0.5 text-xs text-muted-foreground">
            {t('workOrders.chat.readOnly')}
          </p>
        )}
      </div>
    </div>
  )
}
