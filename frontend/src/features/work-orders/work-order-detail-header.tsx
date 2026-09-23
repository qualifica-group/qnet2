import { useTranslation } from 'react-i18next'
import { CalendarClock, CalendarDays, CheckCircle2, FileSignature, Hammer } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'
import { WorkOrderCompletionBar } from '@/features/work-orders/work-order-completion-bar'
import type { WorkOrderDetailWithPermissions, WorkOrderStatusValue, WorkOrderType } from '@/features/work-orders/types'

/**
 * Identity band and KPI strip of the work order record card, mirroring
 * `OpportunityDetailHeader`/`QuoteDetailHeader`: both read the same handful of
 * top-level fields and are always mounted together.
 */

/** Badge colour per computed status (spec 0149 D-9), the same tokens the grid's `#[Color]` badges use. */
const STATUS_TONES: Record<WorkOrderStatusValue, { badge: string; dot: string }> = {
  open: { badge: BADGE_COLOR_CLASSES.blue, dot: 'bg-blue-500' },
  in_progress: { badge: BADGE_COLOR_CLASSES.amber, dot: 'bg-amber-500' },
  completed: { badge: BADGE_COLOR_CLASSES.green, dot: 'bg-green-500' },
  closed: { badge: BADGE_COLOR_CLASSES.slate, dot: 'bg-slate-500' },
}

/** Colored status pill for the record header (spec 0149: calculated from the tasks, read-only). */
function WorkOrderStatusBadge({ status }: { status: WorkOrderStatusValue }) {
  const { t } = useTranslation()
  const tone = STATUS_TONES[status]
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', tone.badge)}>
      <span className={cn('size-1.5 shrink-0 rounded-full', tone.dot)} aria-hidden="true" />
      {t(`enums.work_order_status.${status}`)}
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
  const canEdit = workOrder.permissions.resource.update

  return (
    <RecordCardHeader
      media={
        <DetailMonogram name={workOrder.title} icon={<Hammer />} className="size-10 text-base [&>svg]:size-5" />
      }
      title={workOrder.title}
      subtitle={workOrder.code}
      badges={
        <>
          <WorkOrderStatusBadge status={workOrder.status.value} />
          <WorkOrderTypeBadge type={workOrder.type} />
        </>
      }
      actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
    />
  )
}

/** KPI strip: completion, start date, callback date and contract number. */
export function WorkOrderDetailStats({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()
  const startDate = formatDate(workOrder.start_date)
  const callbackDate = formatDate(workOrder.callback_date)

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('workOrders.columns.completion_percentage')}
        icon={<CheckCircle2 aria-hidden="true" />}
        value={<WorkOrderCompletionBar value={workOrder.completion_percentage} />}
      />
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
    </RecordStatStrip>
  )
}
