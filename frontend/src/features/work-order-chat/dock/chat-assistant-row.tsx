import { useTranslation } from 'react-i18next'
import { AiDockAvatar } from '@/features/ai-assistant/components/ai-dock-avatar'

/** The AI assistant pinned on top of the conversation list (spec 0432), laid out like a channel row. */
export function ChatAssistantRow({ onOpen }: { onOpen: () => void }) {
  const { t } = useTranslation()

  return (
    <button
      type="button"
      onClick={onOpen}
      className="flex w-full min-w-0 items-center gap-2.5 px-3 py-2 text-left outline-none hover:bg-muted/40 focus-visible:bg-muted/40"
    >
      <AiDockAvatar className="size-8" />
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="truncate text-xs font-medium">{t('aiAssistant.title')}</span>
        <span className="truncate text-[0.7rem] text-muted-foreground">{t('workOrders.chat.dock.assistantHint')}</span>
      </span>
    </button>
  )
}
