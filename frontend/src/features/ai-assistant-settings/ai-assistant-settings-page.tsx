import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { useAiAssistantSettings } from '@/features/ai-assistant-settings/hooks'
import { SettingsFormView } from '@/features/ai-assistant-settings/settings-form-view'

/**
 * "AI assistant settings" page (spec 0433, super-admin only: the route sits behind the role guard
 * and the backend enforces the same rule). The browser never calls OpenRouter: models and providers
 * come from QNET2.
 */
export default function AiAssistantSettingsPage() {
  const { t } = useTranslation('aiAssistantSettings')
  const { data, isLoading, isError } = useAiAssistantSettings()

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      {isLoading ? (
        <Skeleton className="h-64 w-full" aria-busy="true" />
      ) : isError || !data ? (
        <p role="alert" className="text-sm text-destructive">{t('loadError')}</p>
      ) : (
        <SettingsFormView settings={data} />
      )}
    </div>
  )
}
