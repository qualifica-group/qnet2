import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** Four statuses covering every group D-5 branches on. */
const STATUS_PAGE = {
  items: [
    {
      id: 3,
      label: 'In lavorazione',
      meta: { system_key: 'open', group: 'open', completion_percentage: 25, color: 'blue', icon: null },
    },
    {
      id: 50,
      label: 'In validazione',
      meta: { system_key: null, group: 'in_validation', completion_percentage: 90, color: 'amber', icon: null },
    },
    {
      id: 6,
      label: 'Completato',
      meta: {
        system_key: 'closed_positive',
        group: 'closed_positive',
        completion_percentage: 100,
        color: 'green',
        icon: null,
      },
    },
    {
      id: 7,
      label: 'Annullato',
      meta: {
        system_key: 'closed_negative',
        group: 'closed_negative',
        completion_percentage: 0,
        color: 'red',
        icon: null,
      },
    },
  ],
  pagination: { offset: 0, limit: 25, total: 4 },
  export_link: null,
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** `actionPermissions` mirrors `task-detail.test.tsx`'s own helper: only the given flags set. */
function actionPermissions(actions: ResourcePermissions['actions']): ResourcePermissions {
  return { ...FULL_ACCESS_PERMISSIONS, actions }
}

function renderEditForm(permissions: ResourcePermissions) {
  return render(
    <ResourcePermissionsProvider permissions={permissions}>
      <TaskFormBody mode={{ type: 'edit', task: taskDetailWithPermissions() }} onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

const label = (key: string) => i18n.t(key)
const statusPicker = () => screen.getByRole('combobox', { name: label('tasks.form.status') })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string) =>
    Promise.resolve(resource === 'task-statuses' ? STATUS_PAGE : EMPTY_PAGE),
  )
})

/**
 * Spec 0123 D-5/AC-015: the Stato select (edit only) shows every option but
 * disables the ones a PATCH could never reach.
 */
describe('TaskClassificationSection — Stato options reserved to actions (AC-015)', () => {
  it('disables in_validation and closed_positive unconditionally', async () => {
    renderEditForm(actionPermissions({ close_via_status: true }))
    fireEvent.click(statusPicker())

    await waitFor(() => expect(screen.getByRole('option', { name: /In validazione/ })).toBeInTheDocument())
    expect(screen.getByRole('option', { name: /In validazione/ })).toHaveAttribute('aria-disabled', 'true')
    expect(screen.getByRole('option', { name: /Completato/ })).toHaveAttribute('aria-disabled', 'true')
  })

  it('leaves an open status selectable', async () => {
    renderEditForm(actionPermissions({ close_via_status: true }))
    fireEvent.click(statusPicker())

    await waitFor(() => expect(screen.getByRole('option', { name: /In lavorazione/ })).toBeInTheDocument())
    expect(screen.getByRole('option', { name: /In lavorazione/ })).toHaveAttribute('aria-disabled', 'false')
  })

  it('disables closed_negative when close_via_status is false', async () => {
    renderEditForm(actionPermissions({ close_via_status: false }))
    fireEvent.click(statusPicker())

    await waitFor(() => expect(screen.getByRole('option', { name: /Annullato/ })).toBeInTheDocument())
    expect(screen.getByRole('option', { name: /Annullato/ })).toHaveAttribute('aria-disabled', 'true')
  })

  it('leaves closed_negative selectable when close_via_status is true', async () => {
    renderEditForm(actionPermissions({ close_via_status: true }))
    fireEvent.click(statusPicker())

    await waitFor(() => expect(screen.getByRole('option', { name: /Annullato/ })).toBeInTheDocument())
    expect(screen.getByRole('option', { name: /Annullato/ })).toHaveAttribute('aria-disabled', 'false')
  })

  it('clicking a disabled option does not change the trigger label', async () => {
    renderEditForm(actionPermissions({ close_via_status: true }))
    fireEvent.click(statusPicker())

    await waitFor(() => expect(screen.getByRole('option', { name: /In validazione/ })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('option', { name: /In validazione/ }))

    expect(statusPicker()).toHaveTextContent('In lavorazione')
  })
})

/**
 * Spec 0126 D-4/AC-013: `permissions.actions.change_status` gates the whole
 * Stato select, on top of the per-option rule above.
 */
describe('TaskClassificationSection — Stato select gated by change_status (AC-013)', () => {
  it('disables the whole select when change_status is false', () => {
    renderEditForm(actionPermissions({ change_status: false, close_via_status: true }))

    expect(statusPicker()).toBeDisabled()
  })

  it('leaves the select enabled when change_status is true', () => {
    renderEditForm(actionPermissions({ change_status: true, close_via_status: true }))

    expect(statusPicker()).not.toBeDisabled()
  })
})

/** The lookup pickers render the configured colored badge, not a bare text label. */
describe('TaskClassificationSection — lookups render as badges', () => {
  it('shows the persisted status as a badge in the trigger and every option as a badge', async () => {
    renderEditForm(actionPermissions({ change_status: true, close_via_status: true }))

    expect(within(statusPicker()).getByText('In lavorazione').closest('[data-slot="badge"]')).not.toBeNull()

    fireEvent.click(statusPicker())

    const option = await screen.findByRole('option', { name: /Annullato/ })
    expect(within(option).getByText('Annullato').closest('[data-slot="badge"]')).not.toBeNull()
  })
})
