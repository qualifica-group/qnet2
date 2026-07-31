import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { DateTimeCellEditor } from '@/components/data-table/datetime-cell-editor'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0055 D-4: a `datetime` column edits through a real date/time control
 * whose value format IS the wire format, instead of the raw text field the
 * generic registry used to hand it. User directive 2026-07-31: the time part
 * of that control is optional — a date alone commits midnight.
 */

type EditorProps = CustomCellEditorProps<TableRow, string | null> & { dateOnly?: boolean }

const DATE_LABEL = 'table.dateTimeEditor.dateLabel'
const TIME_LABEL = 'table.dateTimeEditor.timeLabel'

function renderEditor(props: Partial<EditorProps> = {}) {
  const fullProps = {
    value: null,
    onValueChange: vi.fn(),
    stopEditing: vi.fn(),
    ...props,
  } as unknown as EditorProps

  render((<DateTimeCellEditor {...fullProps} />) as ReactElement)

  return fullProps
}

const dateInput = () => screen.getByLabelText(i18n.t(DATE_LABEL))
const timeInput = () => screen.getByLabelText(i18n.t(TIME_LABEL))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('DateTimeCellEditor', () => {
  it('renders a date and an optional time control, grouped, with the date focused', () => {
    renderEditor({ value: '2026-07-22T09:30' })

    expect(screen.getByRole('group', { name: i18n.t('table.dateTimeEditor.label') })).toBeInTheDocument()
    expect(dateInput()).toHaveAttribute('type', 'date')
    expect(dateInput()).toHaveValue('2026-07-22')
    expect(dateInput()).toHaveFocus()
    expect(timeInput()).toHaveValue('09:30')
  })

  it('commits midnight when only the date is picked — the hour is not mandatory', () => {
    const props = renderEditor()

    fireEvent.change(dateInput(), { target: { value: '2026-08-01' } })

    expect(props.onValueChange).toHaveBeenCalledWith('2026-08-01T00:00')
  })

  it('commits the composed value in the wire format when a time is added', () => {
    const props = renderEditor()

    fireEvent.change(dateInput(), { target: { value: '2026-08-01' } })
    fireEvent.change(timeInput(), { target: { value: '14:00' } })

    expect(props.onValueChange).toHaveBeenLastCalledWith('2026-08-01T14:00')
  })

  it('shows a blank time for a value saved without one', () => {
    renderEditor({ value: '2026-07-22T00:00' })

    expect(timeInput()).toHaveValue('')
  })

  it('commits null when the date is cleared', () => {
    const props = renderEditor({ value: '2026-07-22T09:30' })

    fireEvent.change(dateInput(), { target: { value: '' } })

    expect(props.onValueChange).toHaveBeenCalledWith(null)
  })

  it('closes the editor on Enter', () => {
    const props = renderEditor()

    fireEvent.keyDown(dateInput(), { key: 'Enter' })

    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })

  it('stays open while focus moves from the date to the time input', () => {
    const props = renderEditor({ value: '2026-07-22T09:30' })

    fireEvent.focusOut(dateInput(), { relatedTarget: timeInput() })

    expect(props.stopEditing).not.toHaveBeenCalled()
  })

  it('closes the editor when focus leaves the group entirely', () => {
    const props = renderEditor({ value: '2026-07-22T09:30' })

    fireEvent.focusOut(timeInput(), { relatedTarget: null })

    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })
})

// Spec 0064: reused for the `date` editor kind (a Product Category attribute
// with no time component at all) via the `dateOnly` param.
describe('DateTimeCellEditor with dateOnly (spec 0064)', () => {
  it('renders a plain date control, announced with its own "Date" label (not "Date and time")', () => {
    renderEditor({ value: '2026-07-28', dateOnly: true })

    const input = screen.getByLabelText(i18n.t('table.dateEditor.label'))

    expect(input).toHaveAttribute('type', 'date')
    expect(input).toHaveValue('2026-07-28')
    expect(screen.queryByLabelText(i18n.t(TIME_LABEL))).not.toBeInTheDocument()
  })

  it('commits the picked value as YYYY-MM-DD, unparsed', () => {
    const props = renderEditor({ dateOnly: true })

    fireEvent.change(screen.getByLabelText(i18n.t('table.dateEditor.label')), {
      target: { value: '2026-08-01' },
    })

    expect(props.onValueChange).toHaveBeenCalledWith('2026-08-01')
  })

  it('closes the editor on Enter and on blur', () => {
    const props = renderEditor({ dateOnly: true })
    const input = screen.getByLabelText(i18n.t('table.dateEditor.label'))

    fireEvent.keyDown(input, { key: 'Enter' })
    fireEvent.blur(input)

    expect(props.stopEditing).toHaveBeenCalledTimes(2)
  })
})
