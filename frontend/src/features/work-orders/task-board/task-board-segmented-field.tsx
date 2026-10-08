/**
 * A labelled segmented control, the same look as Gestione Richieste's
 * row-mode picker. Semantically a radio group: one value out of a short,
 * closed list.
 */

import { useId } from 'react'
import { SegmentedControl } from '@/components/ui/segmented-control'

export interface TaskBoardSegmentedFieldProps<T extends string> {
  label: string
  value: T
  options: { value: T; label: string }[]
  onChange: (value: T) => void
}

export function TaskBoardSegmentedField<T extends string>({ label, value, options, onChange }: TaskBoardSegmentedFieldProps<T>) {
  const labelId = useId()

  return (
    <div className="flex flex-col gap-1.5">
      <span id={labelId} className="text-xs font-medium">
        {label}
      </span>
      <SegmentedControl aria-labelledby={labelId} value={value} options={options} onValueChange={onChange} />
    </div>
  )
}
