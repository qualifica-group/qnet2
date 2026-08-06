/**
 * AG Grid popup cell editor for an `editor: 'select'` column (spec 0055 D-2):
 * a listbox over the column's OWN backend-resolved options, narrowed to the
 * ones valid for the edited row.
 *
 * It knows nothing about what the options mean — it renders `label`, the
 * `color` dot and the `requires_note` marker, and commits `value`, exactly as
 * the backend supplied them (the note marker is the same `RequiresNoteBadge`
 * the work panel's picker shows, so the two never drift; the note itself stays
 * enforced server-side, 0054 D-5). Two conventions,
 * both introduced by earlier specs and reused verbatim here:
 *  - row-scoped options (`<columnId>_options`, 0054 AC-026/027): when the row
 *    carries the array, only those values are offered; a row without it keeps
 *    the full catalog.
 *  - the committed value is the `{id, name}` shape every relation-like cell
 *    already uses (`RelationCellEditor`), so `useTableCellEdit` unwraps it to
 *    the id for the PATCH and the cell's badge renderer keeps rendering an
 *    object between the commit and the server's re-mapped row.
 *
 * The AG Grid popup IS the dropdown (declared `cellEditorPopup: true` on the
 * colDef): the single click that starts the edit is the only click the
 * operator needs, per the single-click decision of 0053 D-9 — no nested
 * Popover, no second click to expand.
 */
import { useEffect, useRef } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { Check } from 'lucide-react'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { RequiresNoteBadge } from '@/features/quote-workflows/requires-note-badge'
import { cn } from '@/lib/utils'
import type { SelectOption, TableRow } from '@/features/table/types'

/**
 * A select column's cell value: the picked option projected as the shared
 * related-row shape, or `null`. `color` rides along so the badge renderer keeps
 * painting the right dot between the optimistic commit and the server's
 * re-mapped row (`StatusBadgeCell` reads it off the cell value).
 */
export interface SelectCellValue {
  id: string | number
  name: string
  color?: string | null
}

/** Extra `cellEditorParams` for this editor: the options resolved for the column, and its id (for the row-scoped narrowing). */
export interface SelectCellEditorParams {
  columnId: string
  options: SelectOption[]
}

/**
 * The options this row may actually be moved to: `<columnId>_options` when the
 * row carries it (an array of allowed values), the full catalog otherwise.
 * Driven by the row's own shape, never by a column id the frontend recognizes.
 */
function narrowToRow(options: SelectOption[], columnId: string, row: TableRow | undefined): SelectOption[] {
  const allowed = row?.[`${columnId}_options`]
  if (!Array.isArray(allowed)) {
    return options
  }
  const allowedValues = new Set(allowed.map(String))
  return options.filter((option) => allowedValues.has(String(option.value)))
}

export function SelectCellEditor(
  props: CustomCellEditorProps<TableRow, SelectCellValue | null> & SelectCellEditorParams,
) {
  const { t } = useTranslation()
  const { value, data, onValueChange, stopEditing, columnId, options } = props
  const listRef = useRef<HTMLDivElement>(null)

  // Focus the list on mount so the editor is keyboard-operable from the very
  // click that opened it (Tab/arrows reach the options, Esc still cancels
  // through AG Grid's own popup handling).
  useEffect(() => {
    listRef.current?.focus()
  }, [])

  const available = narrowToRow(options, columnId, data)

  const handlePick = (option: SelectOption) => {
    // Re-picking the current value must not commit: the cell value is an
    // OBJECT, so a fresh `{id, name}` would never compare equal to the old one
    // and the hook's no-op guard (`newValue === oldValue`) would let a pointless
    // PATCH through.
    if (value != null && String(value.id) === String(option.value)) {
      stopEditing()

      return
    }

    onValueChange({ id: option.value, name: option.label, color: option.color ?? null })
    stopEditing()
  }

  return (
    <div
      ref={listRef}
      role="listbox"
      tabIndex={-1}
      aria-label={t('table.selectEditor.list')}
      className="max-h-64 w-64 overflow-auto rounded-md border border-border bg-popover p-1 shadow-md outline-none"
    >
      {available.length === 0 ? (
        <p className="px-2.5 py-1 text-xs text-muted-foreground">{t('table.selectEditor.empty')}</p>
      ) : null}
      {available.map((option) => {
        const selected = value != null && String(value.id) === String(option.value)
        const swatchClass = swatchClassFor(option.color)
        return (
          <button
            key={String(option.value)}
            type="button"
            role="option"
            aria-selected={selected}
            onClick={() => handlePick(option)}
            className={cn(
              'flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs',
              'hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
              selected && 'font-medium',
            )}
          >
            <Check className={cn('size-3.5 shrink-0', selected ? 'opacity-100' : 'opacity-0')} aria-hidden="true" />
            {swatchClass !== undefined ? (
              <span className={cn('size-2.5 shrink-0 rounded-full border', swatchClass)} aria-hidden="true" />
            ) : null}
            <span className="truncate">{option.label}</span>
            {option.requires_note === true ? <RequiresNoteBadge /> : null}
          </button>
        )
      })}
    </div>
  )
}
