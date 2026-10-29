import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import SkillsPage from '@/features/skills/skills-page'
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
vi.mock('@/features/skills/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/skills/api')>()),
  deleteSkill: (...args: unknown[]) => deleteMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: (...args: unknown[]) => toastErrorMock(...args) },
}))

const DELETE_ACTION: TableActionDefinition = { key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger', confirm: true }
const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Inglese' }

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
        <SkillsPage />
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

describe('SkillsPage', () => {
  it('shows the forbidden message without skills.view', () => {
    canMock.mockReturnValue(false)
    renderPage()

    expect(screen.getByText(label('skills.forbidden'))).toBeInTheDocument()
  })

  it('mounts the grid of the skills domain and shows "New course" with create', () => {
    renderPage()

    expect(screen.getByRole('button', { name: 'delete-row' })).toHaveAttribute('data-domain', 'skills')
    expect(screen.getByRole('button', { name: label('skills.form.newSkill') })).toBeInTheDocument()
  })

  it('hides "New course" without the create permission', () => {
    canMock.mockImplementation((permission) => permission !== 'skills.create')
    renderPage()

    expect(screen.queryByRole('button', { name: label('skills.form.newSkill') })).not.toBeInTheDocument()
  })

  it('deletes and confirms with a toast', async () => {
    deleteMock.mockResolvedValue(undefined)
    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'delete-row' }))

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith(label('skills.form.deleted')))
  })

  it('tells to deactivate when the skill has people (422), distinct from 403 and generic', async () => {
    renderPage()
    for (const [status, key] of [
      [422, 'skills.form.deleteInUse'],
      [403, 'common.forbidden'],
      [500, 'skills.form.deleteError'],
    ] as const) {
      deleteMock.mockRejectedValueOnce(rejection(status))
      fireEvent.click(screen.getByRole('button', { name: 'delete-row' }))
      await waitFor(() => expect(toastErrorMock).toHaveBeenLastCalledWith(label(key)))
    }
  })
})
