import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseInvoicesTable } from '@/features/purchase-invoices/purchase-invoices-table'
import type { PayslipImportResult } from '@/features/purchase-invoices/payslips/types'

const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())
const importMock = vi.hoisted(() => vi.fn())
const refreshMock = vi.hoisted(() => vi.fn())

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))
vi.mock('@/features/purchase-invoices/payslips/api', () => ({
  importPayslips: (...args: unknown[]) => importMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string }>(function TableViewStub({ domain }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock }))
    return <div role="region" aria-label={`table-${domain}`} />
  }),
}))

function result(overrides: Partial<PayslipImportResult> = {}): PayslipImportResult {
  return {
    applied: false,
    period: '2026-10',
    summary: { rows: 121, people: 118, companies: 9, total_net: 175185.76, created: 100, updated: 21, suppliers_created: 4, suppliers_linked: 117 },
    errors: [],
    warnings: [],
    ...overrides,
  }
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PurchaseInvoicesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function openDialogAndFill() {
  fireEvent.click(await screen.findByRole('button', { name: 'Import net pay' }))
  fireEvent.change(await screen.findByLabelText('File (csv, txt or xlsx)'), {
    target: { files: [new File(['x'], 'net.csv', { type: 'text/csv' })] },
  })
  fireEvent.change(screen.getByLabelText(/Payment date/), { target: { value: '2026-11-10' } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
})

describe('Payslip net pay import (spec 0431 AC-010)', () => {
  it('shows the button only with the import ability', () => {
    canMock.mockImplementation((permission) => permission !== 'purchase-invoices.import')
    renderPage()
    expect(screen.queryByRole('button', { name: 'Import net pay' })).not.toBeInTheDocument()
  })

  it('asks for file and payment date and previews only when both are set', async () => {
    importMock.mockResolvedValue(result())
    renderPage()
    fireEvent.click(await screen.findByRole('button', { name: 'Import net pay' }))
    fireEvent.change(await screen.findByLabelText('File (csv, txt or xlsx)'), {
      target: { files: [new File(['x'], 'net.csv')] },
    })
    expect(importMock).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeDisabled()

    fireEvent.change(screen.getByLabelText(/Payment date/), { target: { value: '2026-11-10' } })
    expect(await screen.findByText('October 2026')).toBeInTheDocument()
    expect(importMock).toHaveBeenCalledWith({ file: expect.any(File), paymentDate: '2026-11-10' }, true)
  })

  it('previews counts, net total, created/updated and suppliers, confirm enabled', async () => {
    importMock.mockResolvedValue(result())
    renderPage()
    await openDialogAndFill()

    expect(await screen.findByText('121')).toBeInTheDocument()
    expect(screen.getByText('118')).toBeInTheDocument()
    expect(screen.getByText(/175\.185,76/)).toBeInTheDocument()
    expect(screen.getByText('Payslips created').nextSibling).toHaveTextContent('100')
    expect(screen.getByText('Payslips updated').nextSibling).toHaveTextContent('21')
    expect(screen.getByText('Suppliers created').nextSibling).toHaveTextContent('4')
    expect(screen.getByText('Suppliers linked').nextSibling).toHaveTextContent('117')
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeEnabled()
  })

  it('lists errors and warnings row by row; errors disable Confirm', async () => {
    importMock.mockResolvedValue(
      result({
        errors: [{ row: 5, column: 'IMPORTO', message: 'Amount must be positive' }],
        warnings: [{ row: 8, column: null, message: 'No cost row for this person' }],
      }),
    )
    renderPage()
    await openDialogAndFill()

    expect(await screen.findByText('Amount must be positive')).toBeInTheDocument()
    expect(screen.getByText('No cost row for this person')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('1 error')
    expect(screen.getByRole('status', { name: '' })).toHaveTextContent('1 warning')
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeDisabled()
  })

  it('keeps Confirm enabled with warnings only', async () => {
    importMock.mockResolvedValue(result({ warnings: [{ row: 8, column: null, message: 'No cost row' }] }))
    renderPage()
    await openDialogAndFill()

    expect(await screen.findByText('No cost row')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeEnabled()
  })

  it('applies the file, refreshes the grid and closes the dialog', async () => {
    importMock.mockImplementation((_request: unknown, dryRun: boolean) => Promise.resolve(result({ applied: !dryRun })))
    renderPage()
    await openDialogAndFill()

    fireEvent.click(await screen.findByRole('button', { name: 'Confirm import' }))

    await waitFor(() => expect(refreshMock).toHaveBeenCalledTimes(1))
    expect(importMock).toHaveBeenLastCalledWith({ file: expect.any(File), paymentDate: '2026-11-10' }, false)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})
