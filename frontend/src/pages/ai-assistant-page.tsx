import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { AiAssistantPageView } from '@/features/ai-assistant/components/ai-assistant-page-view'
import { useAiAssistantConfig } from '@/features/ai-assistant/hooks/use-ai-assistant-config'
import { AI_ASSISTANT_PERMISSION } from '@/features/ai-assistant/routes'

/** AI assistant page (spec 0347): gated by `ai-assistant.use` and by the server page switch (D-16). */
export default function AiAssistantPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission={AI_ASSISTANT_PERMISSION}
      fallback={<p className="text-sm text-muted-foreground">{t('aiAssistant.forbidden')}</p>}
    >
      <AiAssistantPageGate />
    </Can>
  )
}

/** A failed config load falls through to the view, whose chat panel shows the load error. */
function AiAssistantPageGate() {
  const { t } = useTranslation()
  const configQuery = useAiAssistantConfig()

  if (configQuery.isLoading) {
    return (
      <p role="status" className="text-sm text-muted-foreground">
        {t('aiAssistant.loading')}
      </p>
    )
  }
  if (configQuery.data && !configQuery.data.page_enabled) {
    return <p className="text-sm text-muted-foreground">{t('aiAssistant.pageDisabled')}</p>
  }
  return <AiAssistantPageView />
}
