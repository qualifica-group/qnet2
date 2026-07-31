/**
 * A `YYYY-MM-DDTHH:mm` instant edited as a date plus an OPTIONAL time (user
 * directive 2026-07-31: planning a callback must not force an hour). The wire
 * value is unchanged — a date picked without a time commits midnight — so
 * nothing downstream (sparse payload diff, backend validation, sorting and
 * filtering) has to know the time was skipped.
 *
 * The split/join rules (midnight reads back as "no time set") live in
 * `lib/formatting/wire-instant.ts`; displaying such a value follows the same
 * rule in `formatDateTimeOptionalTime` (`features/table/cell-renderers.tsx`).
 *
 * Slot-friendly: every extra prop lands on the DATE input — including the
 * `id`/`aria-describedby`/`aria-invalid` a `<FormControl>` injects — so an
 * external `<FormLabel htmlFor>` names the control the operator reaches first,
 * and the time input carries its own accessible name.
 */
import type { ComponentProps } from 'react'
import { Input } from '@/components/ui/input'
import { joinInstant, splitInstant } from '@/lib/formatting/wire-instant'
import { cn } from '@/lib/utils'

interface DateTimeFieldProps
  extends Omit<ComponentProps<typeof Input>, 'value' | 'onChange' | 'type'> {
  value: string | null
  onChange: (value: string | null) => void
  /** Accessible name of the time input: it never has an external label of its own. */
  timeLabel: string
  /** Accessible name of the date input. Omit when an external `<FormLabel>` already names it. */
  dateLabel?: string
  /** Applied to BOTH inputs (the wrapper takes `className`), e.g. the compact in-grid sizing. */
  inputClassName?: string
}

export function DateTimeField({
  value,
  onChange,
  timeLabel,
  dateLabel,
  inputClassName,
  className,
  disabled,
  readOnly,
  ...dateInputProps
}: DateTimeFieldProps) {
  const { date, time } = splitInstant(value)

  return (
    <div className={cn('flex flex-wrap items-center gap-2', className)}>
      <Input
        {...dateInputProps}
        type="date"
        aria-label={dateLabel}
        disabled={disabled}
        readOnly={readOnly}
        value={date}
        onChange={(event) => onChange(joinInstant(event.target.value, time))}
        className={cn('min-w-36 flex-1', inputClassName)}
      />
      <Input
        type="time"
        aria-label={timeLabel}
        // Without a date there is no instant to attach an hour to: the wire
        // value would stay null and the typed time would silently vanish.
        disabled={disabled || date === ''}
        readOnly={readOnly}
        value={time}
        onChange={(event) => onChange(joinInstant(date, event.target.value))}
        className={cn('w-28 shrink-0', inputClassName)}
      />
    </div>
  )
}
