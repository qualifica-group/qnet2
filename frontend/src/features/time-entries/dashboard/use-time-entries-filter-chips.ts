/**
 * Resolves the active drawer filters (spec 0122 D-13, AC-033) into display
 * chips, looking up entity labels (Utente/Tipo/Cliente/Commessa/Opportunità/
 * Task) via for-select `ids`, mirroring q-net's
 * `useActiveWorkActivitiesFilterChips` but with ONE batched query per
 * resource (`useForSelectLabels` already accepts an id array) instead of one
 * query per id.
 */

import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { DAILY_STATUS_META } from '@/features/time-entries/time-entry-constants'
import {
  buildTimeEntriesFilterChips,
  type TimeEntriesFiltersState,
  type TimeEntryFilterChip,
  type TimeEntryFilterDefinition,
} from '@/features/time-entries/time-entries-filters'
import type { DailyStatus } from '@/features/time-entries/types'

function parseIds(value: string | string[] | undefined): number[] {
  const entries = Array.isArray(value) ? value : typeof value === 'string' ? [value] : []
  return entries.map(Number).filter((entry) => Number.isInteger(entry) && entry > 0)
}

/**
 * Duplicates `time-entries-filters.ts`'s private `defaultFilterValueLabel`
 * for the two statically-known controls (boolean/daily-status): that helper
 * is not exported and this hook lives outside F1's write surface, so the
 * ~6-line branch is repeated here rather than widening that module's API.
 */
function staticFilterValueLabel(
  definition: TimeEntryFilterDefinition,
  rawValue: string,
  translate: (key: string) => string,
): string {
  if (definition.control === 'boolean') {
    return translate(`timeEntries.filters.isActiveOptions.${rawValue === 'true' ? 'true' : 'false'}`)
  }
  if (definition.control === 'daily-status-multi') {
    const meta = DAILY_STATUS_META[rawValue as DailyStatus]
    return meta ? translate(`timeEntries.dailyStatus.${meta.labelKey}`) : rawValue
  }
  return rawValue
}

export function useTimeEntriesFilterChips(filters: TimeEntriesFiltersState): TimeEntryFilterChip[] {
  const { t } = useTranslation()

  const userIds = parseIds(filters.values.user_id)
  const taskTypeIds = parseIds(filters.values.task_type_ids)
  const registryIds = parseIds(filters.values.registry_ids)
  const workOrderIds = parseIds(filters.values.work_order_ids)
  const opportunityIds = parseIds(filters.values.opportunity_ids)
  const taskIds = parseIds(filters.values.task_ids)

  const userLabels = useForSelectLabels({ resource: 'users', ids: userIds, enabled: userIds.length > 0 })
  const taskTypeLabels = useForSelectLabels({
    resource: 'task-types',
    ids: taskTypeIds,
    enabled: taskTypeIds.length > 0,
  })
  const registryLabels = useForSelectLabels({
    resource: 'registries',
    ids: registryIds,
    enabled: registryIds.length > 0,
  })
  const workOrderLabels = useForSelectLabels({
    resource: 'work-orders',
    ids: workOrderIds,
    enabled: workOrderIds.length > 0,
  })
  const opportunityLabels = useForSelectLabels({
    resource: 'opportunities',
    ids: opportunityIds,
    enabled: opportunityIds.length > 0,
  })
  const taskLabels = useForSelectLabels({ resource: 'tasks', ids: taskIds, enabled: taskIds.length > 0 })

  const labelsByResource = useMemo<Record<string, Map<number, ForSelectItem>>>(
    () => ({
      users: userLabels,
      'task-types': taskTypeLabels,
      registries: registryLabels,
      'work-orders': workOrderLabels,
      opportunities: opportunityLabels,
      tasks: taskLabels,
    }),
    [userLabels, taskTypeLabels, registryLabels, workOrderLabels, opportunityLabels, taskLabels],
  )

  return useMemo(
    () =>
      buildTimeEntriesFilterChips(filters, t, (definition, rawValue) => {
        const resource = definition.control === 'user' ? 'users' : definition.forSelectResource
        if (resource) {
          const item = labelsByResource[resource]?.get(Number(rawValue))
          if (item) {
            return item.label
          }
        }
        return staticFilterValueLabel(definition, rawValue, t)
      }),
    [filters, labelsByResource, t],
  )
}
