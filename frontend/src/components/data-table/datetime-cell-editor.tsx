/**
 * AG Grid popup cell editor for a `datetime` column (spec 0055 D-4). Replaces
 * the plain text editor the generic registry used to hand a `datetime` column,
 * which made the operator retype the raw `YYYY-MM-DDTHH:mm` string by hand.
 *
 * Deliberately built on the SAME native `datetime-local` input the work panel
 * already uses (`features/request-management/request-callback-section.tsx`):
 * its value format IS the wire format the backend emits and accepts, so no
 * parsing, no formatting and no date library sit between the two. Registered
 * on the generic registry, so every `datetime` column of every domain gets it.
 *
 * Commit happens on change (a picked date closes the editor, mirroring the
 * select/relation editors); clearing commits `null`, which the backend accepts
 * for a `nullable` column and rejects for a required one — the cell reverts
 * with the server's message either way.
 *
 * `dateOnly` (spec 0064) reuses this same component for the `date` editor
 * kind: a Product Category attribute of type `date` has no time component, so
 * it renders a plain `type="date"` input, emits `YYYY-MM-DD` — the format
 * `AttributeValueValidator` accepts for that type — and announces itself with
 * the distinct `table.dateEditor.label` ("Date"), not "Date and time".
 */
import { useEffect, useRef } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import type { TableRow } from '@/features/table/types'

interface DateTimeCellEditorProps extends CustomCellEditorProps<TableRow, string | null> {
  /** Renders `type="date"` instead of `type="datetime-local"` (spec 0064). Registered on the `date` editor kind; `datetime` leaves it unset. */
  dateOnly?: boolean
}

export function DateTimeCellEditor(props: DateTimeCellEditorProps) {
  const { t } = useTranslation()
  const { value, onValueChange, stopEditing, dateOnly } = props
  const inputRef = useRef<HTMLInputElement>(null)

  // Focus on mount: the single click that opens the editor must be enough to
  // start typing or to reach the native picker (0053 D-9, single-click edit).
  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  return (
    <div className="rounded-md border border-border bg-popover p-1.5 shadow-md">
      <Input
        ref={inputRef}
        type={dateOnly ? 'date' : 'datetime-local'}
        aria-label={t(dateOnly ? 'table.dateEditor.label' : 'table.dateTimeEditor.label')}
        // A `datetime-local`/`date` input rejects a value carrying
        // seconds/timezone; the backend emits exactly `YYYY-MM-DDTHH:mm` (or
        // `YYYY-MM-DD` for `dateOnly`), so it is passed through untouched and
        // any other shape degrades to an empty field rather than a React
        // warning.
        defaultValue={value ?? ''}
        className="h-7 text-xs"
        onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
          onValueChange(event.target.value === '' ? null : event.target.value)
        }}
        onBlur={() => stopEditing()}
        onKeyDown={(event: React.KeyboardEvent<HTMLInputElement>) => {
          if (event.key === 'Enter') {
            stopEditing()
          }
        }}
      />
    </div>
  )
}
