import { useTranslation } from 'react-i18next'
import { Link2, RefreshCw, Plug } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { TableView } from '@/features/table/table-view'
import NotFoundPage from '@/pages/not-found-page'
import { FORMALAB_CALL_LOGS_DOMAIN } from '@/integrations/formalab/api'
import { StatusCard } from '@/integrations/formalab/components/status-card'
import { useFormalabActions } from '@/integrations/formalab/use-formalab-actions'
import { useFormalabStatus } from '@/integrations/formalab/use-formalab-status'
import { FORMALAB_KEY } from '@/integrations/formalab/definition-key'
import { useIsIntegrationActive } from '@/integrations/use-active-integrations'

/** The call log is read-only: no row action is handled. */
const NO_ROW_ACTIONS = () => undefined

/**
 * `/dev/formalab` (super-admin: the route sits behind the role guard, every
 * endpoint re-checks it). Not found when the integration is not active.
 */
export default function FormalabStatusPage() {
  const { isActive, isLoading: activeLoading } = useIsIntegrationActive(FORMALAB_KEY)

  if (activeLoading) {
    return <Skeleton className="h-40 w-full" aria-busy="true" />
  }
  if (!isActive) {
    return <NotFoundPage />
  }
  return <StatusContent />
}

function StatusContent() {
  const { t } = useTranslation('formalab')
  const { data, isLoading, isError } = useFormalabStatus()
  const { sync, testConnection, linkWorkOrders } = useFormalabActions()

  return (
    <div className="flex min-w-0 flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button size="sm" variant="secondary" disabled={sync.isPending} onClick={() => sync.mutate()}>
              <RefreshCw className="size-3.5" aria-hidden />
              {t('actions.sync')}
            </Button>
            <Button size="sm" variant="secondary" disabled={testConnection.isPending} onClick={() => testConnection.mutate()}>
              <Plug className="size-3.5" aria-hidden />
              {t('actions.testConnection')}
            </Button>
            <Button size="sm" variant="secondary" disabled={linkWorkOrders.isPending} onClick={() => linkWorkOrders.mutate()}>
              <Link2 className="size-3.5" aria-hidden />
              {t('actions.linkWorkOrders')}
            </Button>
          </>
        }
      />

      {isLoading ? (
        <Skeleton className="h-40 w-full" aria-busy="true" />
      ) : isError || !data ? (
        <p role="alert" className="text-sm text-destructive">
          {t('status.loadError')}
        </p>
      ) : (
        <StatusCard status={data} />
      )}

      <section className="flex flex-col gap-2" aria-labelledby="formalab-call-logs-title">
        <h2 id="formalab-call-logs-title" className="text-base font-semibold">
          {t('callLogs.title')}
        </h2>
        <TableView domain={FORMALAB_CALL_LOGS_DOMAIN} onAction={NO_ROW_ACTIONS} />
      </section>
    </div>
  )
}
