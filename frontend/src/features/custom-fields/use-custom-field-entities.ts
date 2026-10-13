import { useQuery } from '@tanstack/react-query'
import { fetchCustomFieldEntities, fetchCustomFieldRelationTargets } from '@/features/custom-fields/api'

/** Custom-fieldable modules change only on deploy: cache for the whole session (mirrors `useResourceMeta`'s rationale). */
const CUSTOM_FIELD_ENTITIES_STALE_TIME_MS = 5 * 60 * 1000

/**
 * Loads the custom-fieldable module catalogue (`GET /custom-fields/entities`)
 * feeding the custom field form's host `entity_type` picker.
 */
export function useCustomFieldEntities() {
  return useQuery({
    queryKey: ['custom-fields', 'entities'],
    queryFn: fetchCustomFieldEntities,
    staleTime: CUSTOM_FIELD_ENTITIES_STALE_TIME_MS,
  })
}

/**
 * Loads every entity a RELATION field can point at (`GET
 * /custom-fields/relation-targets`), feeding `DefinitionRelationTargetEditor`.
 */
export function useCustomFieldRelationTargets() {
  return useQuery({
    queryKey: ['custom-fields', 'relation-targets'],
    queryFn: fetchCustomFieldRelationTargets,
    staleTime: CUSTOM_FIELD_ENTITIES_STALE_TIME_MS,
  })
}
