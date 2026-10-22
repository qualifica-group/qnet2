import { cn } from '@/lib/utils'
import { absenceTypeDotClass } from '@/features/absences/absence-type-color'
import type { AbsenceTypeColor } from '@/features/absences/types'

interface AbsenceTypeChipProps {
  name: string
  color: AbsenceTypeColor
  className?: string
}

/** Type name with its palette dot; the colour only decorates, the name carries the meaning. */
export function AbsenceTypeChip({ name, color, className }: AbsenceTypeChipProps) {
  return (
    <span
      className={cn(
        'inline-flex max-w-full items-center gap-1.5 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-foreground',
        className,
      )}
    >
      <span aria-hidden="true" className={cn('size-2 shrink-0 rounded-full', absenceTypeDotClass(color))} />
      <span className="truncate">{name}</span>
    </span>
  )
}
