import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Badge } from '@/components/ui/badge'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import {
  WorkOrderWorkflowCriteriaRow,
  WorkOrderWorkflowGeneralRows,
  WorkOrderWorkflowStatusesRow,
} from '@/features/work-order-workflows/work-order-workflow-rows'
import { useWorkOrderWorkflowForm } from '@/features/work-order-workflows/use-work-order-workflow-form'
import type { WorkOrderWorkflowFormValues } from '@/features/work-order-workflows/work-order-workflow-schema'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { WorkOrderWorkflowDetailWithPermissions, WorkOrderWorkflowFormMode } from '@/features/work-order-workflows/types'

interface WorkOrderWorkflowDetailViewProps {
  workOrderWorkflow: WorkOrderWorkflowDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record . */
  onChanged?: () => void
}

/**
 * Detail of a single work order workflow on the record kit: identity band,
 * the "Dettagli" rows, its criteria and its status set as full-width rows
 * (the persisted view until their pencil opens the editor creation uses), the
 * activity card on the right, a metadata footer. There is no edit page (spec
 * 0242): every row edits IN PLACE, one at a time, each save a PATCH of that
 * row's fields alone.
 */
export function WorkOrderWorkflowDetailView(props: WorkOrderWorkflowDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.workOrderWorkflow.permissions}>
      <WorkOrderWorkflowDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function WorkOrderWorkflowDetailContent({ workOrderWorkflow, onChanged }: WorkOrderWorkflowDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<WorkOrderWorkflowFormMode>(() => ({ type: 'edit', workOrderWorkflow }), [workOrderWorkflow])
  const { form, onSubmit, serverError, clearServerError } = useWorkOrderWorkflowForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<WorkOrderWorkflowFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('workOrderWorkflows.form.genericError'),
  })
  const canEdit = workOrderWorkflow.permissions.resource.update
  const createdAt = formatDateTime(workOrderWorkflow.created_at)
  const tabs: RecordCollaborationTab[] = workOrderWorkflow.permissions.actions.view_activity
    ? [activityLogTab('work-order-workflows', workOrderWorkflow.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={workOrderWorkflow.name} icon={<Workflow />} />}
            title={workOrderWorkflow.name}
            badges={
              <Badge variant={workOrderWorkflow.is_active ? 'default' : 'secondary'}>
                {t(workOrderWorkflow.is_active ? 'workOrderWorkflows.detail.active' : 'workOrderWorkflows.detail.inactive')}
              </Badge>
            }
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('workOrderWorkflows.form.sections.identity.title')} full>
              <RecordFieldList>
                <WorkOrderWorkflowGeneralRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('workOrderWorkflows.detail.criteriaTitle')} full>
              <WorkOrderWorkflowCriteriaRow inline={inline} canEdit={canEdit} workOrderWorkflow={workOrderWorkflow} />
            </RecordSection>
            <RecordSection title={t('workOrderWorkflows.detail.statusesTitle')} full>
              <WorkOrderWorkflowStatusesRow inline={inline} canEdit={canEdit} workOrderWorkflow={workOrderWorkflow} />
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('workOrderWorkflows.detail.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
