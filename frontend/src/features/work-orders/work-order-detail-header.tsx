import { useTranslation } from 'react-i18next'
import { Boxes, CalendarClock, CalendarDays, FileSignature, Hammer, Pencil } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'
import type { WorkOrderDetailWithPermissions, WorkOrderType } from '@/features/work-orders/types'

/**
 * Identity band and KPI strip of the work order record card, mirroring
 * `OpportunityDetailHeader`/`QuoteDetailHeader`: both read the same handful of
 * top-level fields and are always mounted together.
 */

/** Colored status pill for the record header (D-3: calculated, sola lettura). */
function WorkOrderStatusBadge({ isClosed }: { isClosed: boolean }) {
  const { t } = useTranslation()
  return (
    <Badge
      variant="secondary"
      className={cn(BADGE_BASE, 'gap-1.5', isClosed ? BADGE_COLOR_CLASSES.slate : BADGE_COLOR_CLASSES.green)}
    >
      <span
        className={cn('size-1.5 shrink-0 rounded-full', isClosed ? 'bg-slate-500' : 'bg-green-500')}
        aria-hidden="true"
      />
      {t(isClosed ? 'workOrders.detail.statusClosed' : 'workOrders.detail.statusOpen')}
    </Badge>
  )
}

/** "Tipo commessa" pill for the record header (D-10). */
function WorkOrderTypeBadge({ type }: { type: WorkOrderType }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, BADGE_COLOR_CLASSES.blue)}>
      {t(`workOrders.options.type.${type}`)}
    </Badge>
  )
}

interface WorkOrderDetailHeaderProps {
  workOrder: WorkOrderDetailWithPermissions
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/** Identity band: monogram, title, code subtitle, status/type pills, edit action. */
export function WorkOrderDetailHeader({ workOrder, onEdit }: WorkOrderDetailHeaderProps) {
  const { t } = useTranslation()
  const canEdit = Boolean(onEdit) && workOrder.permissions.resource.update

  return (
    <RecordCardHeader
      media={
        <DetailMonogram name={workOrder.title} icon={<Hammer />} className="size-10 text-base [&>svg]:size-5" />
      }
      title={workOrder.title}
      subtitle={workOrder.code}
      badges={
        <>
          <WorkOrderStatusBadge isClosed={workOrder.status.value === 'closed'} />
          <WorkOrderTypeBadge type={workOrder.type} />
        </>
      }
      actions={
        canEdit ? (
          <Button size="sm" onClick={onEdit}>
            <Pencil aria-hidden="true" />
            {t('common.edit')}
          </Button>
        ) : null
      }
    />
  )
}

/** KPI strip: start date, callback date, contract number and how many product lines the work order covers. */
export function WorkOrderDetailStats({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()
  const startDate = formatDate(workOrder.start_date)
  const callbackDate = formatDate(workOrder.callback_date)

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('workOrders.detail.startDate')}
        icon={<CalendarDays aria-hidden="true" />}
        value={startDate || <DetailEmpty />}
      />
      <RecordStat
        label={t('workOrders.detail.callbackDate')}
        icon={<CalendarClock aria-hidden="true" />}
        value={callbackDate || <DetailEmpty />}
      />
      <RecordStat
        label={t('workOrders.detail.contractNumber')}
        icon={<FileSignature aria-hidden="true" />}
        value={workOrder.contract_number ?? <DetailEmpty />}
      />
      <RecordStat
        label={t('workOrders.detail.lines')}
        icon={<Boxes aria-hidden="true" />}
        value={workOrder.quote_lines.length}
      />
    </RecordStatStrip>
  )
}
