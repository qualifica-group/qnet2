import { useRef } from 'react'
import { useController, useFormContext } from 'react-hook-form'
import { QuoteWorkflowCriteriaEditor } from '@/features/quote-workflows/quote-workflow-criteria-editor'
import { useCriterionFields } from '@/features/quote-workflows/use-criterion-fields'
import { WorkflowStatusesEditor } from '@/features/quote-workflows/workflow-statuses-editor'
import {
  inOrder,
  newCustomRow,
  withCustomRow,
  withoutRow,
  withPatchedRow,
} from '@/features/quote-workflows/workflow-status-rows'
import type { QuoteWorkflowFormValues } from '@/features/quote-workflows/use-quote-workflow-form'
import type { WorkflowStatusRowPatch } from '@/features/quote-workflows/types'

/** The criteria field array bound to the surrounding form: open in the create form, inside the detail's open row. */
export function QuoteWorkflowCriteriaField() {
  return <QuoteWorkflowCriteriaEditor criterionFields={useCriterionFields()} />
}

/** The statuses list bound to the form's `statuses` value (so a cancelled row restores it with the rest). */
export function QuoteWorkflowStatusesField() {
  const { control } = useFormContext<QuoteWorkflowFormValues>()
  const { field, fieldState } = useController({ control, name: 'statuses' })
  const nextCustomRowId = useRef(0)

  const addCustom = () => {
    nextCustomRowId.current += 1
    field.onChange(withCustomRow(field.value, newCustomRow(nextCustomRowId.current)))
  }

  return (
    <WorkflowStatusesEditor
      rows={field.value}
      onReorder={(orderedIds) => field.onChange(inOrder(field.value, orderedIds))}
      onAddCustom={addCustom}
      onRemoveCustom={(id) => field.onChange(withoutRow(field.value, id))}
      onUpdateRow={(id: string, patch: WorkflowStatusRowPatch) => field.onChange(withPatchedRow(field.value, id, patch))}
      error={fieldState.error?.message ?? null}
    />
  )
}
