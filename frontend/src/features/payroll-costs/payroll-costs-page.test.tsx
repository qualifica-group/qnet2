import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import PayrollCostsPage from '@/features/payroll-costs/payroll-costs-page'
import type { PayrollImportResult } from '@/features/payroll-costs/types'

const canMock = vi.fn<(permission: string) => boolean>()
const importMock = vi.fn()
const refreshMock = vi.fn()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))
vi.mock('@/features/payroll-costs/api', () => ({
  importPayrollCosts: (...args: unknown[]) => importMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string }>(function TableViewStub({ domain }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock }))
    return <div role="region" aria-label={`table-${domain}`} />
  }),
}))

function result(overrides: Partial<PayrollImportResult> = {}): PayrollImportResult {
  return {
    applied: false,
    period: '2026-10',
    summary: { rows: 128, people: 123, companies: 12, total_cost: 250000.5, replaced_rows: 0 },
    errors: [],
    ...overrides,
  }
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PayrollCostsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function openDialogAndChooseFile() {
  fireEvent.click(await screen.findByRole('button', { name: 'Import' }))
  const input = await screen.findByLabelText('File (csv, txt or xlsx)')
  fireEvent.change(input, { target: { files: [new File(['x'], 'costs.csv', { type: 'text/csv' })] } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
})

describe('PayrollCostsPage', () => {
  it('renders the grid of the payroll-costs domain', () => {
    renderPage()
    expect(screen.getByRole('region', { name: 'table-payroll-costs' })).toBeInTheDocument()
  })

  it('shows the forbidden message and no grid without view', () => {
    canMock.mockReturnValue(false)
    renderPage()
    expect(screen.getByText("You don't have permission to view the payroll costs.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-payroll-costs' })).not.toBeInTheDocument()
  })

  it('hides the Import button without the import ability', () => {
    canMock.mockImplementation((permission) => permission === 'payroll-costs.view')
    renderPage()
    expect(screen.getByRole('region', { name: 'table-payroll-costs' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Import' })).not.toBeInTheDocument()
  })

  it('previews the file with month, counts, total and replaced rows, confirm enabled', async () => {
    importMock.mockResolvedValue(result({ summary: { rows: 128, people: 123, companies: 12, total_cost: 250000.5, replaced_rows: 3 } }))
    renderPage()
    await openDialogAndChooseFile()

    expect(await screen.findByText('October 2026')).toBeInTheDocument()
    expect(screen.getByText('128')).toBeInTheDocument()
    expect(screen.getByText('123')).toBeInTheDocument()
    expect(screen.getByText(/250\.000,50/)).toBeInTheDocument()
    expect(screen.getByRole('status', { name: '' })).toHaveTextContent('3 rows will be replaced')
    expect(importMock).toHaveBeenCalledWith(expect.any(File), true)
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeEnabled()
  })

  it('lists row errors and disables Confirm', async () => {
    importMock.mockResolvedValue(
      result({ errors: [{ row: 5, column: 'Codice fiscale dipendente', message: 'Tax code not found' }] }),
    )
    renderPage()
    await openDialogAndChooseFile()

    expect(await screen.findByText('Tax code not found')).toBeInTheDocument()
    expect(screen.getByText('Codice fiscale dipendente')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('1 error')
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeDisabled()
  })

  it('applies the file, refreshes the grid and closes the dialog', async () => {
    importMock.mockImplementation((_file: File, dryRun: boolean) => Promise.resolve(result({ applied: !dryRun })))
    renderPage()
    await openDialogAndChooseFile()

    fireEvent.click(await screen.findByRole('button', { name: 'Confirm import' }))

    await waitFor(() => expect(refreshMock).toHaveBeenCalledTimes(1))
    expect(importMock).toHaveBeenLastCalledWith(expect.any(File), false)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})
