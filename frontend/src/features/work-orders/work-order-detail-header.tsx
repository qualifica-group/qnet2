import { useTranslation } from 'react-i18next'
import { Hammer } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'
import { WorkOrderActionsMenu } from '@/features/work-orders/work-order-actions-menu'
import { WorkOrderWorkflowStatusMenu } from '@/features/work-orders/work-order-workflow-status-menu'
import type { WorkOrderDetailWithPermissions, WorkOrderWorkPhase } from '@/features/work-orders/types'

/** Badge colour per working phase (spec 0395), the same tokens as the grid's `#[Color]` badges of `WorkOrderWorkPhase`. */
const STATUS_TONES: Record<WorkOrderWorkPhase, { badge: string; dot: string }> = {
  to_plan: { badge: BADGE_COLOR_CLASSES.blue, dot: 'bg-blue-500' },
  in_progress: { badge: BADGE_COLOR_CLASSES.amber, dot: 'bg-amber-500' },
  completed: { badge: BADGE_COLOR_CLASSES.slate, dot: 'bg-slate-500' },
  cancelled: { badge: BADGE_COLOR_CLASSES.red, dot: 'bg-red-500' },
  terminated: { badge: BADGE_COLOR_CLASSES.gray, dot: 'bg-gray-700' },
}

/** Colored status pill for the record header: the working phase (spec 0395), read-only. */
function WorkOrderStatusBadge({ status }: { status: WorkOrderWorkPhase }) {
  const { t } = useTranslation()
  const tone = STATUS_TONES[status]
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', tone.badge)}>
      <span className={cn('size-1.5 shrink-0 rounded-full', tone.dot)} aria-hidden="true" />
      {t(`enums.work_order_work_phase.${status}`)}
    </Badge>
  )
}

const ACTIONS_CLASS = 'flex max-w-[45vw] flex-wrap items-center justify-end gap-1.5 sm:max-w-none'

/** Spec 0267: marks a work order whose lines come from several offers. */
function WorkOrderMultiQuoteBadge() {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, BADGE_COLOR_CLASSES.amber)}>
      {t('workOrders.detail.multiQuoteBadge')}
    </Badge>
  )
}

interface WorkOrderDetailHeaderProps {
  workOrder: WorkOrderDetailWithPermissions
  /** After a record action changed the work order (spec 0195 D-7: the host refreshes its lists). */
  onChanged?: () => void
  /** After "Elimina": the host leaves the record. Absent, no delete is offered. */
  onDeleted?: () => void
}

/**
 * Identity band of the work order record card: monogram, title, the client
 * company as subtitle (user directive 2026-11-18, it replaces the code; the
 * code only when there is no client). Like the legacy "Gestione commessa"
 * (user directives 2026-11-27): no type pill, no KPI strip, no row of action
 * buttons; top-right the "Stato lavorazione" pill that changes it (user
 * directive 2026-11-27), the status pill and the "..." menu that folds the
 * record actions (`WorkOrderActionsMenu`). The fields edit in place below.
 */
export function WorkOrderDetailHeader({ workOrder, onChanged, onDeleted }: WorkOrderDetailHeaderProps) {
  return (
    <RecordCardHeader
      media={
        <DetailMonogram name={workOrder.title} icon={<Hammer />} className="size-10 text-base [&>svg]:size-5" />
      }
      title={workOrder.title}
      legacyId={workOrder.old_id}
      subtitle={workOrder.registry?.name ?? workOrder.code}
      badges={workOrder.is_multi_quote ? <WorkOrderMultiQuoteBadge /> : undefined}
      actions={
        // Capped on phones so the two pills wrap under each other instead of squeezing the title away.
        <div className={ACTIONS_CLASS}>
          <WorkOrderWorkflowStatusMenu workOrder={workOrder} onChanged={onChanged} />
          <WorkOrderStatusBadge status={workOrder.status.value} />
          <WorkOrderActionsMenu workOrder={workOrder} onChanged={onChanged} onDeleted={onDeleted} />
        </div>
      }
    />
  )
}
