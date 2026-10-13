import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { WorkOrderPaymentStatusRows } from '@/features/work-order-payment-statuses/work-order-payment-status-rows'
import { useWorkOrderPaymentStatusForm, type WorkOrderPaymentStatusFormValues } from '@/features/work-order-payment-statuses/use-work-order-payment-status-form'
import type { WorkOrderPaymentStatusDetailWithPermissions, WorkOrderPaymentStatusFormMode } from '@/features/work-order-payment-statuses/types'

interface WorkOrderPaymentStatusDetailViewProps {
  workOrderPaymentStatus: WorkOrderPaymentStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single work order payment status on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata
 * footer. There is no edit page (spec 0235): every row edits IN PLACE, one at
 * a time, each save a PATCH of that field alone.
 */
export function WorkOrderPaymentStatusDetailView(props: WorkOrderPaymentStatusDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.workOrderPaymentStatus.permissions}>
      <WorkOrderPaymentStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function WorkOrderPaymentStatusDetailContent({ workOrderPaymentStatus, onChanged }: WorkOrderPaymentStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<WorkOrderPaymentStatusFormMode>(() => ({ type: 'edit', workOrderPaymentStatus }), [workOrderPaymentStatus])
  const { form, onSubmit, serverError, clearServerError } = useWorkOrderPaymentStatusForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<WorkOrderPaymentStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('workOrderPaymentStatuses.form.genericError'),
  })
  const canEdit = workOrderPaymentStatus.permissions.resource.update
  const createdAt = formatDateTime(workOrderPaymentStatus.created_at)
  const updatedAt = formatDateTime(workOrderPaymentStatus.updated_at)
  const tabs = workOrderPaymentStatus.permissions.actions.view_activity
    ? [activityLogTab('work-order-payment-statuses', workOrderPaymentStatus.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader media={<DetailMonogram name={workOrderPaymentStatus.name} icon={<Flag />} />} title={workOrderPaymentStatus.name} legacyId={workOrderPaymentStatus.old_id} />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('workOrderPaymentStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <WorkOrderPaymentStatusRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('workOrderPaymentStatuses.detail.sort_order')}>{workOrderPaymentStatus.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('workOrderPaymentStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('workOrderPaymentStatuses.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
