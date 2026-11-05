import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { ChartOfAccountsScreen } from '@/features/chart-of-accounts/chart-of-accounts-screen'
import { FINANCIAL_GROUP, buildTree } from '@/features/chart-of-accounts/chart-tree-fixtures'

const fetchChartTreeMock = vi.fn()
const createTreasuryTypeMock = vi.fn()
const deleteTreasuryTypeMock = vi.fn()

vi.mock('@/features/chart-of-accounts/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/chart-of-accounts/api')>()),
  fetchChartTree: () => fetchChartTreeMock(),
  createTreasuryType: (...args: unknown[]) => createTreasuryTypeMock(...args),
  deleteTreasuryType: (...args: unknown[]) => deleteTreasuryTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))
vi.mock('@/features/activity-log/resource-activity-dialog', () => ({
  ResourceActivityDialog: ({ resource, row }: { resource: string; row: { id: number } | null }) =>
    row ? <div>{`activity:${resource}:${row.id}`}</div> : null,
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function renderScreen() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={() => Promise.resolve(true)}>{children}</ConfirmContext.Provider>
    </QueryClientProvider>
  )
  return render(<ChartOfAccountsScreen />, { wrapper })
}

function openMenu(label: string) {
  fireEvent.keyDown(screen.getByRole('button', { name: `Actions for ${label}` }), { key: 'Enter' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  const base = buildTree()
  fetchChartTreeMock.mockResolvedValue({ ...base, groups: [...base.groups, FINANCIAL_GROUP] })
})

describe('ChartOfAccountsScreen - financial sub-accounts (spec 0360 AC-024)', () => {
  it('shows the financial group with its "Financial" badge and its sub-account', async () => {
    renderScreen()

    expect(await screen.findByText('Movimenti finanziari')).toBeInTheDocument()
    expect(screen.getByText('Financial')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Expand all' }))
    expect(await screen.findByText('Giroconto tra conti')).toBeInTheDocument()
  })

  it('creates a financial sub-account on POST /treasury-types payload with the parent preset', async () => {
    createTreasuryTypeMock.mockResolvedValue({ id: 8 })
    renderScreen()
    await screen.findByText('Movimenti finanziari')

    openMenu('90.01 Giroconti')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'New sub-account' }))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('New financial sub-account')).toBeInTheDocument()
    expect(within(dialog).getByRole('combobox')).toHaveTextContent('90.01 Giroconti')

    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: '90.01.002' } })
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Giroconto interno' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createTreasuryTypeMock).toHaveBeenCalledTimes(1))
    expect(createTreasuryTypeMock).toHaveBeenCalledWith({
      chart_account_id: 30,
      code: '90.01.002',
      name: 'Giroconto interno',
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(fetchChartTreeMock).toHaveBeenCalledTimes(2))
  })

  it('deletes a financial sub-account through the treasury-types endpoint', async () => {
    deleteTreasuryTypeMock.mockResolvedValue(undefined)
    renderScreen()
    await screen.findByText('Movimenti finanziari')
    fireEvent.click(screen.getByRole('button', { name: 'Expand all' }))
    await screen.findByText('Giroconto tra conti')

    openMenu('90.01.001 Giroconto tra conti')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Delete' }))

    await waitFor(() => expect(deleteTreasuryTypeMock).toHaveBeenCalledWith(7))
  })
})
