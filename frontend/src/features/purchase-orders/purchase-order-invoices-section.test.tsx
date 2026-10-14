import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'
import { PurchaseOrderInvoicesSection } from '@/features/purchase-orders/purchase-order-invoices-section'
import type { PurchaseOrder } from '@/features/purchase-orders/types'

const canMock = vi.hoisted(() => vi.fn())
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

function orderWithInvoices(): PurchaseOrder {
  return makePurchaseOrder({
    purchase_invoices: [
      { id: 41, document_type: 'invoice', document_number: 'FT-7', document_date: '2026-10-10' },
      { id: 42, document_type: 'proforma', document_number: null, document_date: null },
    ],
  })
}

function renderSection(order: PurchaseOrder) {
  return render(
    <MemoryRouter>
      <PurchaseOrderInvoicesSection order={order} />
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('Linked invoices section (spec 0255 AC-004)', () => {
  it('links each document to the invoice detail when the actor can view purchase invoices', () => {
    canMock.mockImplementation((permission: string) => permission === 'purchase-invoices.view')
    renderSection(orderWithInvoices())

    expect(screen.getByRole('link', { name: 'Invoice FT-7' })).toHaveAttribute('href', '/purchase-invoices/41')
    expect(screen.getByRole('link', { name: 'Proforma' })).toHaveAttribute('href', '/purchase-invoices/42')
    expect(screen.getByText('10/10/2026')).toBeInTheDocument()
  })

  it('renders plain text without the view permission', () => {
    canMock.mockReturnValue(false)
    renderSection(orderWithInvoices())

    expect(screen.getByText('FT-7')).toBeInTheDocument()
    expect(screen.getByText('Proforma')).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('shows an explicit empty state', () => {
    canMock.mockReturnValue(true)
    renderSection(makePurchaseOrder())

    expect(screen.getByText('No invoice or proforma linked to this order.')).toBeInTheDocument()
  })
})
