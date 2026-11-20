import { useRef } from 'react'
import { useController, useFormContext } from 'react-hook-form'
import { QuoteWorkflowCriteriaEditor } from '@/features/quote-workflows/quote-workflow-criteria-editor'
import { useCriterionFields } from '@/features/quote-workflows/use-criterion-fields'
import { newCustomRow } from '@/features/quote-workflows/quote-status-rows'
import { useQuoteSystemStatusOptions } from '@/features/quote-system-statuses/use-quote-system-status-options'
import { WorkflowStatusesEditor } from '@/features/workflow-statuses/workflow-statuses-editor'
import { inOrder, withCustomRow, withoutRow, withPatchedRow } from '@/features/workflow-statuses/workflow-status-rows'
import type { QuoteWorkflowFormValues } from '@/features/quote-workflows/use-quote-workflow-form'
import { isTailWorkflowSystemKey, type WorkflowStatusRowPatch } from '@/features/quote-workflows/types'

/** The criteria field array bound to the surrounding form: open in the create form, inside the detail's open row. */
export function QuoteWorkflowCriteriaField() {
  return <QuoteWorkflowCriteriaEditor criterionFields={useCriterionFields()} />
}

/** The statuses list bound to the form's `statuses` value (so a cancelled row restores it with the rest). */
export function QuoteWorkflowStatusesField() {
  const { control } = useFormContext<QuoteWorkflowFormValues>()
  const { field, fieldState } = useController({ control, name: 'statuses' })
  const { options } = useQuoteSystemStatusOptions()
  const nextCustomRowId = useRef(0)

  const addCustom = () => {
    nextCustomRowId.current += 1
    field.onChange(withCustomRow(field.value, newCustomRow(nextCustomRowId.current), isTailWorkflowSystemKey))
  }

  return (
    <WorkflowStatusesEditor
      rows={field.value}
      systemStatuses={options}
      showRequiresNote
      showNotify
      onReorder={(orderedIds) => field.onChange(inOrder(field.value, orderedIds))}
      onAddCustom={addCustom}
      onRemoveCustom={(id) => field.onChange(withoutRow(field.value, id))}
      onUpdateRow={(id: string, patch: WorkflowStatusRowPatch) => field.onChange(withPatchedRow(field.value, id, patch))}
      error={fieldState.error?.message ?? null}
    />
  )
}
