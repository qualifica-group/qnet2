import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/features/auth/use-auth'
import { canManageMessage } from '@/features/work-order-chat/chat-cache'
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

interface WorkOrderChatPanelProps {
  workOrderId: number
  /** `permissions.actions.write_chat` of the work order. */
  canWriteAction: boolean
}

/**
 * Body of the "Chat" tab: chronological messages, older-page loader, composer.
 * Live updates are NOT subscribed here: `WorkOrderChatTabLabel` owns the channel
 * so the badge stays live while another tab is open. Mounting this panel marks
 * the thread read (spec 0359 AC-022).
 */
export function WorkOrderChatPanel({ workOrderId, canWriteAction }: WorkOrderChatPanelProps) {
  const { t } = useTranslation()
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
        <Button type="button" size="xs" variant="outline" onClick={() => void chat.refetch()}>
          {t('workOrders.chat.retry')}
        </Button>
      </div>
    )
  }

  const canWrite = canWriteAction && thread.can_write
  const currentUserId = user?.id ?? null
  const oldestId = thread.messages[0]?.id

  return (
    <div className="flex flex-col gap-2 p-3">
      {thread.has_more && oldestId !== undefined ? (
        <Button
          type="button"
          size="xs"
          variant="outline"
          className="self-center"
          disabled={loadOlder.isPending}
          onClick={() => loadOlder.mutate(oldestId)}
        >
          {t('workOrders.chat.loadPrevious')}
        </Button>
      ) : null}
      {thread.messages.length === 0 ? (
        <p className="py-4 text-center text-xs text-muted-foreground">{t('workOrders.chat.empty')}</p>
      ) : (
        <ul
          ref={listRef}
          aria-label={t('workOrders.chat.messagesLabel')}
          className="flex max-h-96 min-h-24 flex-col gap-2 overflow-y-auto rounded-lg bg-surface p-2"
        >
          {thread.messages.map((message) => (
            <WorkOrderChatMessage
              key={message.id}
              message={message}
              isOwn={message.author?.id === currentUserId}
              canManage={canManageMessage(message, {
                canWrite,
                canModerate: thread.can_moderate,
                currentUserId,
                now,
              })}
              isPending={edit.isPending || remove.isPending}
              onEdit={(messageId, body) => edit.mutateAsync({ messageId, body })}
              onDelete={(messageId) => remove.mutate(messageId)}
            />
          ))}
        </ul>
      )}
      {canWrite ? (
        <WorkOrderChatComposer
          isPending={send.isPending}
          submitLabel={t('workOrders.chat.send')}
          onSubmit={(body) => send.mutateAsync(body)}
        />
      ) : (
        <p role="status" className="rounded-md border bg-surface px-2.5 py-1.5 text-xs text-muted-foreground">
          {t('workOrders.chat.readOnly')}
        </p>
      )}
    </div>
  )
}
