import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { createTask } from '@/features/tasks/api'
import { openInlineEditor } from '@/features/tasks/task-detail-test-helpers'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import { FULL_ACCESS_PERMISSIONS } from '@/features/tasks/task-fixtures'

/**
 * Spec 0195 D-8 (user directive 2026-10-06): the create form's rows start
 * CLOSED like the detail's, empty or prefilled; "Fatto" keeps a value in the
 * draft, "Annulla" puts it back, and only the header's Salva validates and
 * creates — its refusals land on the closed rows.
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: () =>
      Promise.resolve({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
  }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function renderCreate() {
  return render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <TaskFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

const label = (key: string) => i18n.t(key)
const titleInput = () => screen.queryByRole('textbox', { name: label('tasks.form.title') })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(createTask).mockReset()
})

describe('Task create draft — rows start closed (spec 0195 D-8)', () => {
  it('shows no control until a row is opened, and a prefilled value closed', () => {
    renderCreate()

    expect(titleInput()).not.toBeInTheDocument()
    // The requester is prefilled with the connected actor (spec 0118 D-1).
    expect(screen.getAllByText('Utente Corrente').length).toBeGreaterThan(0)
  })

  it('"Done" keeps the typed value in the draft and closes the row', () => {
    renderCreate()
    openInlineEditor(label('tasks.form.title'))

    fireEvent.change(titleInput() as HTMLElement, { target: { value: 'Preparare il report' } })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.inlineEdit.apply') }))

    expect(titleInput()).not.toBeInTheDocument()
    // The closed row and the identity band both read it.
    expect(screen.getAllByText('Preparare il report').length).toBeGreaterThanOrEqual(2)
  })

  it('"Revert" puts the draft back as the row found it', async () => {
    renderCreate()
    openInlineEditor(label('tasks.form.title'))

    fireEvent.change(titleInput() as HTMLElement, { target: { value: 'Da scartare' } })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.inlineEdit.revert') }))

    await waitFor(() => expect(screen.queryByText('Da scartare')).not.toBeInTheDocument())
  })

  it('validates the whole draft on Save, reporting on the closed row, without creating', async () => {
    renderCreate()

    // The identity band's Salva (the footer repeats it).
    fireEvent.click(screen.getAllByRole('button', { name: label('tasks.form.save') })[0])

    await waitFor(() => expect(screen.getByText(label('tasks.form.titleRequired'))).toBeInTheDocument())
    expect(createTask).not.toHaveBeenCalled()
    expect(titleInput()).not.toBeInTheDocument()
  })
})
