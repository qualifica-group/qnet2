import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { ChartOfAccountsScreen } from '@/features/chart-of-accounts/chart-of-accounts-screen'
import { NO_ABILITIES, buildTree, costSub } from '@/features/chart-of-accounts/chart-tree-fixtures'
import type { ChartTree } from '@/features/chart-of-accounts/types'

const fetchChartTreeMock = vi.fn()
const createRevenueTypeMock = vi.fn()
const deleteChartAccountMock = vi.fn()
const updateCostTypeMock = vi.fn()

vi.mock('@/features/chart-of-accounts/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/chart-of-accounts/api')>()),
  fetchChartTree: () => fetchChartTreeMock(),
  createRevenueType: (...args: unknown[]) => createRevenueTypeMock(...args),
  deleteChartAccount: (...args: unknown[]) => deleteChartAccountMock(...args),
}))

vi.mock('@/features/cost-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/cost-types/api')>()),
  updateCostType: (...args: unknown[]) => updateCostTypeMock(...args),
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
  const confirm = () => Promise.resolve(true)
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={confirm}>{children}</ConfirmContext.Provider>
    </QueryClientProvider>
  )
  return render(<ChartOfAccountsScreen />, { wrapper })
}

function loadTree(tree: ChartTree) {
  fetchChartTreeMock.mockResolvedValue(tree)
}

function openMenu(label: string) {
  fireEvent.keyDown(screen.getByRole('button', { name: `Actions for ${label}` }), { key: 'Enter' })
}

function httpError(status: number, data: Record<string, unknown>): AxiosError {
  return new AxiosError('failed', String(status), undefined, undefined, { status, data } as AxiosResponse)
}

const field = (label: string) => screen.getByLabelText(new RegExp(`^${label}`))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('ChartOfAccountsScreen - tree (spec 0348 AC-010, AC-011)', () => {
  it('shows the groups with their Cost / Revenue badge and dims the inactive entries', async () => {
    loadTree(buildTree())
    renderScreen()

    expect(await screen.findByText('Servizi')).toBeInTheDocument()
    expect(screen.getByText('Cost')).toBeInTheDocument()
    expect(screen.getByText('Revenue')).toBeInTheDocument()
    expect(screen.getByText('Manutenzioni').closest('li')).toHaveClass('text-muted-foreground')
    expect(screen.getAllByText('Inactive')).toHaveLength(1)
  })

  it('shows the "to place" group only when there are unplaced cost types', async () => {
    loadTree(buildTree())
    const { unmount } = renderScreen()
    await screen.findByText('Servizi')
    expect(screen.queryByText('Cost sub-accounts to place')).not.toBeInTheDocument()
    unmount()

    loadTree(buildTree({ unplaced_cost_types: [costSub({ id: 9, code: null, name: 'Legacy' })] }))
    renderScreen()
    expect(await screen.findByText('Cost sub-accounts to place')).toBeInTheDocument()
    expect(screen.getByText('Legacy')).toBeInTheDocument()
  })

  it('collapses a group and keeps that state after a search is cleared', async () => {
    loadTree(buildTree())
    renderScreen()
    await screen.findByText('Servizi')

    fireEvent.click(screen.getByRole('button', { name: 'Collapse 61 Servizi' }))
    expect(screen.queryByText('Utenze')).not.toBeInTheDocument()

    const search = screen.getByRole('searchbox', { name: 'Search the chart of accounts' })
    fireEvent.change(search, { target: { value: '70.01.001' } })
    expect(screen.getByText('Ricavi da consulenza')).toBeInTheDocument()
    expect(screen.queryByText('Servizi')).not.toBeInTheDocument()

    fireEvent.change(search, { target: { value: '' } })
    expect(screen.getByText('Servizi')).toBeInTheDocument()
    expect(screen.queryByText('Manutenzioni')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Expand 61 Servizi' })).toHaveAttribute('aria-expanded', 'false')
  })

  it('expands and collapses everything', async () => {
    loadTree(buildTree())
    renderScreen()
    await screen.findByText('Servizi')

    fireEvent.click(screen.getByRole('button', { name: 'Expand all' }))
    expect(screen.getByText('Ricavi da consulenza')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Collapse all' }))
    expect(screen.queryByText('Utenze')).not.toBeInTheDocument()
  })

  it('opens and closes a node with the arrow keys', async () => {
    loadTree(buildTree())
    renderScreen()
    await screen.findByText('Servizi')

    fireEvent.keyDown(screen.getByRole('button', { name: 'Collapse 61 Servizi' }), { key: 'ArrowLeft' })
    expect(screen.queryByText('Manutenzioni')).not.toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('button', { name: 'Expand 61 Servizi' }), { key: 'ArrowRight' })
    expect(screen.getByText('Manutenzioni')).toBeInTheDocument()
  })
})

describe('ChartOfAccountsScreen - abilities (spec 0348 AC-013)', () => {
  it('hides every create / edit / delete / history control without abilities', async () => {
    loadTree(buildTree({ abilities: NO_ABILITIES }))
    renderScreen()
    await screen.findByText('Servizi')

    expect(screen.queryByRole('button', { name: 'New group' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Actions for/ })).not.toBeInTheDocument()
  })
})

describe('ChartOfAccountsScreen - actions (spec 0348 AC-012, AC-013)', () => {
  it('creates a revenue sub-account on a revenue account, with the parent preset, and refreshes the tree', async () => {
    loadTree(buildTree())
    createRevenueTypeMock.mockResolvedValue({ id: 6 })
    renderScreen()
    await screen.findByText('Servizi')

    openMenu('70.01 Prestazioni di servizi')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'New sub-account' }))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('New revenue sub-account')).toBeInTheDocument()
    expect(within(dialog).getByRole('combobox')).toHaveTextContent('70.01 Prestazioni di servizi')

    fireEvent.change(field('Code'), { target: { value: '70.01.002' } })
    fireEvent.change(field('Name'), { target: { value: 'Ricavi da formazione' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRevenueTypeMock).toHaveBeenCalledTimes(1))
    expect(createRevenueTypeMock).toHaveBeenCalledWith({
      chart_account_id: 20,
      code: '70.01.002',
      name: 'Ricavi da formazione',
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(fetchChartTreeMock).toHaveBeenCalledTimes(2))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('opens the cost type fields (nature and color) for a sub-account of a cost account', async () => {
    loadTree(buildTree())
    renderScreen()
    await screen.findByText('Servizi')

    openMenu('61.03 Utenze')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'New sub-account' }))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('New cost sub-account')).toBeInTheDocument()
    expect(field('Nature')).toBeInTheDocument()
    expect(within(dialog).getByText('Color')).toBeInTheDocument()
  })

  it('keeps the form open and shows a 422 on the field and the access role alert', async () => {
    loadTree(buildTree())
    createRevenueTypeMock.mockRejectedValue(
      httpError(422, { message: 'invalid', errors: { code: ['codice gia usato nel piano dei conti'] } }),
    )
    renderScreen()
    await screen.findByText('Servizi')

    openMenu('70.01 Prestazioni di servizi')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'New sub-account' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.change(field('Code'), { target: { value: '70.01.001' } })
    fireEvent.change(field('Name'), { target: { value: 'Duplicato' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    const alert = await within(dialog).findByText('codice gia usato nel piano dei conti')
    expect(alert).toHaveAttribute('role', 'alert')
    expect(field('Code')).toHaveAttribute('aria-invalid', 'true')
    expect(field('Name')).toHaveValue('Duplicato')
  })

  it('validates the code on the client before calling the API', async () => {
    loadTree(buildTree())
    renderScreen()
    await screen.findByText('Servizi')

    openMenu('70.01 Prestazioni di servizi')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'New sub-account' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    expect(await within(dialog).findByText('The code is required.')).toHaveAttribute('role', 'alert')
    expect(createRevenueTypeMock).not.toHaveBeenCalled()
  })

  it('places an unplaced cost type by sending account and code', async () => {
    loadTree(buildTree({ unplaced_cost_types: [costSub({ id: 9, code: null, name: 'Legacy' })] }))
    updateCostTypeMock.mockResolvedValue({})
    renderScreen()
    await screen.findByText('Legacy')

    openMenu('Legacy')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Place in the chart' }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('Place cost type')).toBeInTheDocument()

    fireEvent.click(within(dialog).getAllByRole('combobox')[0])
    fireEvent.click(await screen.findByRole('option', { name: '61.03 Utenze' }))
    fireEvent.change(field('Code'), { target: { value: '61.03.009' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateCostTypeMock).toHaveBeenCalledTimes(1))
    expect(updateCostTypeMock).toHaveBeenCalledWith(9, { chart_account_id: 10, code: '61.03.009' })
  })

  it('shows the backend message of a 409 delete as a toast and does not close the tree', async () => {
    loadTree(buildTree())
    deleteChartAccountMock.mockRejectedValue(httpError(409, { message: 'la voce ha voci collegate' }))
    renderScreen()
    await screen.findByText('Servizi')

    openMenu('61 Servizi')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Delete' }))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('la voce ha voci collegate'))
    expect(deleteChartAccountMock).toHaveBeenCalledWith(1)
    expect(screen.getByText('Servizi')).toBeInTheDocument()
  })

  it('opens the activity history of the resource the row belongs to', async () => {
    loadTree(buildTree())
    renderScreen()
    await screen.findByText('Servizi')

    fireEvent.click(screen.getByRole('button', { name: 'Expand all' }))
    openMenu('70.01.001 Ricavi da consulenza')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Activity history' }))

    expect(await screen.findByText('activity:revenue-types:5')).toBeInTheDocument()
  })
})
