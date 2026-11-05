import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { ChartOfAccountsScreen } from '@/features/chart-of-accounts/chart-of-accounts-screen'
import { ALL_ABILITIES, NO_ABILITIES, buildTree } from '@/features/chart-of-accounts/chart-tree-fixtures'
import type { ChartImportResult, ChartTree } from '@/features/chart-of-accounts/types'

const fetchChartTreeMock = vi.fn()
const exportMock = vi.fn()
const importMock = vi.fn()
const saveBlobMock = vi.fn()

vi.mock('@/features/chart-of-accounts/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/chart-of-accounts/api')>()),
  fetchChartTree: () => fetchChartTreeMock(),
  exportChartOfAccounts: (...args: unknown[]) => exportMock(...args),
  importChartOfAccounts: (...args: unknown[]) => importMock(...args),
}))
vi.mock('@/lib/download', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/download')>()),
  saveBlob: (...args: unknown[]) => saveBlobMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const ZERO = { groups: 0, accounts: 0, cost_sub_accounts: 0, revenue_sub_accounts: 0 }

function result(overrides: Partial<ChartImportResult> = {}): ChartImportResult {
  return {
    applied: false,
    summary: { rows: 3, create: { ...ZERO, groups: 1, accounts: 1 }, update: { ...ZERO, cost_sub_accounts: 1 } },
    errors: [],
    ...overrides,
  }
}

function renderScreen(tree: ChartTree) {
  fetchChartTreeMock.mockResolvedValue(tree)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={() => Promise.resolve(true)}>{children}</ConfirmContext.Provider>
    </QueryClientProvider>
  )
  return render(<ChartOfAccountsScreen />, { wrapper })
}

async function chooseFile() {
  const input = await screen.findByLabelText('File (xlsx or csv)')
  fireEvent.change(input, { target: { files: [new File(['x'], 'plan.csv', { type: 'text/csv' })] } })
}

async function openImportDialog() {
  fireEvent.click(await screen.findByRole('button', { name: 'Import' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('Chart toolbar export (spec 0349 AC-012)', () => {
  it('hides Export and Import without the abilities', async () => {
    renderScreen(buildTree({ abilities: NO_ABILITIES }))

    await screen.findByRole('button', { name: 'Expand all' })
    expect(screen.queryByRole('button', { name: 'Export' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Import' })).not.toBeInTheDocument()
  })

  it('shows only the button of the granted ability', async () => {
    const abilities = { ...NO_ABILITIES, chart_accounts: { ...NO_ABILITIES.chart_accounts, export: true } }
    renderScreen(buildTree({ abilities }))

    expect(await screen.findByRole('button', { name: 'Export' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Import' })).not.toBeInTheDocument()
  })

  it('downloads the file in the chosen format', async () => {
    const blob = new Blob(['x'])
    exportMock.mockResolvedValue({ blob, filename: 'chart-of-accounts-2026-11-04.csv' })
    renderScreen(buildTree({ abilities: ALL_ABILITIES }))

    fireEvent.keyDown(await screen.findByRole('button', { name: 'Export' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('menuitem', { name: 'CSV (.csv)' }))

    await waitFor(() => expect(saveBlobMock).toHaveBeenCalledWith(blob, 'chart-of-accounts-2026-11-04.csv'))
    expect(exportMock).toHaveBeenCalledWith('csv')
  })
})

describe('Chart import dialog (spec 0349 AC-013)', () => {
  it('previews counts on file selection and applies on confirm, then reloads the tree and toasts', async () => {
    importMock.mockResolvedValueOnce(result()).mockResolvedValueOnce(result({ applied: true }))
    renderScreen(buildTree())
    await openImportDialog()
    await chooseFile()

    expect(await screen.findByRole('row', { name: /^Groups 1 0$/ })).toBeInTheDocument()
    expect(importMock).toHaveBeenLastCalledWith(expect.any(File), true)
    const callsBefore = fetchChartTreeMock.mock.calls.length

    fireEvent.click(screen.getByRole('button', { name: 'Confirm import' }))

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('Import completed.'))
    expect(importMock).toHaveBeenLastCalledWith(expect.any(File), false)
    await waitFor(() => expect(fetchChartTreeMock.mock.calls.length).toBeGreaterThan(callsBefore))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('lists the errors by row and column and disables Confirm', async () => {
    importMock.mockResolvedValue(
      result({ errors: [{ row: 4, column: 'Parent code', message: 'Parent code not found.' }] }),
    )
    renderScreen(buildTree())
    await openImportDialog()
    await chooseFile()

    expect(await screen.findByRole('row', { name: '4 Parent code Parent code not found.' })).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('1 error')
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeDisabled()
  })

  it('shows the server message of a 422 on the file', async () => {
    importMock.mockRejectedValue(
      Object.assign(new Error('failed'), {
        isAxiosError: true,
        response: { status: 422, data: { message: 'invalid', errors: { file: ['Missing columns: Code.'] } } },
      }),
    )
    renderScreen(buildTree())
    await openImportDialog()
    await chooseFile()

    expect(await screen.findByRole('alert')).toHaveTextContent('Missing columns: Code.')
    expect(screen.getByRole('button', { name: 'Confirm import' })).toBeDisabled()
  })
})
