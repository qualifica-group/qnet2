import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Pencil, Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import { formatChatClock } from '@/features/work-order-chat/chat-timeline'
import { WorkOrderChatComposer } from '@/features/work-order-chat/work-order-chat-composer'
import type { ChatMessage } from '@/features/work-order-chat/types'

interface WorkOrderChatMessageProps {
  message: ChatMessage
  isOwn: boolean
  /** First message of a run by the same author: avatar, name and time are shown once per run. */
  startsGroup: boolean
  /** Result of `canManageMessage`: edit and delete are shown only when true. */
  canManage: boolean
  isPending: boolean
  onEdit: (messageId: number, body: string) => Promise<unknown>
  onDelete: (messageId: number) => void
}

/**
 * One chat entry, laid out like a messaging CRM: the others on the left with
 * their avatar on the muted tint above the dock's card, the own ones on the
 * right in the primary colour, author and
 * time once per group. Edit and delete appear on hover or focus (always on
 * touch screens). Plain text only (React escapes it); deleted ones are a
 * placeholder.
 */
export function WorkOrderChatMessage({
  message,
  isOwn,
  startsGroup,
  canManage,
  isPending,
  onEdit,
  onDelete,
}: WorkOrderChatMessageProps) {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const [isEditing, setIsEditing] = useState(false)
  const author = message.author?.name ?? t('workOrders.chat.unknownAuthor')
  const fullTime = new Date(message.created_at).toLocaleString(i18n.language, { dateStyle: 'short', timeStyle: 'short' })

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
    <li className={cn('group/message flex gap-2', isOwn && 'flex-row-reverse', startsGroup ? 'mt-2.5 first:mt-0' : 'mt-0.5')}>
      {isOwn ? null : (
        <span className="w-6 shrink-0">
          {startsGroup ? <UserAvatar name={author} src={message.author?.avatar_url} size="sm" /> : null}
        </span>
      )}
      <div className={cn('flex min-w-0 max-w-[80%] flex-col gap-0.5', isOwn ? 'items-end' : 'items-start')}>
        {startsGroup ? (
          <div className="flex items-baseline gap-1.5 px-1 text-[0.7rem] text-muted-foreground">
            {isOwn ? null : <span className="font-medium text-foreground">{author}</span>}
            <time dateTime={message.created_at} title={fullTime}>
              {formatChatClock(message.created_at, i18n.language)}
            </time>
          </div>
        ) : null}
        {isEditing ? (
          <div className="w-64 max-w-full">
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
          <div className={cn('flex max-w-full items-center gap-0.5', isOwn && 'flex-row-reverse')}>
            <p
              className={cn(
                'min-w-0 rounded-2xl px-3 py-1.5 text-sm whitespace-pre-wrap [overflow-wrap:anywhere]',
                message.is_deleted
                  ? 'border border-dashed border-border italic text-muted-foreground'
                  : isOwn
                    ? 'rounded-br-md bg-primary text-primary-foreground'
                    : 'rounded-bl-md bg-muted text-foreground',
              )}
            >
              {message.is_deleted ? t('workOrders.chat.deletedMessage') : message.body}
            </p>
            {canManage ? (
              <div className="flex shrink-0 opacity-0 transition-opacity group-hover/message:opacity-100 focus-within:opacity-100 [@media(hover:none)]:opacity-100">
                <Button type="button" variant="ghost" size="icon-xs" aria-label={t('workOrders.chat.edit')} onClick={() => setIsEditing(true)}>
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
        {message.edited_at && !message.is_deleted ? (
          <span className="px-1 text-[0.65rem] text-muted-foreground">({t('workOrders.chat.edited')})</span>
        ) : null}
      </div>
    </li>
  )
}
