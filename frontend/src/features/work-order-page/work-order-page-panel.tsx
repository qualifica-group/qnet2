import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkOrderPage } from '@/features/work-order-page/use-work-order-page'
import { WorkOrderPageEditor } from '@/features/work-order-page/work-order-page-editor'

interface WorkOrderPagePanelProps {
  workOrderId: number
  /** `permissions.actions.edit_page` of the work order. */
  canEditAction: boolean
}

/** Body of the "Appunti" tab: loads the page, then hands it to the editor (which owns the text from there on). */
export function WorkOrderPagePanel({ workOrderId, canEditAction }: WorkOrderPagePanelProps) {
  const { t } = useTranslation()
  const page = useWorkOrderPage(workOrderId)

  if (page.isPending) {
    return (
      <div role="status" aria-label={t('workOrderPage.loading')} className="flex flex-col gap-2 p-3">
        <Skeleton className="h-6 w-1/3" />
        <Skeleton className="h-24 w-full" />
      </div>
    )
  }

  if (!page.data) {
    return (
      <div role="alert" className="flex flex-col items-start gap-2 p-3 text-sm">
        <span>{t('workOrderPage.loadError')}</span>
        <Button type="button" size="xs" variant="outline" className="bg-card" onClick={() => void page.refetch()}>
          {t('workOrderPage.retry')}
        </Button>
      </div>
    )
  }

  return <WorkOrderPageEditor workOrderId={workOrderId} page={page.data} canEditAction={canEditAction} />
}
