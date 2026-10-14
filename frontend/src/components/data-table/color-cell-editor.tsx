/**
 * AG Grid popup cell editor for an `editor: 'color'` column (spec 0258 D-3):
 * the badge palette as a grid of swatches, the same tokens and localized
 * names the record's `ColorTokenPicker` offers. The cell value is the token
 * itself (e.g. `teal`); the server re-validates it against its own palette.
 *
 * The AG Grid popup IS the palette (declared `cellEditorPopup: true`): the
 * single click that opens the cell is followed by the one that picks.
 */
import { useEffect, useRef } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { BADGE_COLOR_TOKENS } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { TableRow } from '@/features/table/types'

export function ColorCellEditor({ value, onValueChange, stopEditing }: CustomCellEditorProps<TableRow, string | null>) {
  const { t } = useTranslation()
  const listRef = useRef<HTMLDivElement>(null)

  // Keyboard-operable from the click that opened it; Esc cancels through AG Grid.
  useEffect(() => {
    listRef.current?.focus()
  }, [])

  const pick = (token: string) => {
    if (token !== value) {
      onValueChange(token)
    }
    stopEditing()
  }

  return (
    <div
      ref={listRef}
      role="listbox"
      tabIndex={-1}
      aria-label={t('table.colorEditor.list')}
      className="grid w-60 grid-cols-7 gap-1.5 rounded-md border border-border bg-popover p-2 shadow-md outline-none"
    >
      {BADGE_COLOR_TOKENS.map(({ token, swatch }) => {
        const selected = token === value
        const label = t(`customFields.colors.${token}`)
        return (
          <button
            key={token}
            type="button"
            role="option"
            aria-selected={selected}
            aria-label={label}
            title={label}
            onClick={() => pick(token)}
            className={cn(
              'flex aspect-square items-center justify-center rounded-md outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50',
              selected && 'ring-2 ring-ring ring-offset-1 ring-offset-popover',
            )}
          >
            <span className={cn('size-5 rounded-full border', swatch)} aria-hidden="true" />
          </button>
        )
      })}
    </div>
  )
}
