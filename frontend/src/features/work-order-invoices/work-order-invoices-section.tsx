import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useRefreshWorkOrderInvoices, useWorkOrderInvoices } from '@/features/work-order-invoices/use-work-order-invoices'
import { WorkOrderActiveInvoicesTable } from '@/features/work-order-invoices/work-order-active-invoices-table'
import { WorkOrderPassiveInvoicesTable } from '@/features/work-order-invoices/work-order-passive-invoices-table'

export type WorkOrderInvoicesKind = 'active' | 'passive'

interface WorkOrderInvoicesSectionProps {
  workOrderId: number
  kind: WorkOrderInvoicesKind
}

/**
 * Body of the "Proforma attiva" / "Proforma passiva" tabs (spec 0282 D-9). Both
 * tabs call the same query, so the endpoint is hit once; the caller mounts it
 * only with `view_invoices`.
 */
export function WorkOrderInvoicesSection({ workOrderId, kind }: WorkOrderInvoicesSectionProps) {
  const { t } = useTranslation()
  const invoices = useWorkOrderInvoices(workOrderId)
  const refresh = useRefreshWorkOrderInvoices(workOrderId)

  return (
    <div className="p-4">
      {invoices.isPending ? (
        <div role="status" aria-label={t('workOrders.invoices.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-16 w-full" />
        </div>
      ) : invoices.isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          {t('workOrders.invoices.loadError')}
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void invoices.refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : kind === 'active' ? (
        <WorkOrderActiveInvoicesTable rows={invoices.data.active} totals={invoices.data.totals.active} onChanged={refresh} />
      ) : (
        <WorkOrderPassiveInvoicesTable rows={invoices.data.passive} totals={invoices.data.totals.passive} onChanged={refresh} />
      )}
    </div>
  )
}
