import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { ApiDocsAuthSection } from '@/features/api-integrations/components/api-docs-auth-section'
import { ApiDocsTagGroup } from '@/features/api-integrations/components/api-docs-tag-group'
import {
  filterOperationGroups,
  parseOpenApiOperations,
} from '@/features/api-integrations/openapi-operations'
import { useApiDocDownload, useOpenApiDocument } from '@/features/api-integrations/use-api-clients'
import type { ApiDocKind } from '@/features/api-integrations/types'

/** Fallback when the document declares no server (should not happen: spec fixes `servers[0].url`). */
const DEFAULT_BASE_URL = '/api'

/** "Documentation" tab: everything is read from the OpenAPI document served by the backend. */
export function ApiDocsTab() {
  const { t } = useTranslation()
  const { data: document, isLoading, isError } = useOpenApiDocument()
  const download = useApiDocDownload()
  const [query, setQuery] = useState('')
  const groups = useMemo(() => (document ? parseOpenApiOperations(document) : []), [document])
  const visibleGroups = useMemo(() => filterOperationGroups(groups, query), [groups, query])
  const isSearching = query.trim() !== ''

  const handleDownload = (kind: ApiDocKind) =>
    download.mutate(kind, { onError: () => toast.error(t('apiIntegrations.docs.downloadError')) })

  if (isLoading) {
    return (
      <div className="flex flex-col gap-3" aria-busy="true">
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (isError || !document) {
    return (
      <p role="alert" className="text-sm text-destructive">
        {t('apiIntegrations.docs.loadError')}
      </p>
    )
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <div className="flex flex-wrap gap-2">
        <Button size="sm" variant="secondary" disabled={download.isPending} onClick={() => handleDownload('openapi')}>
          <Download className="size-3.5" aria-hidden />
          {t('apiIntegrations.docs.downloadOpenApi')}
        </Button>
        <Button size="sm" variant="secondary" disabled={download.isPending} onClick={() => handleDownload('postman')}>
          <Download className="size-3.5" aria-hidden />
          {t('apiIntegrations.docs.downloadPostman')}
        </Button>
      </div>

      <ApiDocsAuthSection baseUrl={document.servers?.[0]?.url ?? DEFAULT_BASE_URL} />

      <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.changeNote')}</p>

      <Input
        type="search"
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        placeholder={t('apiIntegrations.docs.searchPlaceholder')}
        aria-label={t('apiIntegrations.docs.searchLabel')}
        className="max-w-sm"
      />

      {visibleGroups.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          {t(isSearching ? 'apiIntegrations.docs.noResults' : 'apiIntegrations.docs.empty')}
        </p>
      ) : (
        visibleGroups.map((group) => (
          <ApiDocsTagGroup key={group.tag} group={group} forceOpen={isSearching} />
        ))
      )}
    </div>
  )
}
