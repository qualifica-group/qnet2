import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { useAbilities } from '@/features/auth/use-abilities'
import { useWorkOrderCollaborationGates } from '@/features/work-orders/use-work-order-collaboration-gates'
import { WorkOrderCollaborationSection } from '@/features/work-orders/work-order-collaboration-section'
import { WorkOrderDetailHeader, WorkOrderDetailStats } from '@/features/work-orders/work-order-detail-header'
import { WorkOrderDetailSections } from '@/features/work-orders/work-order-detail-sections'
import { WorkOrderTaskBoard } from '@/features/work-orders/task-board/work-order-task-board'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderDetailViewProps {
  workOrder: WorkOrderDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single work order (AC-075), laid out exactly like the
 * Opportunita' record: on the left ONE card carrying identity header, KPI strip
 * and titled sections; the collaboration card (notes, documents, activity log,
 * spec 0134 D-3) on the right; the Task board (spec 0146, D-10: replaces the
 * old `TableView domain="tasks"` panel) full width below both, only with
 * `tasks.viewAny` (AC-024); a metadata footer last.
 */
export function WorkOrderDetailView({ workOrder, onEdit }: WorkOrderDetailViewProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { hasAny: hasCollaboration } = useWorkOrderCollaborationGates(workOrder)
  const createdAt = formatDateTime(workOrder.created_at)
  const updatedAt = formatDateTime(workOrder.updated_at)

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, hasCollaboration && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <WorkOrderDetailHeader workOrder={workOrder} onEdit={onEdit} />
            <WorkOrderDetailStats workOrder={workOrder} />
            <WorkOrderDetailSections workOrder={workOrder} />
          </RecordCard>
        </div>

        {hasCollaboration ? (
          <div className={RECORD_COLUMN_CLASS}>
            <WorkOrderCollaborationSection workOrder={workOrder} />
          </div>
        ) : null}
      </div>

      {can('tasks.viewAny') ? <WorkOrderTaskBoard workOrderId={workOrder.id} /> : null}

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('workOrders.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('workOrders.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
