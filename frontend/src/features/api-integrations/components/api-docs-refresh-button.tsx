import { useTranslation } from 'react-i18next'
import { RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useRefreshApiDocs } from '@/features/api-integrations/use-openapi-document'

interface ApiDocsRefreshButtonProps {
  isGenerating: boolean
}

/** Starts a regeneration of the documentation; `api-clients.update` only (the backend re-authorizes). */
export function ApiDocsRefreshButton({ isGenerating }: ApiDocsRefreshButtonProps) {
  const { t, i18n } = useTranslation()
  const refresh = useRefreshApiDocs()
  const busy = isGenerating || refresh.isPending

  const handleRefresh = () =>
    refresh.mutate(undefined, {
      onError: (error) => toast.error(apiErrorMessage(error, t('apiIntegrations.docs.refreshError'), i18n)),
    })

  return (
    <Can permission="api-clients.update">
      <Button size="sm" variant="secondary" disabled={busy} onClick={handleRefresh}>
        <RefreshCw className={busy ? 'size-3.5 animate-spin' : 'size-3.5'} aria-hidden />
        {busy ? t('apiIntegrations.docs.refreshing') : t('apiIntegrations.docs.refresh')}
      </Button>
    </Can>
  )
}
