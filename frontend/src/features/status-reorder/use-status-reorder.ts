import { useCallback, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { forSelectKeys } from '@/features/for-select/query-keys'
import { fetchStatusesForReorder, reorderStatuses } from '@/features/status-reorder/api'
import { reconcileReorderedItems } from '@/features/status-reorder/reconcile-reordered-items'
import type { StatusReorderItem } from '@/features/status-reorder/types'

/** Query key for a resource's reorder-sheet list (fresh-on-open pattern). */
function reorderListQueryKey(resource: string) {
  return ['status-reorder', resource, 'list'] as const
}

interface StatusReorderLabels {
  saved: string
  forbidden: string
  genericError: string
}

interface UseStatusReorderArgs {
  resource: string
  /** Gates the list fetch — only load while the sheet is open. */
  enabled: boolean
  labels: StatusReorderLabels
  /** Called after a successful reorder so the caller can refresh its own table view. */
  onReordered: () => void
}

/**
 * Owns every non-render concern of `StatusReorderSheet`: loads the full
 * ordered status list, mirrors it into local state for `<SortableList>`, and
 * persists a drag via `POST /{resource}/reorder` — optimistic on the visual
 * order, reverted on a 403/422 (spec 0039 AC-011). Invalidates the shared
 * for-select cache on success so every status picker reflects the new order.
 */
export function useStatusReorder({ resource, enabled, labels, onReordered }: UseStatusReorderArgs) {
  const queryClient = useQueryClient()

  const listQuery = useQuery({
    queryKey: reorderListQueryKey(resource),
    queryFn: () => fetchStatusesForReorder(resource),
    enabled,
  })

  // Mirrors the query result for optimistic in-place reordering, resyncing
  // whenever a genuinely new list lands (initial load, sheet reopen, or the
  // reconciled server response after a successful drag). Adjusted during
  // render rather than in an effect, per React's "adjusting state on prop
  // change" recipe.
  const [items, setItems] = useState<StatusReorderItem[]>([])
  const [syncedFrom, setSyncedFrom] = useState<StatusReorderItem[] | undefined>(undefined)
  if (listQuery.data && listQuery.data !== syncedFrom) {
    setSyncedFrom(listQuery.data)
    setItems(listQuery.data)
  }

  const [isSaving, setIsSaving] = useState(false)
  const previousItemsRef = useRef<StatusReorderItem[]>([])

  const onReorder = useCallback(
    (orderedIds: string[]) => {
      // Step 1: apply the drag optimistically, keeping the pre-drag order to revert to on failure
      previousItemsRef.current = items
      const byId = new Map(items.map((item) => [String(item.id), item]))
      const nextItems = orderedIds
        .map((id) => byId.get(id))
        .filter((item): item is StatusReorderItem => item !== undefined)
      setItems(nextItems)
      setIsSaving(true)

      // Step 2: persist only the custom ids, in their new visual order (system rows are never sent)
      const customIds = nextItems.filter((item) => item.systemKey === null).map((item) => item.id)

      reorderStatuses(resource, customIds)
        .then((fresh) => {
          const nameById = new Map(nextItems.map((item) => [item.id, item.name]))
          const reconciled = reconcileReorderedItems(fresh, nameById)
          setItems(reconciled)
          setSyncedFrom(reconciled)
          toast.success(labels.saved)
          queryClient.invalidateQueries({ queryKey: forSelectKeys.resource(resource) })
          onReordered()
        })
        .catch((error: unknown) => {
          // Step 3: revert to the pre-drag order and surface the failure
          setItems(previousItemsRef.current)
          if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.status === 403) {
            toast.error(labels.forbidden)
          } else {
            toast.error(labels.genericError)
          }
        })
        .finally(() => setIsSaving(false))
    },
    [items, resource, labels, queryClient, onReordered],
  )

  return {
    items,
    isLoading: listQuery.isLoading,
    isError: listQuery.isError,
    refetch: listQuery.refetch,
    isSaving,
    onReorder,
  }
}
