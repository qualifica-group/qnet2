import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { CompletionCell } from '@/features/table/completion-cell'

function cell(value: unknown, data: Record<string, unknown>, dateField?: string) {
  const params = { value, data } as unknown as ICellRendererParams
  return render(<CompletionCell {...params} label="Avanzamento" dateField={dateField} />)
}

describe('CompletionCell', () => {
  it('shows the percentage and the row date below when a date field is given', () => {
    cell(60, { completion_recorded_on: '2026-10-10' }, 'completion_recorded_on')
    expect(screen.getByText('60%')).toBeInTheDocument()
    expect(screen.getByText('10/10/2026')).toBeInTheDocument()
  })

  it('omits the date when the row has none', () => {
    cell(0, { completion_recorded_on: null }, 'completion_recorded_on')
    expect(screen.getByText('0%')).toBeInTheDocument()
    expect(screen.queryByText(/\d{2}\/\d{2}\/\d{4}/)).not.toBeInTheDocument()
  })

  it('keeps the bar-only default without a date field', () => {
    cell(40, { completion_recorded_on: '2026-10-10' })
    expect(screen.getByText('40%')).toBeInTheDocument()
    expect(screen.queryByText('10/10/2026')).not.toBeInTheDocument()
  })
})
