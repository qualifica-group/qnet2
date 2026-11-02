import { useRef } from 'react'
import type { UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { fetchRegistryForSelectMeta } from '@/features/registries/for-select-meta'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/**
 * Spec 0330 D-7: picking an anagrafica gives the Referente its main referent
 * (empty when it has none). Run from the registry change handler, after the
 * handler cleared the previous referent — never from an effect. The fetched
 * value is dropped when a later pick superseded this one or the form no longer
 * holds this anagrafica, so quick successive picks keep only the last.
 */
export function useTaskRegistryReferentPrefill(
  setValue: UseFormSetValue<TaskFormValues>,
  getValues: UseFormGetValues<TaskFormValues>,
) {
  const queryClient = useQueryClient()
  const requestRef = useRef(0)

  return async (registryId: number | null) => {
    const request = ++requestRef.current
    if (registryId === null) {
      return
    }
    // Best effort: a failed lookup leaves the Referente empty, as before this prefill existed.
    const meta = await fetchRegistryForSelectMeta(queryClient, registryId).catch(() => null)
    if (request !== requestRef.current || getValues('registry_id') !== registryId) {
      return
    }
    setValue('referent_id', meta?.primary_referent?.id ?? null, { shouldDirty: true })
  }
}
