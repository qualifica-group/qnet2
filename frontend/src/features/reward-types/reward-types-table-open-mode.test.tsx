import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { createRowActionsRenderer, type RowActionHandler } from '@/features/table/row-actions'
import { RewardTypesTable } from '@/features/reward-types/reward-types-table'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { ModuleOpenPreferences } from '@/features/modules/types'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { User } from '@/features/auth/types'

/**
 * AC-021 (spec 0058), split out of `reward-types-table.test.tsx`
 * (engineering.md §6, soft 300-line limit): view/edit/create must respect the
 * user's `module_open_preferences` (spec 0042 `resolveOpenMode`), not just
 * declare `defaultMode: 'modal'` structurally. This suite needs a DIFFERENT
 * mock shape from the rest of the domain's table tests — `@/features/auth/
 * use-auth` (mutable `module_open_preferences`) and a real `useNavigate` spy
 * — so `useModuleOpenMode`/`resolveOpenMode` run for REAL rather than the
 * forced-'modal' stub other suites can afford. Setup is duplicated on
 * purpose, not shared with `reward-types-table.test.tsx` (engineering.md §3:
 * no abstraction over a hypothetical repetition between two suites whose
 * mocks genuinely diverge).
 */
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const currentUser: User = {
  id: 1,
  name: 'Current User',
  email: 'current@example.com',
  locale: 'en',
  roles: [],
  avatar_url: null,
  created_at: null,
  module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
  ui_scale: 40,
  date_format: 'dmy',
  time_format: '24h',
}

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({
    user: currentUser,
    isAuthenticated: true,
    isInitializing: false,
    login: vi.fn(),
    logout: vi.fn(),
    impersonator: null,
    impersonate: vi.fn(),
    stopImpersonation: vi.fn(),
  }),
}))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    domain === 'reward-types'
      ? {
          domain: 'reward-types',
          basePath: '/reward-types',
          defaultMode: 'modal',
          labelKey: 'navigation.rewardTypes',
          DetailScreen: () => <div>detail-screen</div>,
          FormScreen: ({ onSuccess }: { onSuccess: (id: number) => void }) => (
            <button type="button" onClick={() => onSuccess(1)}>
              save-form
            </button>
          ),
        }
      : undefined,
}))

vi.mock('@/features/reward-types/api', () => ({
  deleteRewardType: vi.fn(),
  fetchRewardType: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EDIT_ACTION: TableActionDefinition = {
  key: 'edit',
  label: 'actions.edit',
  icon: 'pencil',
  type: 'action',
  confirm: false,
}

const ROW: TableRow = { id: 1, actions: ['edit'], name: 'Buono Amazon' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction?: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {} }))
      const Cell = onAction ? createRowActionsRenderer([EDIT_ACTION], onAction) : null
      return (
        <div role="region" aria-label={`table-${domain}`}>
          {/* RowActions calls useConfirm() unconditionally; EDIT_ACTION never
              confirms, so a resolved-true stub is enough — not asserted here. */}
          <ConfirmContext.Provider value={() => Promise.resolve(true)}>
            {Cell ? <Cell {...({ data: ROW } as ICellRendererParams)} /> : null}
          </ConfirmContext.Provider>
        </div>
      )
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RewardTypesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  navigateMock.mockReset()
  currentUser.module_open_preferences = DEFAULT_MODULE_OPEN_PREFERENCES
})

/** The registered `reward-types` entry's `defaultMode` is `modal` (D-4). */
describe('RewardTypesTable — open mode preference (AC-021)', () => {
  it('opens the sheet (no navigation) when the user has no override, using the module default', () => {
    currentUser.module_open_preferences = DEFAULT_MODULE_OPEN_PREFERENCES

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))

    expect(screen.getByRole('button', { name: 'save-form' })).toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('navigates to the dedicated pages when the global preference is "page"', () => {
    currentUser.module_open_preferences = { mode: 'page', overrides: {} }

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))

    expect(navigateMock).toHaveBeenCalledWith('/reward-types/1/edit')
    expect(screen.queryByRole('button', { name: 'save-form' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'New reward type' }))
    expect(navigateMock).toHaveBeenCalledWith('/reward-types/new')
  })

  it('lets a domain-specific override win over a "custom" global preference', () => {
    const prefs: ModuleOpenPreferences = { mode: 'custom', overrides: { 'reward-types': 'page' } }
    currentUser.module_open_preferences = prefs

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))

    expect(navigateMock).toHaveBeenCalledWith('/reward-types/1/edit')
    expect(screen.queryByRole('button', { name: 'save-form' })).not.toBeInTheDocument()
  })
})
