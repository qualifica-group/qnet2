import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import { createRowActionsRenderer, type RowActionHandler } from '@/features/table/row-actions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { rewardTypeColumnRenderers } from '@/features/reward-types/column-renderers'
import { RewardTypesTable } from '@/features/reward-types/reward-types-table'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { User } from '@/features/auth/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and `PageHeader` are framework
 * pieces outside this microtask's ownership (mirrors
 * `opportunity-statuses-table.test.tsx`). `<TableView>` is stubbed to a
 * single delete row action rendered through the REAL
 * `createRowActionsRenderer` + `ConfirmContext`, so the confirm-before-delete
 * gate (`row-actions.tsx`, generic per spec 0058 context/confirm_is_generic)
 * is genuinely exercised, not bypassed (AC-019). The module registry is
 * stubbed with a trivial `FormScreen` proving `onSaved: refreshGrid` reaches
 * `useModuleOpener` (AC-020). `@/features/auth/use-auth` (not
 * `use-module-open-mode`) is mocked for the open-mode preference, so
 * `useModuleOpenMode` resolves for REAL to modal (the registry's
 * `defaultMode`) — AC-021's preference-switch assertions live in the sibling
 * `reward-types-table-open-mode.test.tsx` (split out, engineering.md §6).
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

const deleteRewardTypeMock = vi.fn()
vi.mock('@/features/reward-types/api', () => ({
  deleteRewardType: (...args: unknown[]) => deleteRewardTypeMock(...args),
  fetchRewardType: vi.fn(),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const refreshMock = vi.fn()
const confirmMock = vi.fn<ConfirmFn>()

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Buono Amazon' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: RowActionHandler; isBusy?: (row: TableRow) => boolean }
  >(function TableViewStub({ domain, onAction, isBusy }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock }))
    const Cell = onAction ? createRowActionsRenderer([DELETE_ACTION], onAction, { isBusy }) : null
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <ConfirmContext.Provider value={confirmMock}>
          {Cell ? <Cell {...({ data: ROW } as ICellRendererParams)} /> : null}
        </ConfirmContext.Provider>
      </div>
    )
  }),
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
  deleteRewardTypeMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
  refreshMock.mockReset()
  confirmMock.mockReset()
  confirmMock.mockResolvedValue(true)
  currentUser.module_open_preferences = DEFAULT_MODULE_OPEN_PREFERENCES
})

describe('rewardTypeColumnRenderers — cell renderers (AC-018)', () => {
  function renderCell(columnId: string, value: unknown) {
    const renderer = rewardTypeColumnRenderers[columnId]
    if (!renderer) {
      throw new Error(`Missing renderer for column "${columnId}"`)
    }
    return render(<>{renderer({ value } as unknown as ICellRendererParams)}</>)
  }

  it('renders the color column as a swatch + the localized token name', () => {
    renderCell('color', 'blue')
    expect(screen.getByText('Blue')).toBeInTheDocument()
  })

  it('renders the created_at column as a formatted date-time', () => {
    renderCell('created_at', '2026-01-01T09:00:00Z')
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
  })

  it('renders the updated_at column as a formatted date-time', () => {
    renderCell('updated_at', '2026-02-15T14:30:00Z')
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })
})

describe('RewardTypesTable — create button gating (AC-023)', () => {
  it('shows the create button with reward-types.create', () => {
    canMock.mockImplementation((permission) => permission === 'reward-types.create')

    renderTable()

    expect(screen.getByRole('button', { name: 'New reward type' })).toBeInTheDocument()
  })

  it('hides the create button without reward-types.create', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'New reward type' })).not.toBeInTheDocument()
  })
})

describe('RewardTypesTable — delete confirmation gate (AC-019)', () => {
  it('opens the confirm dialog before deleting and never calls the API when the user cancels', async () => {
    confirmMock.mockResolvedValueOnce(false)

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    await waitFor(() =>
      expect(confirmMock).toHaveBeenCalledWith(expect.objectContaining({ tone: 'destructive' })),
    )
    expect(deleteRewardTypeMock).not.toHaveBeenCalled()
    expect(toastSuccessMock).not.toHaveBeenCalled()
    expect(toastErrorMock).not.toHaveBeenCalled()
    expect(refreshMock).not.toHaveBeenCalled()
  })

  it('runs the delete once the user confirms', async () => {
    deleteRewardTypeMock.mockResolvedValue(undefined)

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(deleteRewardTypeMock).toHaveBeenCalledWith(1))
  })
})

describe('RewardTypesTable — delete outcome toasts (AC-019)', () => {
  it('shows the success toast and refreshes the grid on a successful delete', async () => {
    deleteRewardTypeMock.mockResolvedValue(undefined)

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Reward type deleted successfully.'),
    )
    expect(refreshMock).toHaveBeenCalled()
  })

  it('shows the forbidden toast on a 403', async () => {
    deleteRewardTypeMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this reward type.'),
    )
  })

  it('shows the in-use fallback toast on a 409 (BR-3, prewired branch)', async () => {
    deleteRewardTypeMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false },
      } as never),
    )

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('This reward type is in use and cannot be deleted.'),
    )
  })

  it('shows the generic error toast when the failure is not an Axios error', async () => {
    deleteRewardTypeMock.mockRejectedValue(new Error('network down'))

    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('Unable to delete the reward type. Please try again.'),
    )
  })
})

describe('RewardTypesTable — save refresh (AC-020)', () => {
  it('refreshes the grid after a successful save from the opener sheet', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'New reward type' }))
    expect(screen.getByRole('button', { name: 'save-form' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'save-form' }))

    expect(refreshMock).toHaveBeenCalled()
  })
})
