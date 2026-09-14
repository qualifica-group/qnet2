/**
 * "Tipo" drawer filter (spec 0122 D-13): task types rendered as toggle chips
 * with their own icon/color, not a searchable combobox — task-types is a
 * small, fixed catalogue (D-1 context: Attività, Riunione, Chiamata, Ticket,
 * Email, Visita, Altro), so a full async-paginated select would be over-kill.
 */

import { useMemo } from 'react'
import { Skeleton } from '@/components/ui/skeleton'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { ICON_CATALOG } from '@/features/custom-fields/icon-catalog'
import { flattenForSelectPages, useForSelect } from '@/features/for-select/use-for-select'
import {
  TimeEntryToggleFilter,
  type TimeEntryToggleOption,
} from '@/features/time-entries/dashboard/time-entry-toggle-filter'

/** Mirrors `TaskTypeForSelectResource::forSelectItem()`'s `meta` bag. */
interface TaskTypeForSelectMeta {
  color: string
  icon: string | null
  is_active: boolean
}

interface TimeEntriesTaskTypeFilterProps {
  value: string[]
  onChange: (next: string[]) => void
  'aria-label'?: string
}

export function TimeEntriesTaskTypeFilter({
  value,
  onChange,
  'aria-label': ariaLabel,
}: TimeEntriesTaskTypeFilterProps) {
  const query = useForSelect({ resource: 'task-types', search: '', enabled: true })

  const options = useMemo<TimeEntryToggleOption[]>(() => {
    return flattenForSelectPages(query.data?.pages).map((item) => {
      const meta = (item as typeof item & { meta?: TaskTypeForSelectMeta }).meta
      return {
        value: String(item.id),
        label: item.label,
        icon: meta?.icon ? ICON_CATALOG[meta.icon] : undefined,
        colorClassName: badgeColorClass(meta?.color),
      }
    })
  }, [query.data?.pages])

  if (query.isPending) {
    return (
      <div className="flex flex-wrap gap-1.5">
        {Array.from({ length: 5 }).map((_, index) => (
          <Skeleton key={index} className="h-6 w-20 rounded-full" />
        ))}
      </div>
    )
  }

  return <TimeEntryToggleFilter options={options} value={value} onChange={onChange} aria-label={ariaLabel} />
}
