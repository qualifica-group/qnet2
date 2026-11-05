import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { InvoiceLineRevenueTypeCell } from '@/features/invoices/invoice-line-revenue-type-cell'
import { setInvoiceLineRevenueType } from '@/features/invoices/api'
import { fetchRevenueTypesForSelect } from '@/features/revenue-types/api'
import type { InvoiceLine } from '@/features/invoices/types'

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  setInvoiceLineRevenueType: vi.fn(),
}))
vi.mock('@/features/revenue-types/api', () => ({ fetchRevenueTypesForSelect: vi.fn() }))
const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('sonner', () => ({ toast: { success: (m: string) => toastSuccess(m), error: (m: string) => toastError(m) } }))

const patchMock = vi.mocked(setInvoiceLineRevenueType)
const fetchMock = vi.mocked(fetchRevenueTypesForSelect)

const SUB = {
  id: 5,
  code: '70.01.001',
  name: 'Consulting',
  path: '70 Sales > 70.01 Services',
  is_active: true,
  group: { code: '70', name: 'Sales' },
  account: { code: '70.01', name: 'Services' },
}

function line(overrides: Partial<InvoiceLine> = {}): InvoiceLine {
  return {
    id: 11,
    quote_line_id: null,
    product: null,
    description: 'Line',
    quantity: '1',
    unit_price: '10.00',
    vat_rate: { id: 1, name: '22%', rate: '22.00' },
    net_amount: '10.00',
    vat_amount: '2.20',
    total_amount: '12.20',
    sort_order: 1,
    revenue_type_id: null,
    revenue_type: null,
    ...overrides,
  }
}

function renderCell(invoiceLine: InvoiceLine, invoiceId: number | undefined = 3) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <InvoiceLineRevenueTypeCell invoiceId={invoiceId} line={invoiceLine} position={1} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset().mockReturnValue(true)
  patchMock.mockReset().mockResolvedValue({ id: 11, revenue_type_id: 5, revenue_type: SUB })
  fetchMock.mockReset().mockResolvedValue({ items: [SUB], total: 1, offset: 0 })
  toastSuccess.mockReset()
  toastError.mockReset()
})

describe('InvoiceLineRevenueTypeCell (spec 0350 AC-014)', () => {
  it('shows "To be classified" for an unclassified line', () => {
    renderCell(line())

    expect(screen.getByRole('combobox', { name: 'Change the revenue sub-account of line 1' })).toHaveTextContent('To be classified')
  })

  it('changes the sub-account of the line through the dedicated endpoint', async () => {
    renderCell(line())

    fireEvent.click(screen.getByRole('combobox', { name: 'Change the revenue sub-account of line 1' }))
    fireEvent.click(await screen.findByRole('option', { name: '70.01.001 Consulting' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledWith(3, 11, 5))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('Revenue sub-account updated.'))
  })

  it('clears the sub-account by sending null', async () => {
    renderCell(line({ revenue_type_id: 5, revenue_type: SUB }))

    fireEvent.click(screen.getByRole('button', { name: 'Clear the sub-account' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledWith(3, 11, null))
  })

  it('reports a refused change without altering what is shown', async () => {
    patchMock.mockRejectedValue(new Error('422'))
    renderCell(line({ revenue_type_id: 5, revenue_type: SUB }))

    fireEvent.click(screen.getByRole('button', { name: 'Clear the sub-account' }))

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Unable to update the revenue sub-account. Try again.'))
    expect(screen.getByRole('combobox')).toHaveTextContent('70.01.001 Consulting')
  })

  it('is read-only without invoices.update', () => {
    canMock.mockReturnValue(false)
    renderCell(line({ revenue_type_id: 5, revenue_type: SUB }))

    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getByText('70.01.001 Consulting')).toBeInTheDocument()
  })

  it('shows the unclassified marker as text when read-only', () => {
    renderCell(line(), undefined)

    expect(screen.getByText('To be classified')).toBeInTheDocument()
  })
})
