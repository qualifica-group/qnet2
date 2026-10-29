import { useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { fetchDefaultStatuses, updateDefaultStatuses } from '@/features/quote-workflows/api'
import { buildDefaultStatusesPayload } from '@/features/quote-workflows/quote-workflow-form-payload'
import { newCustomRow, statusRowsFromItems } from '@/features/quote-workflows/quote-status-rows'
import {
  isTailWorkflowSystemKey,
  type QuoteWorkflowStatusItem,
  type WorkflowStatusFormRow,
  type WorkflowStatusRowPatch,
} from '@/features/quote-workflows/types'
import { inOrder, withCustomRow, withoutRow, withPatchedRow } from '@/features/workflow-statuses/workflow-status-rows'

/** Query key for the GLOBAL default status set (fresh-on-open pattern, mirrors `useStatusReorder`). */
function defaultStatusesQueryKey() {
  return ['quote-workflows', 'default-statuses'] as const
}

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
 * Owns every non-render concern of `DefaultStatusesSheet` (spec 0047 Lane C):
 * loads the GLOBAL default status set, mirrors it into local editable rows
 * for `<WorkflowStatusesEditor>`, and persists an explicit Save via `PUT
 * /quote-workflows/default-statuses` (unlike the per-drag auto-save of
 * `useStatusReorder`, since this editor also edits name/color/group, not
 * just order).
 */
export function useDefaultStatuses({ enabled, labels }: UseDefaultStatusesArgs) {
  const { i18n } = useTranslation()
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: defaultStatusesQueryKey(),
    queryFn: fetchDefaultStatuses,
    enabled,
  })

  const [rows, setRows] = useState<WorkflowStatusFormRow[]>([])
  const [syncedFrom, setSyncedFrom] = useState<QuoteWorkflowStatusItem[] | undefined>(undefined)
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
    setRows((current) => withCustomRow(current, newRow, isTailWorkflowSystemKey))
  }

  const removeCustom = (id: string) => {
    setRows((current) => withoutRow(current, id))
  }

  const updateRow = (id: string, patch: WorkflowStatusRowPatch) => {
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
      queryClient.setQueryData(defaultStatusesQueryKey(), fresh)
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
