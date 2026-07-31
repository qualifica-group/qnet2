/**
 * AG Grid popup cell editor for a `datetime` column (spec 0055 D-4). Replaces
 * the plain text editor the generic registry used to hand a `datetime` column,
 * which made the operator retype the raw `YYYY-MM-DDTHH:mm` string by hand.
 *
 * Deliberately built on the SAME control the work panel uses
 * (`DateTimeField`): a date plus an OPTIONAL time (user directive 2026-07-31),
 * whose composed value IS the wire format the backend emits and accepts, so no
 * parsing, no formatting and no date library sit between the two. Registered
 * on the generic registry, so every `datetime` column of every domain gets it.
 *
 * Commit happens on change; clearing the date commits `null`, which the backend
 * accepts for a `nullable` column and rejects for a required one — the cell
 * reverts with the server's message either way. Editing only ends when focus
 * leaves the WHOLE group: moving from the date to the time input is not a
 * commit-and-close.
 *
 * `dateOnly` (spec 0064) reuses this same component for the `date` editor
 * kind: a Product Category attribute of type `date` has no time component at
 * all, so it renders a single `type="date"` input, emits `YYYY-MM-DD` — the
 * format `AttributeValueValidator` accepts for that type — and announces
 * itself with the distinct `table.dateEditor.label` ("Date").
 */
import { useEffect, useRef, useState } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { DateTimeField } from '@/components/date-time-field'
import { Input } from '@/components/ui/input'
import type { TableRow } from '@/features/table/types'

interface DateTimeCellEditorProps extends CustomCellEditorProps<TableRow, string | null> {
  /** Renders a single `type="date"` input instead of the date+time pair (spec 0064). Registered on the `date` editor kind; `datetime` leaves it unset. */
  dateOnly?: boolean
}

const POPUP_CLASS = 'rounded-md border border-border bg-popover p-1.5 shadow-md'
const INPUT_CLASS = 'h-7 text-xs'

export function DateTimeCellEditor(props: DateTimeCellEditorProps) {
  const { t } = useTranslation()
  const { value, onValueChange, stopEditing, dateOnly } = props
  const inputRef = useRef<HTMLInputElement>(null)
  // Own the draft instead of reading `props.value` back: the two inputs must
  // stay in sync with each other between keystrokes, whatever the grid does
  // with the value it was handed.
  const [draft, setDraft] = useState<string | null>(value ?? null)

  // Focus on mount: the single click that opens the editor must be enough to
  // start typing or to reach the native picker (0053 D-9, single-click edit).
  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  function stopOnEnter(event: React.KeyboardEvent) {
    if (event.key === 'Enter') {
      stopEditing()
    }
  }

  if (dateOnly) {
    return (
      <div className={POPUP_CLASS}>
        <Input
          ref={inputRef}
          type="date"
          aria-label={t('table.dateEditor.label')}
          // A `date` input rejects a value carrying time/timezone; the backend
          // emits exactly `YYYY-MM-DD`, so it is passed through untouched and
          // any other shape degrades to an empty field rather than a React
          // warning.
          defaultValue={value ?? ''}
          className={INPUT_CLASS}
          onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
            onValueChange(event.target.value === '' ? null : event.target.value)
          }}
          onBlur={() => stopEditing()}
          onKeyDown={stopOnEnter}
        />
      </div>
    )
  }

  return (
    <div
      className={POPUP_CLASS}
      role="group"
      aria-label={t('table.dateTimeEditor.label')}
      onBlur={(event: React.FocusEvent<HTMLDivElement>) => {
        if (!event.currentTarget.contains(event.relatedTarget)) {
          stopEditing()
        }
      }}
      onKeyDown={stopOnEnter}
    >
      <DateTimeField
        ref={inputRef}
        value={draft}
        onChange={(next) => {
          setDraft(next)
          onValueChange(next)
        }}
        dateLabel={t('table.dateTimeEditor.dateLabel')}
        timeLabel={t('table.dateTimeEditor.timeLabel')}
        inputClassName={INPUT_CLASS}
        className="w-56 flex-nowrap"
      />
    </div>
  )
}
