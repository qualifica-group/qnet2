import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { WorkOrderDetailHeader } from '@/features/work-orders/work-order-detail-header'
import { WorkOrderDetailSections } from '@/features/work-orders/work-order-detail-sections'
import { WorkOrderDetailTabs } from '@/features/work-orders/work-order-detail-tabs'
import { useWorkOrderInlineEdit } from '@/features/work-orders/use-work-order-inline-edit'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderDetailViewProps {
  workOrder: WorkOrderDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the work order (spec 0195 D-7). */
  onChanged?: () => void
  /** After "Elimina" in the actions menu: the host leaves the deleted record. */
  onDeleted?: () => void
  /** The tab to open first outside the work order's page (spec 0405). */
  initialTab?: string
}

/**
 * Detail of a single work order (AC-075), laid out like the legacy "Gestione
 * commessa" (user directives 2026-11-16, 2026-11-27): the identity band on
 * top with the "..." menu of the record actions (no KPI strip), then ONE card
 * of tabs (Info | Task | Documenti | Amministrazione | Note | Chat | Email |
 * Attivita', each with its own gate) with the work order title set apart at
 * the end of the strip; a metadata footer last. The Info tab carries the
 * record sections.
 *
 * There is no edit page (spec 0195 applied to Commesse, user directive
 * 2026-10-06): the sections' fields edit IN PLACE, one at a time
 * (`RecordInlineField`, driven by `useWorkOrderInlineEdit`), each save a
 * PATCH of that field alone.
 */
export function WorkOrderDetailView(props: WorkOrderDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.workOrder.permissions}>
      <WorkOrderDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function WorkOrderDetailContent({ workOrder, onChanged, onDeleted, initialTab }: WorkOrderDetailViewProps) {
  const { t } = useTranslation()
  const editor = useWorkOrderInlineEdit(workOrder, onChanged)
  const createdAt = formatDateTime(workOrder.created_at)
  const updatedAt = formatDateTime(workOrder.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        header={<WorkOrderDetailHeader workOrder={workOrder} onChanged={onChanged} onDeleted={onDeleted} />}
      />

      <WorkOrderDetailTabs
        workOrder={workOrder}
        initialTab={initialTab}
        onChanged={onChanged}
        info={
          /* Provider only (no DOM): the inline editors share the edit form. */
          <Form {...editor.form}>
            <WorkOrderDetailSections workOrder={workOrder} editor={editor} onChanged={onChanged} />
          </Form>
        }
      />

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
