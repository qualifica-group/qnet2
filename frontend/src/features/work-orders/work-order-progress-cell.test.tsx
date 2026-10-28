import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { WorkOrderProgressCell } from '@/features/work-orders/work-order-progress-cell'

function renderCell(value: number, recordedOn: string | null, onOpen = vi.fn()) {
  const params = { value, data: { id: 7, completion_recorded_on: recordedOn } } as unknown as ICellRendererParams
  render(<WorkOrderProgressCell {...params} onOpen={onOpen} />)
  return onOpen
}

describe('WorkOrderProgressCell', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
  })

  it('shows the total with the last date below and opens the popup on click', () => {
    const onOpen = renderCell(130, '2026-10-10')
    const button = screen.getByRole('button', { name: 'Apri avanzamento commessa' })
    expect(button).toHaveTextContent('130%')
    expect(button).toHaveTextContent('10/10/2026')
    fireEvent.click(button)
    expect(onOpen).toHaveBeenCalledWith(7)
  })

  it('shows only a plus icon when nothing is recorded, still clickable', () => {
    const onOpen = renderCell(0, null)
    const button = screen.getByRole('button', { name: 'Apri avanzamento commessa' })
    expect(button).not.toHaveTextContent('%')
    fireEvent.click(button)
    expect(onOpen).toHaveBeenCalledWith(7)
  })
})
