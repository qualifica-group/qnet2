import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Download } from 'lucide-react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { ApiDocsRefreshButton } from '@/features/api-integrations/components/api-docs-refresh-button'
import { CopyButton } from '@/features/api-integrations/components/copy-button'
import type { ApiDocKind } from '@/features/api-integrations/types'
import { useApiDocDownload } from '@/features/api-integrations/use-api-clients'
import { useApiDocsStatus } from '@/features/api-integrations/use-openapi-document'
import { formatDateTime } from '@/lib/formatting/date-display'

interface ApiReferenceHeaderProps {
  /** Null while the document is not available yet: base URL and totals are hidden. */
  baseUrl: string | null
  version: string | null
  endpointCount: number | null
  moduleCount: number | null
}

/** Title, base URL, version, totals, generation instant, Refresh and the OpenAPI / Postman downloads. */
export function ApiReferenceHeader({ baseUrl, version, endpointCount, moduleCount }: ApiReferenceHeaderProps) {
  const { t, i18n } = useTranslation()
  const download = useApiDocDownload()
  const { data: docsStatus } = useApiDocsStatus()
  const generatedAt = docsStatus?.generated_at ?? null
  const downloadDisabled = download.isPending || generatedAt === null

  const handleDownload = (kind: ApiDocKind) =>
    download.mutate(kind, {
      onError: (error) => toast.error(apiErrorMessage(error, t('apiIntegrations.docs.downloadError'), i18n)),
    })

  return (
    <Card className="gap-2 p-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h2 className="text-base font-semibold">{t('apiIntegrations.docs.referenceTitle')}</h2>
          <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.referenceSubtitle')}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ApiDocsRefreshButton isGenerating={docsStatus?.status === 'generating'} />
          <Button
            size="sm"
            variant="secondary"
            disabled={downloadDisabled}
            aria-label={t('apiIntegrations.docs.downloadOpenApi')}
            onClick={() => handleDownload('openapi')}
          >
            <Download className="size-3.5" aria-hidden />
            OpenAPI
          </Button>
          <Button
            size="sm"
            variant="secondary"
            disabled={downloadDisabled}
            aria-label={t('apiIntegrations.docs.downloadPostman')}
            onClick={() => handleDownload('postman')}
          >
            <Download className="size-3.5" aria-hidden />
            Postman
          </Button>
        </div>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        {baseUrl ? (
          <div className="flex min-w-0 items-center gap-1 rounded-md border border-border bg-surface py-0.5 pr-0.5 pl-2">
            <span className="sr-only">{t('apiIntegrations.docs.baseUrl')}</span>
            <code className="min-w-0 truncate font-mono text-xs">{baseUrl}</code>
            <CopyButton
              value={baseUrl}
              label={t('apiIntegrations.docs.copyBaseUrl')}
              successMessage={t('apiIntegrations.docs.copied')}
            />
          </div>
        ) : null}
        {version ? <Badge variant="outline">{t('apiIntegrations.docs.version', { version })}</Badge> : null}
        {endpointCount !== null && moduleCount !== null ? (
          <span className="text-xs text-muted-foreground">
            {t('apiIntegrations.docs.totals', { endpoints: endpointCount, modules: moduleCount })}
          </span>
        ) : null}
        {generatedAt ? (
          <span className="text-xs text-muted-foreground">
            {t('apiIntegrations.docs.generatedAt', { date: formatDateTime(generatedAt) })}
          </span>
        ) : null}
      </div>
      <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.changeNote')}</p>
    </Card>
  )
}
