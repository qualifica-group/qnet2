import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import StaffCoursesPage from '@/features/staff-courses/staff-courses-page'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteMock = vi.fn()
vi.mock('@/features/staff-courses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/staff-courses/api')>()),
  deleteStaffCourse: (...args: unknown[]) => deleteMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: (...args: unknown[]) => toastErrorMock(...args) },
}))

const DELETE_ACTION: TableActionDefinition = { key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger', confirm: true }
const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Sicurezza' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: (action: TableActionDefinition, row: TableRow) => void }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
      return (
        <button type="button" data-domain={domain} onClick={() => onAction(DELETE_ACTION, ROW)}>
          delete-row
        </button>
      )
    },
  ),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <StaffCoursesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function rejection(status: number) {
  return new AxiosError('error', String(status), undefined, undefined, { status, data: {} } as never)
}

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('StaffCoursesPage', () => {
  it('shows the forbidden message without staff-courses.view', () => {
    canMock.mockReturnValue(false)
    renderPage()

    expect(screen.getByText(label('staffCourses.forbidden'))).toBeInTheDocument()
  })

  it('mounts the grid of the staff-courses domain and shows "New course" with create', () => {
    renderPage()

    expect(screen.getByRole('button', { name: 'delete-row' })).toHaveAttribute('data-domain', 'staff-courses')
    expect(screen.getByRole('button', { name: label('staffCourses.form.newCourse') })).toBeInTheDocument()
  })

  it('hides "New course" without the create permission', () => {
    canMock.mockImplementation((permission) => permission !== 'staff-courses.create')
    renderPage()

    expect(screen.queryByRole('button', { name: label('staffCourses.form.newCourse') })).not.toBeInTheDocument()
  })

  it('deletes and confirms with a toast', async () => {
    deleteMock.mockResolvedValue(undefined)
    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'delete-row' }))

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith(label('staffCourses.form.deleted')))
  })

  it('tells to deactivate when the course has trainings (422), distinct from 403 and generic', async () => {
    renderPage()
    for (const [status, key] of [
      [422, 'staffCourses.form.deleteInUse'],
      [403, 'common.forbidden'],
      [500, 'staffCourses.form.deleteError'],
    ] as const) {
      deleteMock.mockRejectedValueOnce(rejection(status))
      fireEvent.click(screen.getByRole('button', { name: 'delete-row' }))
      await waitFor(() => expect(toastErrorMock).toHaveBeenLastCalledWith(label(key)))
    }
  })
})
