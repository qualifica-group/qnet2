import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { WorkOrderProformaCell } from '@/features/work-orders/work-order-proforma-cell'

/** Spec 0407: the "€" column of the Commesse grid, the former row action's colours and labels. */
function renderCell(value: unknown, onOpen = vi.fn()) {
  const params = { value, data: { id: 7, code: 'C-007' } } as unknown as ICellRendererParams
  const { container } = render(<WorkOrderProformaCell {...params} onOpen={onOpen} />)
  return { onOpen, container }
}

describe('WorkOrderProformaCell', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
  })

  it.each([
    ['none', 'Richiedi emissione proforma', 'text-muted-foreground'],
    ['pending', 'Proforma richiesta: apri la richiesta', 'text-blue-600'],
  ])('a "%s" commessa shows a clickable tinted euro that opens the request dialog', (status, label, tint) => {
    const { onOpen, container } = renderCell(status)
    const button = screen.getByRole('button', { name: label })

    expect(container.querySelector('svg')).toHaveClass(tint)
    fireEvent.click(button)
    expect(onOpen).toHaveBeenCalledWith({ id: 7, code: 'C-007' })
  })

  it('an issued commessa shows a yellow, inert euro labelled with its state', () => {
    const { onOpen, container } = renderCell('issued')

    expect(screen.queryByRole('button')).toBeNull()
    expect(screen.getByRole('img', { name: 'Proforma emessa' })).toBeInTheDocument()
    expect(container.querySelector('svg')).toHaveClass('text-yellow-600')
    expect(onOpen).not.toHaveBeenCalled()
  })

  it('renders nothing for an unknown value', () => {
    const { container } = renderCell(undefined)

    expect(container).toBeEmptyDOMElement()
  })
})
