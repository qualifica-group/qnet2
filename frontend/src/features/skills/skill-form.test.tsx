import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { SkillForm } from '@/features/skills/skill-form'
import type { Skill } from '@/features/skills/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** The create form replicates the detail with closed rows; Save creates everything at once. */

const createMock = vi.fn()
vi.mock('@/features/skills/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/skills/api')>()),
  createSkill: (...args: unknown[]) => createMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/staff-trainings/picker-stub')).PickerStub,
}))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}
vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

const CREATED: Skill = {
  id: 9,
  name: 'Inglese',
  category: null,
  description: null,
  is_active: true,
  job_roles: [],
  users_count: 0,
  created_at: '2026-10-19T08:00:00Z',
  updated_at: '2026-10-19T08:00:00Z',
}

const label = (key: string) => i18n.t(key)
const edit = (key: string) => screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label(key) }) })
const save = () => fireEvent.click(screen.getAllByRole('button', { name: label('skills.form.save') })[0])

function typeName(value: string) {
  fireEvent.click(edit('skills.form.name'))
  fireEvent.change(screen.getByRole('textbox'), { target: { value } })
}

function addJobRoleRow(jobRoleId: string) {
  fireEvent.click(screen.getByRole('button', { name: label('skills.form.addJobRole') }))
  const pickers = screen.getAllByRole('combobox', { name: label('skills.form.jobRole') })
  fireEvent.change(pickers[pickers.length - 1], { target: { value: jobRoleId } })
}

function rejection(status: number, errors: Record<string, string[]>) {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data: { success: false, message: 'Validation failed', errors },
  } as never)
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  createMock.mockReset()
})

describe('SkillForm - create (spec 0286)', () => {
  it('lists the catalogue rows with the agreed labels', () => {
    render(<SkillForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    for (const key of ['name', 'category', 'description', 'isActive', 'jobRoles']) {
      expect(edit(`skills.form.${key}`)).toBeInTheDocument()
    }
  })

  it('does not call the API when the name is empty', async () => {
    render(<SkillForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })
    save()

    expect(await screen.findByText(label('skills.form.nameRequired'))).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('creates with the job role rows as { id, min_level }, level 1 by default', async () => {
    createMock.mockResolvedValue(CREATED)
    const onSuccess = vi.fn()
    render(<SkillForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName(' Inglese ')
    fireEvent.click(edit('skills.form.jobRoles'))
    addJobRoleRow('3')
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        name: 'Inglese',
        category: null,
        description: null,
        is_active: true,
        job_roles: [{ id: 3, min_level: 1 }],
      }),
    )
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(CREATED))
  })

  it('sends the chosen minimum level of a row', async () => {
    createMock.mockResolvedValue(CREATED)
    render(<SkillForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Inglese')
    fireEvent.click(edit('skills.form.jobRoles'))
    addJobRoleRow('7')
    fireEvent.keyDown(screen.getByRole('combobox', { name: label('skills.form.minLevel') }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: `3 · ${label('skills.levels.3')}` }))
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith(expect.objectContaining({ job_roles: [{ id: 7, min_level: 3 }] })),
    )
  })

  it('refuses the same job role twice and sends nothing', async () => {
    render(<SkillForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Inglese')
    fireEvent.click(edit('skills.form.jobRoles'))
    addJobRoleRow('3')
    addJobRoleRow('3')
    save()

    expect(await screen.findByText(label('skills.form.jobRoleDuplicate'))).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('removes a row', async () => {
    createMock.mockResolvedValue(CREATED)
    render(<SkillForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Inglese')
    fireEvent.click(edit('skills.form.jobRoles'))
    addJobRoleRow('3')
    fireEvent.click(screen.getByRole('button', { name: label('skills.form.removeJobRole') }))
    save()

    await waitFor(() => expect(createMock).toHaveBeenCalledWith(expect.objectContaining({ job_roles: [] })))
  })

  it('maps a server 422 on a duplicated name onto the field', async () => {
    createMock.mockRejectedValue(rejection(422, { name: ['Nome già usato.'] }))
    render(<SkillForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Inglese')
    save()

    expect(await screen.findByText('Nome già usato.')).toHaveAttribute('role', 'alert')
  })
})
