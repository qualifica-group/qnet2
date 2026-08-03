import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClientProvider, QueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { UsersTable } from '@/features/users/users-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { User } from '@/features/auth/types'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'

/**
 * Spec 0050 AC-023 — the Users adapter wires the generic table's 'impersonate'
 * row action to `useAuth().impersonate` and navigates home on success. The
 * generic `<TableView>` (AG Grid + SSRM) is outside this adapter's ownership
 * and is stubbed with a button that fires `onAction` for a fixed row,
 * mirroring the pattern of the other `*-table.test.tsx` suites.
 */

const ROW: TableRow = { id: 9, actions: ['view', 'edit', 'delete', 'impersonate'], name: 'Jane' }

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'venetian-mask', type: 'action', confirm: false }
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('impersonate'), ROW)}>
            trigger-impersonate
          </button>
        </div>
      )
    },
  ),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

const impersonateMock = vi.fn()
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
    impersonate: (...args: unknown[]) => impersonateMock(...args),
    stopImpersonation: vi.fn(),
  }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <UsersTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  impersonateMock.mockReset()
  navigateMock.mockReset()
  vi.mocked(toast.error).mockClear()
})

describe('UsersTable — impersonate row action (AC-023)', () => {
  it('starts impersonation for the row and navigates to the dashboard', async () => {
    impersonateMock.mockResolvedValue(undefined)
    renderTable()

    fireEvent.click(screen.getByText('trigger-impersonate'))

    await waitFor(() => expect(impersonateMock).toHaveBeenCalledWith(9))
    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/dashboard'))
  })

  it('shows an error toast and does not navigate when impersonation fails', async () => {
    impersonateMock.mockRejectedValue(new Error('forbidden'))
    renderTable()

    fireEvent.click(screen.getByText('trigger-impersonate'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to start impersonation. Please try again.'),
    )
    expect(navigateMock).not.toHaveBeenCalled()
  })
})
