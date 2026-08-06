import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import {
  SelectCellEditor,
  type SelectCellEditorParams,
  type SelectCellValue,
} from '@/components/data-table/select-cell-editor'
import type { SelectOption, TableRow } from '@/features/table/types'

/**
 * Spec 0055 D-2: a `select` column's editor lists the options the BACKEND
 * resolved, narrowed to the values valid for the edited row
 * (`<columnId>_options`, the 0054 convention), and commits the pick as the
 * shared `{id, name}` shape. Nothing here knows what a working status is.
 */

const OPTIONS: SelectOption[] = [
  { value: 1, label: 'Da contattare', color: 'blue', requires_note: false },
  { value: 2, label: 'In lavorazione', color: 'amber', requires_note: false },
  { value: 3, label: 'Chiusa', color: null, requires_note: true },
]

/** The note marker's own text, so the option lookups stay label-only. */
const REQUIRES_NOTE_LABEL = () => i18n.t('quoteWorkflows.form.statuses.requiresNoteBadge')

function renderEditor(
  props: Partial<CustomCellEditorProps<TableRow, SelectCellValue | null> & SelectCellEditorParams> = {},
) {
  const fullProps = {
    value: null,
    data: { id: 7, actions: [] } as TableRow,
    onValueChange: vi.fn(),
    stopEditing: vi.fn(),
    columnId: 'workflow_status',
    options: OPTIONS,
    ...props,
  } as unknown as CustomCellEditorProps<TableRow, SelectCellValue | null> & SelectCellEditorParams

  render((<SelectCellEditor {...fullProps} />) as ReactElement)

  return fullProps
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('SelectCellEditor', () => {
  it('lists every backend option when the row carries no row-scoped set', () => {
    renderEditor()

    expect(screen.getAllByRole('option')).toHaveLength(3)
    expect(screen.getByRole('option', { name: /Chiusa/ })).toBeInTheDocument()
  })

  it('commits the pick as {id, name, color} and closes the editor, on a single click', () => {
    const props = renderEditor()

    fireEvent.click(screen.getByRole('option', { name: 'In lavorazione' }))

    expect(props.onValueChange).toHaveBeenCalledWith({ id: 2, name: 'In lavorazione', color: 'amber' })
    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })

  it('narrows the list to the row-scoped <columnId>_options (0054 AC-026/027)', () => {
    renderEditor({ data: { id: 7, actions: [], workflow_status_options: [1, 3] } as TableRow })

    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual([
      'Da contattare',
      `Chiusa${REQUIRES_NOTE_LABEL()}`,
    ])
  })

  it('is driven by the row shape, never by column id', () => {
    renderEditor({ columnId: 'anything', data: { id: 7, actions: [], anything_options: [2] } as TableRow })

    expect(screen.getAllByRole('option')).toHaveLength(1)
    expect(screen.getByRole('option', { name: 'In lavorazione' })).toBeInTheDocument()
  })

  it('marks the current value as selected', () => {
    renderEditor({ value: { id: 3, name: 'Chiusa' } })

    expect(screen.getByRole('option', { name: /Chiusa/ })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('option', { name: 'Da contattare' })).toHaveAttribute('aria-selected', 'false')
  })

  it('paints each option with the color dot the backend sent, and none when it sent no color', () => {
    renderEditor()

    const dots = screen.getAllByRole('option').map((option) => option.querySelector('.rounded-full'))

    expect(dots[0]).toHaveClass('bg-blue-500')
    expect(dots[1]).toHaveClass('bg-amber-500')
    expect(dots[2]).toBeNull()
  })

  it('marks only the options that require a note, so the operator knows before picking', () => {
    renderEditor()

    expect(screen.getAllByText(REQUIRES_NOTE_LABEL())).toHaveLength(1)
    expect(screen.getByRole('option', { name: /Chiusa/ })).toHaveTextContent(REQUIRES_NOTE_LABEL())
  })

  it('states the empty case instead of rendering a blank popup', () => {
    renderEditor({ data: { id: 7, actions: [], workflow_status_options: [] } as TableRow })

    expect(screen.queryAllByRole('option')).toHaveLength(0)
    expect(screen.getByText(i18n.t('table.selectEditor.empty'))).toBeInTheDocument()
  })

  it('commits nothing until an option is actually picked', () => {
    const props = renderEditor()

    expect(props.onValueChange).not.toHaveBeenCalled()
    expect(props.stopEditing).not.toHaveBeenCalled()
  })

  it('re-picking the current value closes without committing (no pointless PATCH)', () => {
    const props = renderEditor({ value: { id: 2, name: 'In lavorazione' } })

    fireEvent.click(screen.getByRole('option', { name: 'In lavorazione' }))

    expect(props.onValueChange).not.toHaveBeenCalled()
    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })
})
