import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ReadonlyTableValue } from '@/features/custom-fields/components/readonly-table-value'
import type { TableFieldConfig } from '@/features/custom-fields/types'

const CONFIG: TableFieldConfig = {
  columns: [
    { key: 'inspection_date', label: 'Inspection date', type: 'date' },
    {
      key: 'outcome',
      label: 'Outcome',
      type: 'enum',
      options: [{ value: 'ok', label: 'Compliant' }],
    },
    { key: 'certified', label: 'Certified', type: 'boolean' },
  ],
  selectable: { key: 'is_last', label: 'Last inspection' },
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ReadonlyTableValue (spec 0180, AC-023)', () => {
  it('renders column labels, enum labels, yes/no and formats dates', () => {
    render(
      <ReadonlyTableValue
        config={CONFIG}
        value={{
          rows: [
            { id: 'a', inspection_date: '2026-10-12', outcome: 'ok', certified: true, is_last: true },
            { id: 'b', inspection_date: null, outcome: null, certified: false, is_last: false },
          ],
        }}
      />,
    )

    expect(screen.getByRole('columnheader', { name: 'Outcome' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Compliant' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: '12/10/2026' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Yes' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'No' })).toBeInTheDocument()
  })

  it('marks the selected row with accessible text, not colour only', () => {
    render(
      <ReadonlyTableValue
        config={CONFIG}
        value={{ rows: [{ id: 'a', certified: true, is_last: true }, { id: 'b', certified: false, is_last: false }] }}
      />,
    )

    const [, first, second] = screen.getAllByRole('row')
    expect(within(first).getByText('Last inspection')).toBeInTheDocument()
    expect(within(second).queryByText('Last inspection')).not.toBeInTheDocument()
  })

  it('shows the empty text when there are no rows', () => {
    render(<ReadonlyTableValue config={CONFIG} value={{ rows: [] }} />)

    expect(screen.getByText('No rows.')).toBeInTheDocument()
  })
})
