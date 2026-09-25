import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import { FULL_ACCESS_PERMISSIONS } from '@/features/tasks/task-fixtures'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import type { TaskFormMode } from '@/features/tasks/types'

/**
 * Spec 0146 D-3/AC-030: the "Fase" select — visible only with a commessa
 * picked and no parent, OPEN fasi only, prefilled from `ModuleCreateParams`
 * (`use-task-form-work-order-stage.test.tsx` covers the reset HANDLERS at
 * the hook level; this file covers the section's own wiring/visibility).
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

vi.mock('@/features/work-orders/task-board/api', () => ({ fetchWorkOrderStages: vi.fn() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const WORK_ORDER_PAGE = {
  items: [
    { id: 9, label: 'WO-1', meta: {} },
    { id: 10, label: 'WO-2', meta: {} },
  ],
  pagination: { offset: 0, limit: 25, total: 2 },
  export_link: null,
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderCreateForm(mode: Extract<TaskFormMode, { type: 'create' }> = { type: 'create' }) {
  return render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <TaskFormBody mode={mode} onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

const label = (key: string) => i18n.t(key)
const fasePicker = () => screen.queryByRole('combobox', { name: label('tasks.form.workOrderStage') })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string) =>
    Promise.resolve(resource === 'work-orders' ? WORK_ORDER_PAGE : EMPTY_PAGE),
  )
  vi.mocked(fetchWorkOrderStages).mockReset()
  vi.mocked(fetchWorkOrderStages).mockResolvedValue([
    { id: 1, name: 'Analisi', sort_order: 0, closed_at: null, closed_by: null, logged_minutes: 0 },
    { id: 2, name: 'Chiusa', sort_order: 1, closed_at: '2026-09-01T00:00:00Z', closed_by: null, logged_minutes: 0 },
  ])
})

describe('TaskLinksSection — "Fase" visible only with a commessa and no parent (D-3/AC-030)', () => {
  it('is absent without a commessa', () => {
    renderCreateForm()

    expect(fasePicker()).not.toBeInTheDocument()
  })

  it('appears once a commessa is prefilled', async () => {
    renderCreateForm({ type: 'create', workOrderId: 9 })

    await waitFor(() => expect(fasePicker()).toBeInTheDocument())
  })

  it('stays absent on a "crea sotto-task" prefill, even with a commessa (D-3)', async () => {
    renderCreateForm({ type: 'create', parentTaskId: 90, workOrderId: 9 })

    await waitFor(() => expect(fetchWorkOrderStages).not.toHaveBeenCalled())
    expect(fasePicker()).not.toBeInTheDocument()
  })
})

describe('TaskLinksSection — options are the commessa\'s OPEN fasi only (AC-030)', () => {
  it('lists the open fase but not the closed one', async () => {
    renderCreateForm({ type: 'create', workOrderId: 9 })
    await waitFor(() => expect(fasePicker()).not.toBeDisabled())

    fireEvent.click(fasePicker() as HTMLElement)

    expect(await screen.findByRole('option', { name: 'Analisi' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Chiusa' })).not.toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'No phase' })).toBeInTheDocument()
  })
})

describe('TaskLinksSection — resets when the commessa changes (D-3, wiring only)', () => {
  it('hides the fase field once the commessa is cleared, wherever it was left (AC-030)', async () => {
    renderCreateForm({ type: 'create', workOrderId: 9 })
    await waitFor(() => expect(fasePicker()).not.toBeDisabled())

    fireEvent.click(fasePicker() as HTMLElement)
    fireEvent.click(await screen.findByRole('option', { name: 'Analisi' }))
    expect(fasePicker()).toHaveTextContent('Analisi')

    // The trigger's own inline clear affordance (`AsyncPaginatedSelect`'s
    // `role="button"` X) fires the SAME `onValueChange` a re-pick would, and
    // needs no second popover to open — a far more robust interaction under
    // jsdom than driving two nested Radix popovers through `fireEvent`. The
    // VALUE reset itself (D-3, `useTaskForm.handleWorkOrderChange`) is unit
    // tested in `use-task-form-work-order-stage.test.tsx`; here the
    // observable proof is that a fase-less field simply disappears with its
    // commessa, rather than surviving hidden with a stale value.
    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: label('tasks.form.workOrder') })).toHaveTextContent('WO-1'),
    )
    fireEvent.click(screen.getByRole('button', { name: 'Clear WO-1' }))

    await waitFor(() => expect(fasePicker()).not.toBeInTheDocument())
  })
})
