import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

interface BootErrorScreenProps {
  description: string
  onRetry: () => void
  isRetrying?: boolean
}

/** Full-screen failure of a request the app cannot start without, with a Retry button. */
export function BootErrorScreen({ description, onRetry, isRetrying = false }: BootErrorScreenProps) {
  const { t } = useTranslation()

  return (
    <div
      className="flex min-h-svh flex-col items-center justify-center gap-4 p-4 text-center"
      role="alert"
    >
      <p className="text-xl font-semibold tracking-tight">{t('config.error.title')}</p>
      <p className="max-w-md text-muted-foreground">{description}</p>
      <Button disabled={isRetrying} onClick={onRetry}>
        {t('config.error.retry')}
      </Button>
    </div>
  )
}
