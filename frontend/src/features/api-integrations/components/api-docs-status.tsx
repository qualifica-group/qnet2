import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'

export type ApiDocsStatusState = 'generating' | 'missing' | 'failed'

interface ApiDocsStatusProps {
  state: ApiDocsStatusState
  onRetry: () => void
}

/** Compact status card shown while there is no document to render. */
export function ApiDocsStatus({ state, onRetry }: ApiDocsStatusProps) {
  const { t } = useTranslation()

  if (state === 'failed') {
    return (
      <Card role="alert" className="flex-row flex-wrap items-center gap-3 p-3">
        <p className="text-sm text-destructive">{t('apiIntegrations.docs.loadError')}</p>
        <Button type="button" size="sm" variant="secondary" onClick={onRetry}>
          {t('apiIntegrations.docs.retry')}
        </Button>
      </Card>
    )
  }

  if (state === 'missing') {
    return (
      <Card role="status" className="p-3">
        <p className="text-sm text-muted-foreground">{t('apiIntegrations.docs.missing')}</p>
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
