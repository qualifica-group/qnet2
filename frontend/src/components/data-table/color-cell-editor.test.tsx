import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { ColorCellEditor } from '@/components/data-table/color-cell-editor'
import type { TableRow } from '@/features/table/types'

function renderEditor(value: string | null) {
  const props = {
    value,
    onValueChange: vi.fn(),
    stopEditing: vi.fn(),
  } as unknown as CustomCellEditorProps<TableRow, string | null>
  render(<ColorCellEditor {...props} />)
  return props
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

// Spec 0258 D-3: the grid's palette editor for a catalogue `color` token.
describe('ColorCellEditor', () => {
  it('offers the palette with localized names and marks the current token', () => {
    renderEditor('teal')

    expect(screen.getByRole('listbox', { name: 'Pick a colour' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: i18n.t('customFields.colors.teal') })).toHaveAttribute('aria-selected', 'true')
  })

  it('commits the picked token and closes', () => {
    const props = renderEditor('teal')

    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.red') }))

    expect(props.onValueChange).toHaveBeenCalledWith('red')
    expect(props.stopEditing).toHaveBeenCalled()
  })

  it('re-picking the current token closes without committing', () => {
    const props = renderEditor('teal')

    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.teal') }))

    expect(props.onValueChange).not.toHaveBeenCalled()
    expect(props.stopEditing).toHaveBeenCalled()
  })
})
