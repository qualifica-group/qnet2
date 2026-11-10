import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

/** Loader for the page area while a lazy page downloads; the app shell around it stays mounted. */
export function PageLoader() {
  const { t } = useTranslation()
  return (
    <div
      className="flex flex-1 items-center justify-center py-12 text-muted-foreground"
      role="status"
      aria-live="polite"
    >
      <Loader2 className="size-5 animate-spin" aria-hidden />
      <span className="sr-only">{t('common.loading')}</span>
    </div>
  )
}
