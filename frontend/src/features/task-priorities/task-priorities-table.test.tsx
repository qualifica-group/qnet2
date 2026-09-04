import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { TaskPrioritiesTable } from '@/features/task-priorities/task-priorities-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome
 * (`PageHeader`) are framework pieces outside this microtask's ownership:
 * they are stubbed so the suite stays focused on what THIS adapter is
 * responsible for — wiring `<Can>` around the reorder toggle and the create
 * button (spec 0101 AC-049), mounting
 * `<TableView domain="task-priorities">`, and the delete flow.
 *
 * Labels are resolved through `i18n.t` rather than hardcoded English: the
 * locale catalogue for this module is delivered by its own microtask, and the
 * suite must assert the wiring, not the copy.
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

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteTaskPriorityMock = vi.fn()
vi.mock('@/features/task-priorities/api', () => ({
  deleteTaskPriority: (...args: unknown[]) => deleteTaskPriorityMock(...args),
  fetchTaskPriority: vi.fn(),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'A row' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
        </button>
      </div>
    )
  }),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <TaskPrioritiesTable />
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
  deleteTaskPriorityMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('TaskPrioritiesTable — reorder toggle (spec 0101 D-4, rectified)', () => {
  it('shows the reorder button with task-priorities.update', () => {
    canMock.mockImplementation((permission) => permission === 'task-priorities.update')

    renderTable()

    expect(
      screen.getByRole('button', { name: i18n.t('taskPriorities.reorder.openButton') }),
    ).toBeInTheDocument()
  })

  it('hides the reorder button without task-priorities.update', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(
      screen.queryByRole('button', { name: i18n.t('taskPriorities.reorder.openButton') }),
    ).not.toBeInTheDocument()
  })
})

describe('TaskPrioritiesTable — mount', () => {
  it('mounts <TableView domain="task-priorities">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-task-priorities' })).toBeInTheDocument()
  })
})

describe('TaskPrioritiesTable — delete', () => {
  it("surfaces the backend's own message on a 409 (row used by a task, D-8b)", async () => {
    // Deliberately NOT the wording of `taskPriorities.form.deleteInUse`: that static
    // fallback says "used by a task", so a fixture identical to it would pass
    // whether the code surfaced the server's message or silently fell back.
    const message = 'This task priority is used by 3 tasks and cannot be deleted.'
    deleteTaskPriorityMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false, message },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteTaskPriorityMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(message))
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteTaskPriorityMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(i18n.t('taskPriorities.form.deleteForbidden')),
    )
  })

  it('shows the success toast on a successful delete', async () => {
    deleteTaskPriorityMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith(i18n.t('taskPriorities.form.deleted')),
    )
  })
})

/**
 * The badge renders only when a label is supplied, so a MISSING i18n key
 * degrades in SILENCE: no badge on any row, ever, while every test that mocks
 * the label stays green — a marker that never marks. That failure mode has
 * already bitten this module once (the key was added, then removed, while the
 * consuming code stayed in place), so it gets an assertion that discriminates
 * "the copy exists" from "there is simply nothing to show", instead of one
 * that passes either way.
 */
describe('TaskPrioritiesTable — inactive badge copy', () => {
  const KEY = 'taskPriorities.reorder.inactiveBadge'

  it.each(['en', 'it'])('resolves the %s copy from the real bundle, not as a raw key', (lng) => {
    // `getFixedT` rather than `changeLanguage`: no global side effect on the
    // shared i18n instance, so the surrounding suite is unaffected.
    const translate = i18n.getFixedT(lng)

    // Control, so this guard is not itself green-and-blind: i18next echoes the
    // key back when it is absent, which is precisely what the assertion below
    // detects. Without this line, a `t()` that silently returned '' would make
    // the guard pass over a missing key.
    expect(translate(`${KEY}.absent`)).toBe(`${KEY}.absent`)

    expect(translate(KEY)).not.toBe(KEY)
  })
})
