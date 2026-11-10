import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { MassImportDialog } from '@/features/migrations/mass-import-dialog'
import type {
  MassMigrationRun,
  MigrationPlan,
  MigrationRun,
} from '@/features/migrations/types'

/**
 * Spec 0046 AC-014: the "Import all" dialog confirms the enabled sources (from
 * the saved plan), starts the aggregate run and polls to a terminal state,
 * showing per-source progress. On stop-on-failure the sources after the failing
 * one read as "Not run". The API module is mocked.
 */

const fetchMigrationPlanMock = vi.fn()
const startMassMigrationMock = vi.fn()
const fetchMassMigrationRunMock = vi.fn()

vi.mock('@/features/migrations/api', () => ({
  fetchMigrationPlan: (...args: unknown[]) => fetchMigrationPlanMock(...args),
  startMassMigration: (...args: unknown[]) => startMassMigrationMock(...args),
  fetchMassMigrationRun: (...args: unknown[]) => fetchMassMigrationRunMock(...args),
}))

const PLAN: MigrationPlan = {
  sources: [
    { source: 'companies', label: 'Companies', enabled: true, available: true },
    { source: 'users', label: 'Users', enabled: true, available: true },
    { source: 'roles', label: 'Roles', enabled: false, available: true },
  ],
}

function childRun(source: string, overrides: Partial<MigrationRun> = {}): MigrationRun {
  return {
    id: source.length,
    source,
    status: 'completed',
    total_rows: 1,
    created_rows: 1,
    skipped_rows: 0,
    failed_rows: 0,
    report: null,
    updated_since: null,
    update_existing: false,
    updated_rows: 0,
    created_at: '2026-07-20T00:00:00Z',
    ...overrides,
  }
}

function pendingRun(sources: string[]): MassMigrationRun {
  return {
    id: 7,
    status: 'pending',
    sources,
    updated_since: null,
    update_existing: false,
    created_at: '2026-07-20T00:00:00Z',
    runs: [],
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMigrationPlanMock.mockReset().mockResolvedValue(PLAN)
  startMassMigrationMock.mockReset()
  fetchMassMigrationRunMock.mockReset()
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

describe('MassImportDialog', () => {
  it('lists only the enabled sources, then starts and polls to completion', async () => {
    startMassMigrationMock.mockResolvedValue(pendingRun(['companies', 'users']))
    fetchMassMigrationRunMock.mockResolvedValue({
      ...pendingRun(['companies', 'users']),
      status: 'completed',
      runs: [childRun('companies'), childRun('users')],
    })

    render(<MassImportDialog open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    // Confirm step lists the enabled sources, not the disabled 'roles'.
    expect(await screen.findByText('Companies')).toBeInTheDocument()
    expect(screen.getByText('Users')).toBeInTheDocument()
    expect(screen.queryByText('Roles')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Start import' }))

    await waitFor(() => expect(startMassMigrationMock).toHaveBeenCalledWith({ updatedSince: null, updateExisting: false }))
    // Terminal state: the Close button appears once the run completes.
    expect(await screen.findByRole('button', { name: 'Close' })).toBeInTheDocument()
  })

  it('AC-013: starts with the chosen day for every source', async () => {
    startMassMigrationMock.mockResolvedValue({
      ...pendingRun(['companies', 'users']),
      updated_since: '2026-10-01',
    })
    fetchMassMigrationRunMock.mockResolvedValue({
      ...pendingRun(['companies', 'users']),
      status: 'completed',
      updated_since: '2026-10-01',
      runs: [
        childRun('companies'),
        childRun('users', { updated_since: '2026-10-01' }),
      ],
    })

    render(<MassImportDialog open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    await screen.findByText('Companies')
    fireEvent.change(screen.getByLabelText('Import only records created or modified since'), {
      target: { value: '2026-10-01' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Start import' }))
    await waitFor(() =>
      expect(startMassMigrationMock).toHaveBeenCalledWith({
        updatedSince: '2026-10-01',
        updateExisting: false,
      }),
    )

    // AC-014: the aggregate and the child show the start day.
    expect((await screen.findAllByText(/Since /, {}, { timeout: 3000 })).length).toBeGreaterThan(0)
  })

  it('AC-025: the realign checkbox is always there, off by default, and combines with the day', async () => {
    startMassMigrationMock.mockResolvedValue({
      ...pendingRun(['companies', 'users']),
      updated_since: '2026-10-01',
      update_existing: true,
    })
    fetchMassMigrationRunMock.mockResolvedValue({
      ...pendingRun(['companies', 'users']),
      status: 'completed',
      updated_since: '2026-10-01',
      update_existing: true,
      runs: [
        childRun('companies'),
        childRun('users', { update_existing: true, updated_rows: 4 }),
      ],
    })

    render(<MassImportDialog open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    await screen.findByText('Companies')
    const checkbox = screen.getByRole('checkbox', { name: 'Also update records already imported' })
    expect(checkbox).not.toBeChecked()
    expect(checkbox).toHaveAccessibleDescription(/Work orders, Quotes, Opportunities/)

    fireEvent.click(checkbox)
    fireEvent.change(screen.getByLabelText('Import only records created or modified since'), {
      target: { value: '2026-10-01' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Start import' }))

    await waitFor(() =>
      expect(startMassMigrationMock).toHaveBeenCalledWith({
        updatedSince: '2026-10-01',
        updateExisting: true,
      }),
    )
    // AC-026: the aggregate shows the mode and only the supporting child shows its updated count.
    expect(await screen.findByText('Update existing records', {}, { timeout: 3000 })).toBeInTheDocument()
    expect(screen.getByText(/4 updated/)).toBeInTheDocument()
    expect(screen.getAllByText(/updated/)).toHaveLength(1)
  })

  it('renders stop-on-failure: the source after the failing one is "Not run"', async () => {
    startMassMigrationMock.mockResolvedValue(pendingRun(['companies', 'users', 'roles']))
    fetchMassMigrationRunMock.mockResolvedValue({
      ...pendingRun(['companies', 'users', 'roles']),
      status: 'failed',
      runs: [childRun('companies'), childRun('users', { status: 'failed', created_rows: 0, failed_rows: 1 })],
    })

    render(<MassImportDialog open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    // Wait for the plan so the confirm list (and thus an enabled Start) renders.
    await screen.findByText('Companies')
    fireEvent.click(screen.getByRole('button', { name: 'Start import' }))

    await waitFor(() => expect(startMassMigrationMock).toHaveBeenCalledTimes(1))

    // 'roles' was never reached -> Not run; the stop message explains why.
    // Terminal state arrives via the poll (refetchInterval), so widen the wait.
    expect(await screen.findByText('Not run', {}, { timeout: 3000 })).toBeInTheDocument()
    expect(
      screen.getByText('The import stopped at the failing source; the sources after it were not run.'),
    ).toBeInTheDocument()
  })

  it('disables Start and explains when no source is enabled', async () => {
    fetchMigrationPlanMock.mockResolvedValue({
      sources: [{ source: 'companies', label: 'Companies', enabled: false, available: true }],
    })

    render(<MassImportDialog open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    expect(
      await screen.findByText('No sources are enabled. Enable at least one in “Configure order”.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Start import' })).toBeDisabled()
  })
})
