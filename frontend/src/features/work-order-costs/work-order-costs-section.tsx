import { useTranslation } from 'react-i18next'
import { Euro } from 'lucide-react'
import { RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { WorkOrderCostsComparisonTab } from '@/features/work-order-costs/work-order-costs-comparison-tab'
import { WorkOrderCostsEditorTab } from '@/features/work-order-costs/work-order-costs-editor-tab'
import { useWorkOrderCosts } from '@/features/work-order-costs/use-work-order-costs'

const TAB_CLASS = 'px-2.5 py-1 text-xs'

interface WorkOrderCostsSectionProps {
  workOrderId: number
  /** `permissions.actions.manage_costs` of the work order. */
  canManage: boolean
}

/**
 * Full-width "Costi" card of the work order detail (spec 0190 D-9): budget vs
 * actual comparison and the real-cost grid. The caller mounts it only with
 * `view_costs`, so the costs query never fires for an actor who may not see it.
 */
export function WorkOrderCostsSection({ workOrderId, canManage }: WorkOrderCostsSectionProps) {
  const { t } = useTranslation()
  const costs = useWorkOrderCosts(workOrderId)

  return (
    <RecordCard>
      <RecordCardHeader
        media={<Euro aria-hidden="true" className="mt-0.5 size-3.5 text-muted-foreground" />}
        title={t('workOrders.costs.title')}
      />
      <div className="p-4">
        {costs.isPending ? (
          <div role="status" aria-label={t('workOrders.costs.loading')} className="flex flex-col gap-2">
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-24 w-full" />
          </div>
        ) : costs.isError ? (
          <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
            {t('workOrders.costs.loadError')}
            <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void costs.refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : (
          <Tabs defaultValue="comparison">
            <TabsList>
              <TabsTrigger value="comparison" className={TAB_CLASS}>{t('workOrders.costs.tabs.comparison')}</TabsTrigger>
              <TabsTrigger value="actual" className={TAB_CLASS}>{t('workOrders.costs.tabs.actual')}</TabsTrigger>
            </TabsList>
            <TabsContent value="comparison">
              <WorkOrderCostsComparisonTab overview={costs.data} />
            </TabsContent>
            <TabsContent value="actual">
              <WorkOrderCostsEditorTab workOrderId={workOrderId} overview={costs.data} canManage={canManage} />
            </TabsContent>
          </Tabs>
        )}
      </div>
    </RecordCard>
  )
}
