import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
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
/** "% Stato attuale" starts at 0, like the total reached; the date starts empty, as in the legacy popup. */
const EMPTY_FORM: ProgressFormValues = { percentage: '0', recorded_on: '' }

interface UseWorkOrderProgressOptions {
  workOrderId: number
  /** After a registration or a deletion, so the host refreshes the grid or detail it lives in. */
  onMutated?: () => void
  /** After a successful registration: the legacy popup closes on "Conferma". */
  onSaved?: () => void
}

/**
 * Logic of the "Lista avanzamenti commessa" popup (spec 0321 R-5): the rows
 * (increments), their sum (the total reached), the add form (typed total +
 * date) and the inline two-step delete. Writing needs the server-granted
 * `update`; the backend re-checks it.
 */
export function useWorkOrderProgress({ workOrderId, onMutated, onSaved }: UseWorkOrderProgressOptions) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const id = workOrderId
  const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null)

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
  const total = useMemo(() => entries.reduce((sum, entry) => sum + entry.percentage, 0), [entries])
  const detail = detailQuery.data
  const canWrite = Boolean(detail?.permissions.resource.update)

  const form = useForm<ProgressFormValues>({
    resolver: zodResolver(buildProgressSchema(t, total)),
    defaultValues: EMPTY_FORM,
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

  const createMutation = useMutation({
    mutationFn: (values: ProgressFormValues) =>
      createProgressEntry(id, { percentage: Number(values.percentage), recorded_on: values.recorded_on }),
    onSuccess: async () => {
      await refreshAfterMutation()
      toast.success(t('workOrders.progress.added'))
      onSaved?.()
    },
    onError: (error) => {
      if (!applyServerValidationErrors(error, form.setError, ['percentage', 'recorded_on'])) {
        toast.error(t('workOrders.progress.genericError'))
      }
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (entryId: number) => deleteProgressEntry(id, entryId),
    onSuccess: async () => {
      setPendingDeleteId(null)
      await refreshAfterMutation()
      toast.success(t('workOrders.progress.deleted'))
    },
    onError: () => toast.error(t('workOrders.progress.genericError')),
  })

  return {
    code: detail?.code ?? '',
    entries,
    total,
    isLoading: entriesQuery.isLoading,
    isError: entriesQuery.isError,
    canWrite,
    form,
    submit: form.handleSubmit((values) => createMutation.mutate(values)),
    isSaving: createMutation.isPending,
    isDeleting: deleteMutation.isPending,
    pendingDeleteId,
    askDelete: setPendingDeleteId,
    cancelDelete: () => setPendingDeleteId(null),
    confirmDelete: (entry: WorkOrderProgressEntry) => deleteMutation.mutate(entry.id),
  }
}
