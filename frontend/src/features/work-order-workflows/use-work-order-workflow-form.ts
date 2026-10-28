import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createWorkOrderWorkflow, updateWorkOrderWorkflow, workOrderWorkflowKeys } from '@/features/work-order-workflows/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/work-order-workflows/work-order-workflow-form-payload'
import {
  buildWorkOrderWorkflowSchema,
  type WorkOrderWorkflowFormValues,
} from '@/features/work-order-workflows/work-order-workflow-schema'
import { initialSystemStatusRows, statusRowsFromDetail } from '@/features/work-order-workflows/work-order-status-rows'
import type { WorkOrderWorkflowDetail, WorkOrderWorkflowFormMode } from '@/features/work-order-workflows/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'is_active', 'criteria', 'statuses'] as const

/** Collections whose server errors arrive per row (`criteria.0.field`) but show once, on the collection. */
const NESTED_ERROR_ROOTS = ['criteria', 'statuses'] as const

/** A fresh, still-empty criteria row. */
const EMPTY_CRITERION_ROW = { field: null, value_id: null }

/** Surfaces the first per-row message of a collection when the server sent none for the collection itself. */
function applyNestedServerErrors(error: unknown, setError: UseFormSetError<WorkOrderWorkflowFormValues>): void {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return
  }
  const errors = (error.response.data?.errors ?? {}) as Record<string, string[]>
  for (const root of NESTED_ERROR_ROOTS) {
    const key = Object.keys(errors).find((candidate) => candidate.startsWith(`${root}.`))
    if (key && errors[root] === undefined && errors[key]?.[0]) {
      setError(root, { message: errors[key][0] })
    }
  }
}

interface UseWorkOrderWorkflowFormArgs {
  mode: WorkOrderWorkflowFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (workOrderWorkflow: WorkOrderWorkflowDetail) => void
}

/**
 * Owns every non-render concern of the work order workflow form: RHF/Zod
 * wiring, 422 mapping and the create/update submit. Create backs
 * `WorkOrderWorkflowForm`; edit backs the detail's in-place rows (diff PATCH).
 */
export function useWorkOrderWorkflowForm({ mode, onSuccess }: UseWorkOrderWorkflowFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'
  const schema = useMemo(() => buildWorkOrderWorkflowSchema(t), [t])

  const defaultValues = useMemo<WorkOrderWorkflowFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.workOrderWorkflow.name,
        is_active: mode.workOrderWorkflow.is_active,
        criteria: mode.workOrderWorkflow.criteria.map((criterion) => ({
          field: criterion.field,
          value_id: criterion.value_id,
        })),
        statuses: statusRowsFromDetail(mode.workOrderWorkflow),
      }
    }
    return {
      name: '',
      is_active: true,
      criteria: [{ ...EMPTY_CRITERION_ROW }],
      statuses: initialSystemStatusRows(t),
    }
  }, [mode, t])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<WorkOrderWorkflowFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: WorkOrderWorkflowFormValues) => {
    setServerError(null)
    const errorFields: Path<WorkOrderWorkflowFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateWorkOrderWorkflow(mode.workOrderWorkflow.id, buildUpdatePayload(values, mode.workOrderWorkflow))
        queryClient.setQueryData(workOrderWorkflowKeys.detail(mode.workOrderWorkflow.id), saved)
        toast.success(t('workOrderWorkflows.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createWorkOrderWorkflow(buildCreatePayload(values))
      toast.success(t('workOrderWorkflows.form.created'))
      onSuccess(created)
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, errorFields)
      applyNestedServerErrors(error, form.setError)
      if (!handled) {
        setServerError(t('workOrderWorkflows.form.genericError'))
      }
    }
  }

  return {
    form,
    serverError,
    clearServerError: () => setServerError(null),
    onSubmit,
  }
}
