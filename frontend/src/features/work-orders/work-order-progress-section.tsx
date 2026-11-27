import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ListChecks, TrendingUp } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { formatDate } from '@/lib/formatting/date-display'
import { WorkOrderProgressDialog } from '@/features/work-orders/work-order-progress-dialog'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderProgressSectionProps {
  workOrder: WorkOrderDetailWithPermissions
  /** After a registration or deletion: the host refreshes the lists that show the work order. */
  onChanged?: () => void
  className?: string
}

/**
 * "Avanzamento commessa" (spec 0321, legacy "Avanzamento Commessa" of the
 * Info and "€" tabs, user directive 2026-11-27): the total reached with the
 * date of the last entry, and "Gestione avanzamento" opening the progress
 * popup where the commessa advances. The same section sits in the Info tab
 * and in Amministrazione.
 */
export function WorkOrderProgressSection({ workOrder, onChanged, className }: WorkOrderProgressSectionProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const lastRecordedOn = formatDate(workOrder.completion_recorded_on)

  return (
    <RecordSection
      title={t('workOrders.progress.action')}
      icon={<TrendingUp />}
      className={className}
      action={
        <Button type="button" variant="secondary" size="xs" onClick={() => setOpen(true)}>
          <ListChecks aria-hidden="true" />
          {t('workOrders.progress.manage')}
        </Button>
      }
    >
      <RecordFieldList>
        <RecordField label={t('workOrders.progress.total')}>
          <span className="font-medium tabular-nums">{workOrder.completion_percentage}%</span>
        </RecordField>
        <RecordField label={t('workOrders.progress.lastRecordedOn')}>
          {lastRecordedOn ? <span className="tabular-nums">{lastRecordedOn}</span> : <DetailEmpty />}
        </RecordField>
      </RecordFieldList>
      <WorkOrderProgressDialog
        workOrderId={open ? workOrder.id : null}
        onOpenChange={setOpen}
        onMutated={onChanged}
      />
    </RecordSection>
  )
}
