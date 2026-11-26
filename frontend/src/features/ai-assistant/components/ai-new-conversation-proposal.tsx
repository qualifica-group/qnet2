import { MessageSquarePlus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

/** Offered once the conversation was summarized too many times (D-5): nothing passes to the new one. */
export function AiNewConversationProposal({ onStart }: { onStart: () => void }) {
  const { t } = useTranslation()

  return (
    <div role="status" className="mx-3 mb-2 flex flex-col gap-2 rounded-lg border border-border bg-surface p-3 text-xs">
      <p>{t('aiAssistant.longConversation')}</p>
      <Button type="button" size="sm" variant="secondary" className="self-start" onClick={onStart}>
        <MessageSquarePlus className="size-3.5" aria-hidden="true" />
        {t('aiAssistant.startNewConversation')}
      </Button>
    </div>
  )
}
