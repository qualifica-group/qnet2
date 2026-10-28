import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { CompletionCell } from '@/features/table/completion-cell'

function cell(value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  return render(<CompletionCell {...params} label="Avanzamento" />)
}

describe('CompletionCell', () => {
  it('renders the bar with its percentage', () => {
    cell(40)
    expect(screen.getByText('40%')).toBeInTheDocument()
    expect(screen.getByRole('progressbar', { name: 'Avanzamento' })).toBeInTheDocument()
  })

  it('renders a non-numeric value as an empty cell', () => {
    cell(null)
    expect(screen.queryByRole('progressbar')).not.toBeInTheDocument()
  })
})
