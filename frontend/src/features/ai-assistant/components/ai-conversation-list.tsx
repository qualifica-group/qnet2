import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { MessageSquarePlus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'
import { useAiConversations } from '@/features/ai-assistant/hooks/use-ai-conversations'
import { useDeleteAiConversation } from '@/features/ai-assistant/hooks/use-delete-ai-conversation'
import type { AiConversation } from '@/features/ai-assistant/types'

interface Props {
  selectedId: number | null
  /** `null` starts a new conversation. */
  onSelect: (id: number | null) => void
}

/** Saved conversations of the user: select, start a new one, delete with confirmation. */
export function AiConversationList({ selectedId, onSelect }: Props) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const query = useAiConversations()
  const remove = useDeleteAiConversation()
  const conversations = query.data?.pages.flatMap((page) => page.items) ?? []

  const handleDelete = async (conversation: AiConversation) => {
    const confirmed = await confirm({
      title: t('aiAssistant.deleteConfirmTitle'),
      description: t('aiAssistant.deleteConfirmDescription'),
      confirmLabel: t('aiAssistant.deleteConfirmLabel'),
      tone: 'destructive',
    })
    if (!confirmed) {
      return
    }
    try {
      await remove.mutateAsync(conversation.id)
      if (conversation.id === selectedId) {
        onSelect(null)
      }
    } catch {
      toast.error(t('aiAssistant.deleteFailed'))
    }
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col gap-2">
      <Button type="button" size="sm" variant="outline" className="w-full justify-start" onClick={() => onSelect(null)}>
        <MessageSquarePlus className="size-3.5" />
        {t('aiAssistant.newConversation')}
      </Button>
      <nav aria-label={t('aiAssistant.conversations')} className="min-h-0 flex-1 overflow-y-auto">
        {query.isLoading ? (
          <p role="status" className="px-2 py-1 text-xs text-muted-foreground">
            {t('aiAssistant.loading')}
          </p>
        ) : conversations.length === 0 ? (
          <p className="px-2 py-1 text-xs text-muted-foreground">{t('aiAssistant.noConversations')}</p>
        ) : (
          <ul className="flex flex-col gap-0.5">
            {conversations.map((conversation) => (
              <li key={conversation.id} className="group flex items-center gap-1">
                <button
                  type="button"
                  aria-current={conversation.id === selectedId ? 'true' : undefined}
                  onClick={() => onSelect(conversation.id)}
                  className={cn(
                    'flex min-w-0 flex-1 flex-col rounded-md px-2 py-1 text-left text-xs hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                    conversation.id === selectedId && 'bg-accent text-accent-foreground',
                  )}
                >
                  <span className="truncate font-medium">
                    {conversation.title || t('aiAssistant.untitledConversation')}
                  </span>
                  <span className="text-muted-foreground">
                    {formatDate(conversation.last_message_at ?? conversation.created_at)}
                  </span>
                </button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  aria-label={`${t('aiAssistant.deleteConversation')}: ${conversation.title || t('aiAssistant.untitledConversation')}`}
                  onClick={() => void handleDelete(conversation)}
                >
                  <Trash2 />
                </Button>
              </li>
            ))}
          </ul>
        )}
        {query.hasNextPage ? (
          <Button
            type="button"
            variant="ghost"
            size="xs"
            className="mt-1 w-full"
            disabled={query.isFetchingNextPage}
            onClick={() => void query.fetchNextPage()}
          >
            {t('aiAssistant.loadMore')}
          </Button>
        ) : null}
      </nav>
    </div>
  )
}
