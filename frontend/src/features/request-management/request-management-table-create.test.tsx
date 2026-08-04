import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { RowActionHandler } from '@/features/table/row-actions'

/**
 * Spec 0057 AC-011: the "New request" header affordance, gated by this
 * module's OWN `request-management.create` permission (`<Can>`, UX gate only
 * — the backend re-authorizes). Mirrors the other row-action suites'
 * `canMock` pattern (`request-management-table-documents.test.tsx`).
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

/**
 * The create form defaults its Operatore/Sede from the connected actor (user
 * directive 2026-08-04); this suite only checks that the form opens, so it runs
 * with no actor — the defaults have their own coverage in
 * `use-request-create-form-actor-defaults.test.ts`.
 */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true }),
}))

let requestManagementOpenMode: 'page' | 'modal' = 'page'
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => requestManagementOpenMode,
}))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void; clearSelection: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {}, clearSelection: () => {} }))
      return <div role="region" aria-label={`table-${domain}`} />
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      {/* The create form's anagrafica section mounts `ContactsManager`, whose
          delete flow needs the app-level confirm dialog (mirrors the base suite). */}
      <ConfirmDialogProvider>
        <MemoryRouter>
          <RequestManagementTable />
        </MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  requestManagementOpenMode = 'page'
  navigateMock.mockReset()
})

describe('RequestManagementTable — "New request" affordance (spec 0057 AC-011)', () => {
  it('hides the button without request-management.create', () => {
    canMock.mockReturnValue(false)
    renderTable()

    expect(screen.queryByRole('button', { name: 'New request' })).not.toBeInTheDocument()
  })

  it('shows the button with request-management.create and navigates to the deep-link create route in page mode', async () => {
    canMock.mockReturnValue(true)
    requestManagementOpenMode = 'page'
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'New request' }))

    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/request-management/new'))
  })

  it('opens the modal Sheet with the create form in modal mode, without navigating', async () => {
    canMock.mockReturnValue(true)
    requestManagementOpenMode = 'modal'
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'New request' }))

    expect(await screen.findByRole('heading', { name: 'Client details' })).toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })
})
