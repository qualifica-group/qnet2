import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList } from '@/components/detail/record-panel'
import { SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import {
  WorkOrderWorkflowCriteriaField,
  WorkOrderWorkflowStatusesField,
} from '@/features/work-order-workflows/work-order-workflow-editors'
import type { WorkOrderWorkflowFormValues } from '@/features/work-order-workflows/work-order-workflow-schema'
import type { WorkOrderWorkflowDetail } from '@/features/work-order-workflows/types'
import { cn } from '@/lib/utils'

interface RowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** Name and active flag, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function WorkOrderWorkflowGeneralRows({ inline, canEdit }: RowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<WorkOrderWorkflowFormValues>
        name="name"
        label={t('workOrderWorkflows.form.name')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<WorkOrderWorkflowFormValues>
        name="is_active"
        label={t('workOrderWorkflows.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}

interface DetailRowProps extends RowsProps {
  workOrderWorkflow: WorkOrderWorkflowDetail
}

/** The detail's criteria: the persisted field/value pairs until the pencil opens the same editor as creation. */
export function WorkOrderWorkflowCriteriaRow({ inline, canEdit, workOrderWorkflow }: DetailRowProps) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="criteria"
      label={t('workOrderWorkflows.detail.criteriaTitle')}
      layout="block"
      inline={inline}
      canEdit={canEdit}
      editor={<WorkOrderWorkflowCriteriaField />}
    >
      {workOrderWorkflow.criteria.length === 0 ? (
        <DetailEmpty />
      ) : (
        <RecordFieldList>
          {workOrderWorkflow.criteria.map((criterion) => (
            <RecordField
              key={criterion.id}
              label={t(`workOrderWorkflows.criterionFields.${criterion.field}`)}
            >
              {criterion.value_label}
            </RecordField>
          ))}
        </RecordFieldList>
      )}
    </RecordInlineField>
  )
}

/** The detail's statuses: the persisted set with its groups until the pencil opens the same editor as creation. */
export function WorkOrderWorkflowStatusesRow({ inline, canEdit, workOrderWorkflow }: DetailRowProps) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="statuses"
      label={t('workOrderWorkflows.detail.statusesTitle')}
      layout="block"
      inline={inline}
      canEdit={canEdit}
      editor={<WorkOrderWorkflowStatusesField />}
    >
      {workOrderWorkflow.statuses.length === 0 ? (
        <DetailEmpty />
      ) : (
        <ul className="flex flex-col gap-1.5">
          {workOrderWorkflow.statuses.map((status) => (
            <li key={status.id} className="flex items-center gap-2 text-sm">
              <span
                className={cn('size-2.5 shrink-0 rounded-full border', swatchClassFor(status.color) ?? 'bg-transparent')}
                aria-hidden="true"
              />
              <span className="flex-1 truncate">{status.name}</span>
              {status.progress_percentage !== null ? (
                <span className="shrink-0 text-xs tabular-nums text-muted-foreground" title={t('workflowStatuses.progressPercentage')}>
                  {status.progress_percentage}%
                </span>
              ) : null}
              <Badge variant="secondary" className="shrink-0">
                {t(`workOrderWorkflows.form.statuses.group.${status.group}`)}
              </Badge>
            </li>
          ))}
        </ul>
      )}
    </RecordInlineField>
  )
}
