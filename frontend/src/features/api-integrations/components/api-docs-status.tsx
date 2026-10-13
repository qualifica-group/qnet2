import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'

interface ApiDocsGeneratingProps {
  /** The server gave up waiting or the request failed. */
  failed: boolean
  onRetry: () => void
}

/** Compact status card: the document is being prepared (polite live region) or could not be loaded. */
export function ApiDocsStatus({ failed, onRetry }: ApiDocsGeneratingProps) {
  const { t } = useTranslation()

  if (failed) {
    return (
      <Card role="alert" className="flex-row flex-wrap items-center gap-3 p-3">
        <p className="text-sm text-destructive">{t('apiIntegrations.docs.loadError')}</p>
        <Button type="button" size="sm" variant="secondary" onClick={onRetry}>
          {t('apiIntegrations.docs.retry')}
        </Button>
      </Card>
    )
  }

  return (
    <Card role="status" aria-live="polite" className="flex-row items-center gap-2 p-3">
      <Loader2 className="size-4 shrink-0 animate-spin text-muted-foreground" aria-hidden />
      <p className="text-sm">{t('apiIntegrations.docs.generating')}</p>
    </Card>
  )
}
