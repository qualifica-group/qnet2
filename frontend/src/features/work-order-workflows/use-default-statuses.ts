import { useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { fetchDefaultStatuses, updateDefaultStatuses, workOrderWorkflowKeys } from '@/features/work-order-workflows/api'
import { buildDefaultStatusesPayload } from '@/features/work-order-workflows/work-order-workflow-form-payload'
import { newCustomRow, statusRowsFromItems } from '@/features/work-order-workflows/work-order-status-rows'
import {
  isTailWorkOrderSystemKey,
  type WorkOrderStatusFormRow,
  type WorkOrderStatusRowPatch,
  type WorkOrderWorkflowStatusItem,
} from '@/features/work-order-workflows/types'
import { inOrder, withCustomRow, withoutRow, withPatchedRow } from '@/features/workflow-statuses/workflow-status-rows'

interface UseDefaultStatusesLabels {
  saved: string
  genericError: string
  nameRequired: string
}

interface UseDefaultStatusesArgs {
  /** Gates the fetch — only load while the sheet is open. */
  enabled: boolean
  labels: UseDefaultStatusesLabels
}

/**
 * Owns every non-render concern of `DefaultStatusesSheet` for work orders:
 * loads the GLOBAL default status set, mirrors it into local editable rows
 * for `<WorkflowStatusesEditor>`, and persists an explicit Save via `PUT
 * /work-order-workflows/default-statuses`.
 */
export function useDefaultStatuses({ enabled, labels }: UseDefaultStatusesArgs) {
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: workOrderWorkflowKeys.defaultStatuses,
    queryFn: fetchDefaultStatuses,
    enabled,
  })

  const [rows, setRows] = useState<WorkOrderStatusFormRow[]>([])
  const [syncedFrom, setSyncedFrom] = useState<WorkOrderWorkflowStatusItem[] | undefined>(undefined)
  if (query.data && query.data !== syncedFrom) {
    setSyncedFrom(query.data)
    setRows(statusRowsFromItems(query.data))
  }

  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const nextCustomRowId = useRef(0)

  const addCustom = () => {
    nextCustomRowId.current += 1
    const newRow = newCustomRow(nextCustomRowId.current)
    setRows((current) => withCustomRow(current, newRow, isTailWorkOrderSystemKey))
  }

  const removeCustom = (id: string) => {
    setRows((current) => withoutRow(current, id))
  }

  const updateRow = (id: string, patch: WorkOrderStatusRowPatch) => {
    setRows((current) => withPatchedRow(current, id, patch))
  }

  const reorder = (orderedIds: string[]) => {
    setRows((current) => inOrder(current, orderedIds))
  }

  const save = async (): Promise<boolean> => {
    const hasEmptyCustomName = rows.some((row) => row.system_key === null && row.name.trim() === '')
    if (hasEmptyCustomName) {
      setError(labels.nameRequired)
      return false
    }
    setError(null)
    setIsSaving(true)
    try {
      const fresh = await updateDefaultStatuses(buildDefaultStatusesPayload(rows))
      setRows(statusRowsFromItems(fresh))
      setSyncedFrom(fresh)
      queryClient.setQueryData(workOrderWorkflowKeys.defaultStatuses, fresh)
      toast.success(labels.saved)
      return true
    } catch (caughtError) {
      toast.error(apiErrorMessage(caughtError, labels.genericError, i18n))
      return false
    } finally {
      setIsSaving(false)
    }
  }

  return {
    rows,
    isLoading: query.isLoading,
    isError: query.isError,
    refetch: query.refetch,
    isSaving,
    error,
    addCustom,
    removeCustom,
    updateRow,
    reorder,
    save,
  }
}
