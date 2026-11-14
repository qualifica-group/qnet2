import { useCallback, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios, { type AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import type { ApiErrorResponse } from '@/api/types'
import { registryDetailQueryKey } from '@/features/registries/api'
import { referentDetailQueryKey } from '@/features/referents/api'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { mergeRecords, previewMerge } from '@/features/record-merge/api'
import {
  buildMergePayload,
  toggleDiscarded,
  type DiscardedItems,
  type FieldOverrides,
} from '@/features/record-merge/merge-selection'
import type { MergeChoice, MergeCollectionKey, MergeModule } from '@/features/record-merge/types'

const HTTP_UNPROCESSABLE = 422

const NO_OVERRIDES: FieldOverrides = {}
const NO_DISCARDED: DiscardedItems = {}

export const recordMergeQueryKey = (module: MergeModule, ids: readonly number[]) =>
  ['record-merge', module, 'preview', [...ids].sort((a, b) => a - b)] as const

const detailQueryKey = (module: MergeModule, id: number) =>
  module === 'registries' ? registryDetailQueryKey(id) : referentDetailQueryKey(id)

/** A 422 carries a readable domain reason (too many managers, invalid resulting card): show it. */
function validationReason(error: unknown): string | null {
  if (!axios.isAxiosError<Partial<ApiErrorResponse>>(error) || error.response?.status !== HTTP_UNPROCESSABLE) {
    return null
  }
  const message = error.response.data?.message
  return typeof message === 'string' && message.trim() !== '' ? message : null
}

interface UseRecordMergeArgs {
  module: MergeModule
  ids: readonly number[]
  onMerged: () => void
}

/** Owns the merge dialog's state: the preview query, the operator's choices and the merge mutation. */
export function useRecordMerge({ module, ids, onMerged }: UseRecordMergeArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(module)

  // Always fresh: the records may have changed since the last time the dialog opened.
  const preview = useQuery({
    queryKey: recordMergeQueryKey(module, ids),
    queryFn: () => previewMerge(module, [...ids]),
    gcTime: 0,
    staleTime: 0,
  })

  const [survivorChoice, setSurvivorChoice] = useState<number | null>(null)
  const [overrides, setOverrides] = useState<FieldOverrides>(NO_OVERRIDES)
  const [discarded, setDiscarded] = useState<DiscardedItems>(NO_DISCARDED)
  const [showEqual, setShowEqual] = useState(false)

  const data = preview.data
  const survivorId = survivorChoice ?? data?.suggested_survivor_id ?? null

  // Defaults depend on the survivor: switching it drops the manual field choices.
  const selectSurvivor = useCallback((id: number) => {
    setSurvivorChoice(id)
    setOverrides(NO_OVERRIDES)
  }, [])

  const chooseField = useCallback((key: string, choice: MergeChoice) => {
    setOverrides((current) => ({ ...current, [key]: choice }))
  }, [])

  const toggleItem = useCallback((key: MergeCollectionKey, itemId: number) => {
    setDiscarded((current) => toggleDiscarded(current, key, itemId))
  }, [])

  const mutation = useMutation<void, AxiosError, void>({
    mutationFn: () => {
      if (!data || survivorId === null) {
        return Promise.reject(new Error('merge preview not loaded'))
      }
      return mergeRecords(module, buildMergePayload(data, survivorId, overrides, discarded))
    },
    onSuccess: () => {
      if (survivorId !== null) {
        void queryClient.invalidateQueries({ queryKey: detailQueryKey(module, survivorId) })
      }
      invalidateStats()
      toast.success(t('recordMerge.success'))
      onMerged()
    },
    onError: (error) => {
      toast.error(validationReason(error) ?? apiErrorMessage(error, t('recordMerge.error'), i18n))
    },
  })

  const survivor = useMemo(() => data?.records.find((record) => record.id === survivorId) ?? null, [data, survivorId])
  const mergedRecords = useMemo(
    () => data?.records.filter((record) => record.id !== survivorId) ?? [],
    [data, survivorId],
  )

  return {
    preview,
    survivorId,
    survivor,
    mergedRecords,
    overrides,
    discarded,
    showEqual,
    setShowEqual,
    selectSurvivor,
    chooseField,
    toggleItem,
    merge: mutation.mutate,
    isMerging: mutation.isPending,
  }
}
