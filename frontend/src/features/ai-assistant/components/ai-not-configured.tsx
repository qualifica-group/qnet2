import { useTranslation } from 'react-i18next'
import { PlugZap } from 'lucide-react'

/** Shown instead of the composer when the server has no AI driver configured (AC-026). */
export function AiNotConfigured() {
  const { t } = useTranslation()
  return (
    <div role="status" className="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
      <PlugZap className="size-6 text-muted-foreground" aria-hidden="true" />
      <h3 className="text-base font-semibold">{t('aiAssistant.notConfigured.title')}</h3>
      <p className="max-w-xs text-xs text-muted-foreground">{t('aiAssistant.notConfigured.description')}</p>
    </div>
  )
}
