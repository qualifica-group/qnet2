import { Clock } from 'lucide-react'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { cn } from '@/lib/utils'

interface TimeEntryTypeChipProps {
  /** The type's palette token and icon; `null` while no type is picked (a neutral clock). */
  type: { color: string; icon: string | null } | null
}

/** The round header chip of a time entry: its type's colored icon (spec 0122 D-2). */
export function TimeEntryTypeChip({ type }: TimeEntryTypeChipProps) {
  if (!type) {
    return (
      <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border bg-background text-muted-foreground">
        <Clock className="size-4" aria-hidden="true" />
      </span>
    )
  }

  return (
    <span
      className={cn(
        'flex size-9 shrink-0 items-center justify-center rounded-full border border-current',
        badgeColorClass(type.color),
      )}
    >
      <DynamicIcon name={type.icon} className="size-4" />
    </span>
  )
}
