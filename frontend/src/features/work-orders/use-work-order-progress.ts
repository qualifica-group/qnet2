import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import {
  createProgressEntry,
  deleteProgressEntry,
  fetchProgressEntries,
  workOrderProgressQueryKey,
} from '@/features/work-orders/progress-api'
import { buildProgressSchema, type ProgressFormValues } from '@/features/work-orders/work-order-progress-schema'
import type { WorkOrderProgressEntry } from '@/features/work-orders/types'

const EMPTY_ENTRIES: WorkOrderProgressEntry[] = []
const HTTP_CONFLICT = 409

/** Today as a local `Y-m-d` (the default of the date field; `toISOString` would shift by the UTC offset). */
function todayLocalIso(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

interface UseWorkOrderProgressOptions {
  workOrderId: number
  /** After a registration or a deletion, so the host refreshes the grid or detail it lives in. */
  onMutated?: () => void
}

/**
 * Logic of the "Avanzamento commessa" dialog (spec 0321): the entries list,
 * the permission to write (server-granted `update`, work order not
 * force-closed — the backend re-checks both), the add form and the delete flow.
 */
export function useWorkOrderProgress({ workOrderId, onMutated }: UseWorkOrderProgressOptions) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const confirm = useConfirm()
  const id = workOrderId

  const entriesQuery = useQuery({
    queryKey: workOrderProgressQueryKey(id),
    queryFn: () => fetchProgressEntries(id),
  })
  // Reuses the detail the open record already holds: a refetch here would put
  // the record screen (useEntityDetail: skeleton while fetching) into loading
  // and unmount this popup with it. Opened from the grid it fetches once.
  const detailQuery = useQuery({
    queryKey: workOrderDetailQueryKey(id),
    queryFn: () => fetchWorkOrder(id),
    staleTime: Infinity,
  })

  const entries = entriesQuery.data ?? EMPTY_ENTRIES
  const currentPercentage = entries[0]?.percentage ?? 0
  const detail = detailQuery.data
  const canWrite = Boolean(detail?.permissions.resource.update) && !detail?.is_force_closed

  const defaultValues = useMemo<ProgressFormValues>(() => ({ percentage: '', recorded_on: todayLocalIso() }), [])
  const form = useForm<ProgressFormValues>({
    resolver: zodResolver(buildProgressSchema(t, currentPercentage)),
    defaultValues,
  })

  async function refreshAfterMutation() {
    // The fresh detail is written into the cache, never invalidated: an
    // invalidation would refetch it and reload the open record (see above).
    const [, freshDetail] = await Promise.all([
      queryClient.invalidateQueries({ queryKey: workOrderProgressQueryKey(id) }),
      fetchWorkOrder(id),
    ])
    queryClient.setQueryData(workOrderDetailQueryKey(id), freshDetail)
    onMutated?.()
  }

  function reportFailure(error: unknown) {
    if (axios.isAxiosError(error) && error.response?.status === HTTP_CONFLICT) {
      toast.error(t('workOrders.progress.closedError'))
      void refreshAfterMutation()
      return
    }
    toast.error(t('workOrders.progress.genericError'))
  }

  const createMutation = useMutation({
    mutationFn: (values: ProgressFormValues) =>
      createProgressEntry(id, { percentage: Number(values.percentage), recorded_on: values.recorded_on }),
    onSuccess: async () => {
      form.reset({ percentage: '', recorded_on: todayLocalIso() })
      await refreshAfterMutation()
      toast.success(t('workOrders.progress.added'))
    },
    onError: (error) => {
      if (!applyServerValidationErrors(error, form.setError, ['percentage', 'recorded_on'])) {
        reportFailure(error)
      }
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (entryId: number) => deleteProgressEntry(id, entryId),
    onSuccess: async () => {
      await refreshAfterMutation()
      toast.success(t('workOrders.progress.deleted'))
    },
    onError: reportFailure,
  })

  async function removeEntry(entry: WorkOrderProgressEntry) {
    // Step 1: ask before the irreversible delete
    const confirmed = await confirm({
      title: t('workOrders.progress.deleteTitle'),
      description: t('workOrders.progress.deleteDescription', { percentage: entry.percentage }),
      confirmLabel: t('workOrders.progress.delete'),
      tone: 'destructive',
    })
    // Step 2: delete; the list, the detail and the host grid refresh on success
    if (confirmed) {
      deleteMutation.mutate(entry.id)
    }
  }

  return {
    entries,
    isLoading: entriesQuery.isLoading,
    isError: entriesQuery.isError,
    canWrite,
    currentPercentage,
    form,
    submit: form.handleSubmit((values) => createMutation.mutate(values)),
    isSaving: createMutation.isPending,
    isDeleting: deleteMutation.isPending,
    removeEntry,
  }
}
