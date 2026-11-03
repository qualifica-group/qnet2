import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { AiAssistantPageView } from '@/features/ai-assistant/components/ai-assistant-page-view'
import { AI_ASSISTANT_PERMISSION } from '@/features/ai-assistant/routes'

/** AI assistant page (spec 0347): gated by `ai-assistant.use`, composition only. */
export default function AiAssistantPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission={AI_ASSISTANT_PERMISSION}
      fallback={<p className="text-sm text-muted-foreground">{t('aiAssistant.forbidden')}</p>}
    >
      <AiAssistantPageView />
    </Can>
  )
}
