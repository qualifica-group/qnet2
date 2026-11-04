import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Pencil, Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { WorkOrderChatComposer } from '@/features/work-order-chat/work-order-chat-composer'
import type { ChatMessage } from '@/features/work-order-chat/types'

interface WorkOrderChatMessageProps {
  message: ChatMessage
  isOwn: boolean
  /** Result of `canManageMessage`: edit and delete are shown only when true. */
  canManage: boolean
  isPending: boolean
  onEdit: (messageId: number, body: string) => Promise<unknown>
  onDelete: (messageId: number) => void
}

/** One chat entry: plain text only (React escapes it), deleted ones are a placeholder. */
export function WorkOrderChatMessage({ message, isOwn, canManage, isPending, onEdit, onDelete }: WorkOrderChatMessageProps) {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const [isEditing, setIsEditing] = useState(false)
  const author = message.author?.name ?? t('workOrders.chat.unknownAuthor')
  const time = new Date(message.created_at).toLocaleString(i18n.language, { dateStyle: 'short', timeStyle: 'short' })

  const confirmDelete = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('workOrders.chat.confirmDelete.title'),
      description: t('workOrders.chat.confirmDelete.description'),
      confirmLabel: t('workOrders.chat.delete'),
      cancelLabel: t('workOrders.chat.cancel'),
    })
    if (confirmed) onDelete(message.id)
  }

  return (
    <li className={`flex flex-col gap-0.5 ${isOwn ? 'items-end' : 'items-start'}`}>
      <div className="flex flex-wrap items-baseline gap-x-2 text-xs text-muted-foreground">
        <span className="font-medium text-foreground">{author}</span>
        <time dateTime={message.created_at}>{time}</time>
        {message.edited_at && !message.is_deleted ? <span>({t('workOrders.chat.edited')})</span> : null}
      </div>
      {isEditing ? (
        <div className="w-full max-w-prose">
          <WorkOrderChatComposer
            initialBody={message.body ?? ''}
            isPending={isPending}
            submitLabel={t('workOrders.chat.save')}
            onCancel={() => setIsEditing(false)}
            onSubmit={async (body) => {
              await onEdit(message.id, body)
              setIsEditing(false)
            }}
          />
        </div>
      ) : (
        <div className="flex max-w-full items-start gap-1">
          <p
            className={`max-w-prose rounded-lg border px-2.5 py-1.5 text-sm break-words whitespace-pre-wrap ${
              message.is_deleted ? 'bg-surface italic text-muted-foreground' : 'bg-card'
            }`}
          >
            {message.is_deleted ? t('workOrders.chat.deletedMessage') : message.body}
          </p>
          {canManage ? (
            <div className="flex shrink-0 gap-0.5">
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={t('workOrders.chat.edit')}
                onClick={() => setIsEditing(true)}
              >
                <Pencil aria-hidden="true" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={t('workOrders.chat.delete')}
                disabled={isPending}
                onClick={() => void confirmDelete()}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </div>
          ) : null}
        </div>
      )}
    </li>
  )
}
