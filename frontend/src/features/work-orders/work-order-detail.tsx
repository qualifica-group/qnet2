import { useTranslation } from 'react-i18next'
import { MessagesSquare, Paperclip } from 'lucide-react'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { useRegistryDocumentsTab } from '@/features/registries/use-registry-documents-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { useWorkOrderEmailsTab } from '@/features/work-order-emails/use-work-order-emails-tab'
import { WORK_ORDER_ATTACHABLE_ALIAS, WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { WorkOrderDetailHeader, WorkOrderDetailStats } from '@/features/work-orders/work-order-detail-header'
import { WorkOrderDetailSections } from '@/features/work-orders/work-order-detail-sections'
import { WorkOrderDetailWorkTabs } from '@/features/work-orders/work-order-detail-work-tabs'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderDetailViewProps {
  workOrder: WorkOrderDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * The record's collaboration tabs (Notes | Documents | Registry documents |
 * Email | Activity), each gated by its OWN authorization source and absent
 * entirely when unauthorized (spec 0134 D-3; the read-only registry one is
 * spec 0173; Email is spec 0175).
 */
function useCollaborationTabs(workOrder: WorkOrderDetailWithPermissions): RecordCollaborationTab[] {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const registryDocumentsTab = useRegistryDocumentsTab(workOrder.registry?.id)
  const workOrderEmailsTab = useWorkOrderEmailsTab(
    workOrder.id,
    workOrder.permissions.actions.view_emails,
    workOrder.permissions.actions.send_email,
  )
  const tabs: RecordCollaborationTab[] = []

  /*
   * Notes are gated on the resource permission alone: the membership half of
   * the server rule (`WorkOrderNotable::authorizeRead`) is not evaluable
   * client-side, and `NotesSection` owns its own error state for that
   * residual case.
   */
  if (can('work-orders.view')) {
    tabs.push({
      value: 'notes',
      label: t('notes.section.title'),
      icon: <MessagesSquare className="size-3.5" aria-hidden="true" />,
      content: <NotesSection entityType={WORK_ORDERS_DOMAIN} entityId={workOrder.id} showHeader={false} />,
    })
  }

  if (workOrder.permissions.actions.view_documents) {
    tabs.push({
      value: 'documents',
      label: t('attachments.title'),
      icon: <Paperclip className="size-3.5" aria-hidden="true" />,
      content: (
        <DocumentsSection
          resource={WORK_ORDER_ATTACHABLE_ALIAS}
          id={workOrder.id}
          canUpload={can('attachments.create')}
          canDelete={can('attachments.delete')}
        />
      ),
    })
  }

  if (registryDocumentsTab) {
    tabs.push(registryDocumentsTab)
  }

  if (workOrderEmailsTab) {
    tabs.push(workOrderEmailsTab)
  }

  if (workOrder.permissions.actions.view_activity) {
    tabs.push(activityLogTab(WORK_ORDERS_DOMAIN, workOrder.id, t('activityLog.title')))
  }

  return tabs
}

/**
 * Read-only detail of a single work order (AC-075), laid out exactly like the
 * Opportunita' record: on the left ONE card carrying identity header, KPI strip
 * and titled sections; the collaboration card (notes, documents, activity log,
 * spec 0134 D-3) on the right; full width below both, ONE card switching
 * between the Task board (spec 0146, only with `tasks.viewAny`, AC-024; open
 * by default) and the Costi section (spec 0190, only with `view_costs`); a
 * metadata footer last.
 */
export function WorkOrderDetailView({ workOrder, onEdit }: WorkOrderDetailViewProps) {
  const { t } = useTranslation()
  const collaborationTabs = useCollaborationTabs(workOrder)
  const createdAt = formatDateTime(workOrder.created_at)
  const updatedAt = formatDateTime(workOrder.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <WorkOrderDetailHeader workOrder={workOrder} onEdit={onEdit} />
          <WorkOrderDetailStats workOrder={workOrder} />
          <WorkOrderDetailSections workOrder={workOrder} />
        </RecordCard>
      </RecordBody>

      <WorkOrderDetailWorkTabs workOrder={workOrder} />

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
