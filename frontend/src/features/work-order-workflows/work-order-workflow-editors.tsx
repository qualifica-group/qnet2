import { useRef } from 'react'
import { useController, useFormContext } from 'react-hook-form'
import { QuoteWorkflowCriteriaEditor } from '@/features/quote-workflows/quote-workflow-criteria-editor'
import { useCriterionFields } from '@/features/work-order-workflows/use-criterion-fields'
import { newCustomRow } from '@/features/work-order-workflows/work-order-status-rows'
import { useWorkOrderStatusGroups } from '@/features/work-order-workflows/use-work-order-status-groups'
import { WorkflowStatusesEditor } from '@/features/workflow-statuses/workflow-statuses-editor'
import { inOrder, withCustomRow, withoutRow, withPatchedRow } from '@/features/workflow-statuses/workflow-status-rows'
import type { WorkOrderWorkflowFormValues } from '@/features/work-order-workflows/work-order-workflow-schema'
import { isTailWorkOrderSystemKey, type WorkOrderStatusRowPatch } from '@/features/work-order-workflows/types'

/**
 * The criteria field array bound to the surrounding form. The editor is the
 * one of the quote configurator: it is domain-agnostic (field array of
 * `{field, value_id}` over the form context), only the field options differ.
 */
export function WorkOrderWorkflowCriteriaField() {
  return <QuoteWorkflowCriteriaEditor criterionFields={useCriterionFields()} />
}

/** The statuses list bound to the form's `statuses` value (so a cancelled row restores it with the rest). */
export function WorkOrderWorkflowStatusesField() {
  const { control } = useFormContext<WorkOrderWorkflowFormValues>()
  const { field, fieldState } = useController({ control, name: 'statuses' })
  const groups = useWorkOrderStatusGroups()
  const nextCustomRowId = useRef(0)

  const addCustom = () => {
    nextCustomRowId.current += 1
    field.onChange(withCustomRow(field.value, newCustomRow(nextCustomRowId.current), isTailWorkOrderSystemKey))
  }

  return (
    <WorkflowStatusesEditor
      rows={field.value}
      groups={groups}
      showProgressPercentage
      onReorder={(orderedIds) => field.onChange(inOrder(field.value, orderedIds))}
      onAddCustom={addCustom}
      onRemoveCustom={(id) => field.onChange(withoutRow(field.value, id))}
      onUpdateRow={(id: string, patch: WorkOrderStatusRowPatch) => field.onChange(withPatchedRow(field.value, id, patch))}
      error={fieldState.error?.message ?? null}
    />
  )
}
