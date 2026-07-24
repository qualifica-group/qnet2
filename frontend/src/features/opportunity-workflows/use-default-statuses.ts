import { useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { fetchDefaultStatuses, updateDefaultStatuses } from '@/features/opportunity-workflows/api'
import { buildDefaultStatusesPayload } from '@/features/opportunity-workflows/opportunity-workflow-form-payload'
import {
  isTailWorkflowSystemKey,
  type OpportunityWorkflowStatusItem,
  type WorkflowStatusFormRow,
  type WorkflowStatusRowPatch,
} from '@/features/opportunity-workflows/types'

/** Query key for the GLOBAL default status set (fresh-on-open pattern, mirrors `useStatusReorder`). */
function defaultStatusesQueryKey() {
  return ['opportunity-workflows', 'default-statuses'] as const
}

function toFormRows(statuses: OpportunityWorkflowStatusItem[]): WorkflowStatusFormRow[] {
  return statuses.map((status) => ({
    id: String(status.id),
    statusId: status.id,
    name: status.name,
    description: status.description,
    color: status.color,
    group: status.group,
    system_key: status.system_key,
    requires_note: status.requires_note,
  }))
}

interface UseDefaultStatusesLabels {
  saved: string
  forbidden: string
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
 * /opportunity-workflows/default-statuses` (unlike the per-drag auto-save of
 * `useStatusReorder`, since this editor also edits name/color/group, not
 * just order).
 */
export function useDefaultStatuses({ enabled, labels }: UseDefaultStatusesArgs) {
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: defaultStatusesQueryKey(),
    queryFn: fetchDefaultStatuses,
    enabled,
  })

  const [rows, setRows] = useState<WorkflowStatusFormRow[]>([])
  const [syncedFrom, setSyncedFrom] = useState<OpportunityWorkflowStatusItem[] | undefined>(undefined)
  if (query.data && query.data !== syncedFrom) {
    setSyncedFrom(query.data)
    setRows(toFormRows(query.data))
  }

  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const nextCustomRowId = useRef(0)

  const addCustom = () => {
    nextCustomRowId.current += 1
    const newRow: WorkflowStatusFormRow = {
      id: `custom-${nextCustomRowId.current}`,
      name: '',
      description: null,
      color: null,
      group: 'pending',
      system_key: null,
      requires_note: false,
    }
    setRows((current) => {
      const tailIndex = current.findIndex((row) => isTailWorkflowSystemKey(row.system_key))
      const insertAt = tailIndex === -1 ? current.length : tailIndex
      return [...current.slice(0, insertAt), newRow, ...current.slice(insertAt)]
    })
  }

  const removeCustom = (id: string) => {
    setRows((current) => current.filter((row) => row.id !== id))
  }

  const updateRow = (id: string, patch: WorkflowStatusRowPatch) => {
    setRows((current) => current.map((row) => (row.id === id ? { ...row, ...patch } : row)))
  }

  const reorder = (orderedIds: string[]) => {
    setRows((current) => {
      const byId = new Map(current.map((row) => [row.id, row]))
      return orderedIds.map((id) => byId.get(id)).filter((row): row is WorkflowStatusFormRow => row !== undefined)
    })
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
      setRows(toFormRows(fresh))
      setSyncedFrom(fresh)
      queryClient.setQueryData(defaultStatusesQueryKey(), fresh)
      toast.success(labels.saved)
      return true
    } catch (caughtError) {
      if (axios.isAxiosError<ApiErrorResponse>(caughtError) && caughtError.response?.status === 403) {
        toast.error(labels.forbidden)
      } else {
        toast.error(labels.genericError)
      }
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
