import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { MassImportDialog } from '@/features/migrations/mass-import-dialog'
import type { MassMigrationRun, MigrationPlan, MigrationRun } from '@/features/migrations/types'

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
    created_at: '2026-07-20T00:00:00Z',
    ...overrides,
  }
}

function pendingRun(sources: string[]): MassMigrationRun {
  return { id: 7, status: 'pending', sources, created_at: '2026-07-20T00:00:00Z', runs: [] }
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

    await waitFor(() => expect(startMassMigrationMock).toHaveBeenCalledTimes(1))
    // Terminal state: the Close button appears once the run completes.
    expect(await screen.findByRole('button', { name: 'Close' })).toBeInTheDocument()
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
