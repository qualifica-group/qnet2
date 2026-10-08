import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ContractDataTable } from '@/features/work-order-contract-data/contract-data-table'
import { ContractDataTotals } from '@/features/work-order-contract-data/contract-data-totals'
import { useWorkOrderContractData } from '@/features/work-order-contract-data/use-work-order-contract-data'

interface WorkOrderContractDataSectionProps {
  workOrderId: number
  /** `permissions.actions.manage_payments` of the work order. */
  canManage: boolean
}

/**
 * Contract data of the work order (spec 0201), hosted by the "product lines"
 * section of the work order record card. The caller mounts it only with
 * `view_contract_data`, so the query never fires for an actor who may not see it.
 */
export function WorkOrderContractDataSection({ workOrderId, canManage }: WorkOrderContractDataSectionProps) {
  const { t } = useTranslation()
  const contractData = useWorkOrderContractData(workOrderId)

  if (contractData.isPending) {
    return (
      <div role="status" aria-label={t('workOrders.contractData.loading')} className="flex flex-col gap-2">
        <Skeleton className="h-16 w-full" />
        <Skeleton className="h-24 w-full" />
      </div>
    )
  }

  if (contractData.isError) {
    return (
      <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
        {t('workOrders.contractData.loadError')}
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void contractData.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  const { data } = contractData

  return (
    <div className="flex flex-col gap-3">
      {data.lines.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('workOrders.contractData.empty')}</p>
      ) : (
        <>
          <ContractDataTotals totals={data.totals} />
          <ContractDataTable workOrderId={workOrderId} data={data} canManage={canManage} />
        </>
      )}
    </div>
  )
}
