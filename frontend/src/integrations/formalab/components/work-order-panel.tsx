import { useTranslation } from 'react-i18next'
import { RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ClassCard } from '@/integrations/formalab/components/class-card'
import { useFormalabSync, useFormalabWorkOrderLink } from '@/integrations/formalab/use-formalab-work-order-link'

/**
 * Body of the work order's "Formalab" tab: the synced class, and the single
 * "Sincronizza" action. The edition is chosen in the work order's class select
 * (matched by edition_id only): this panel never picks it.
 */
export function FormalabWorkOrderPanel({ workOrderId }: { workOrderId: number }) {
  const { t } = useTranslation('formalab')
  const { data, isLoading, isError } = useFormalabWorkOrderLink(workOrderId, true)
  const sync = useFormalabSync(workOrderId)

  if (isLoading) {
    return <Skeleton className="m-3 h-24" aria-busy="true" />
  }
  if (isError || !data) {
    return (
      <p role="alert" className="p-3 text-sm text-destructive">
        {t('workOrder.loadError')}
      </p>
    )
  }

  return (
    <div className="flex flex-col gap-3 p-3">
      {data.link ? (
        <ClassCard link={data.link} />
      ) : (
        <p className="text-sm text-muted-foreground">{t('workOrder.notLinked')}</p>
      )}
      {data.can_manage ? (
        <div>
          <Button size="sm" disabled={sync.isPending} onClick={() => sync.mutate()}>
            <RefreshCw className={sync.isPending ? 'size-3.5 animate-spin' : 'size-3.5'} aria-hidden="true" />
            {t('workOrder.sync')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
