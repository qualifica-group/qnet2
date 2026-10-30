import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { StaffCourseForm } from '@/features/staff-courses/staff-course-form'
import type { StaffCourse } from '@/features/staff-courses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** The create form replicates the detail with closed rows; Save creates everything at once. */

const createMock = vi.fn()
vi.mock('@/features/staff-courses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/staff-courses/api')>()),
  createStaffCourse: (...args: unknown[]) => createMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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

const CREATED: StaffCourse = {
  id: 9,
  name: 'Sicurezza',
  description: null,
  duration_minutes: 480,
  validity_months: 60,
  is_active: true,
  job_roles: [],
  trainings_count: 0,
  created_at: '2026-10-19T08:00:00Z',
  updated_at: '2026-10-19T08:00:00Z',
}

const edit = (key: string) =>
  screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })
const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('staffCourses.form.save') })[0])

function typeInto(key: string, value: string, role: 'textbox' | 'spinbutton' = 'textbox') {
  fireEvent.click(edit(key))
  fireEvent.change(screen.getByRole(role), { target: { value } })
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

describe('StaffCourseForm - create (spec 0284)', () => {
  it('lists the catalogue rows with the agreed labels', () => {
    render(<StaffCourseForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    for (const key of ['name', 'description', 'durationHours', 'validityMonths', 'isActive', 'jobRoles']) {
      expect(edit(`staffCourses.form.${key}`)).toBeInTheDocument()
    }
  })

  it('does not call the API when the name is empty', async () => {
    render(<StaffCourseForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText(i18n.t('staffCourses.form.nameRequired'))).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('refuses an invalid duration without calling the API', async () => {
    render(<StaffCourseForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeInto('staffCourses.form.name', 'Sicurezza')
    typeInto('staffCourses.form.durationHours', 'abc')
    save()

    expect(await screen.findByText(i18n.t('staffCourses.form.durationInvalid'))).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('creates with hours converted to minutes, the validity and the defaults', async () => {
    createMock.mockResolvedValue(CREATED)
    const onSuccess = vi.fn()
    render(<StaffCourseForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeInto('staffCourses.form.name', '  Sicurezza ')
    typeInto('staffCourses.form.durationHours', '8')
    typeInto('staffCourses.form.validityMonths', '60', 'spinbutton')
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        name: 'Sicurezza',
        description: null,
        duration_minutes: 480,
        validity_months: 60,
        is_active: true,
        job_role_ids: [],
      }),
    )
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(CREATED))
  })

  it('maps a server 422 on a duplicated name and on the duration onto their fields', async () => {
    createMock.mockRejectedValue(
      rejection(422, { name: ['Nome già usato.'], duration_minutes: ['Durata non valida.'] }),
    )
    render(<StaffCourseForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeInto('staffCourses.form.name', 'Sicurezza')
    save()

    const message = await screen.findByText('Nome già usato.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(await screen.findByText('Durata non valida.')).toBeInTheDocument()
  })
})
