import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { JobRoleForm } from '@/features/job-roles/job-role-form'
import type { JobRoleDetail } from '@/features/job-roles/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0271: the create form is a replica of the detail with closed rows; Save creates everything at once. */

const createJobRoleMock = vi.fn()

vi.mock('@/features/job-roles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/job-roles/api')>()),
  createJobRole: (...args: unknown[]) => createJobRoleMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

// The closed row names the picked ids through the for-select cache.
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (resource: string, params: { ids?: number[] }) =>
    Promise.resolve({
      items: (params.ids ?? []).map((id) => ({ id, label: `${resource} ${id}` })),
      export_link: null,
      pagination: { total: null, offset: 0, limit: 25, total_pages: null },
    }),
}))

// Controllable stand-in for the network-backed company picker.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value, onChange }: { value: number[]; onChange: (next: number[]) => void }) => (
    <button type="button" onClick={() => onChange([...value, 70])}>
      pick activity 70
    </button>
  ),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function created(overrides: Partial<JobRoleDetail> = {}): JobRoleDetail {
  return {
    id: 9,
    name: 'Clerk',
    description: null,
    job_activity_ids: [70],
    job_activities: [{ id: 70, name: 'job-activities 70' }],
    users_count: 0,
    created_at: '2026-10-16T08:00:00Z',
    updated_at: '2026-10-16T08:00:00Z',
    abilities: { update: true, delete: true },
    ...overrides,
  }
}

/** Anchors a translated label so a required field's trailing marker does not break the match. */
function labelFor(key: string): RegExp {
  return new RegExp(`^${i18n.t(key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)
}

function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('jobRoles.form.save') })[0])

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createJobRoleMock.mockReset()
})

describe('JobRoleForm - create (spec 0271)', () => {
  it('lists the name, description and job_activities rows', () => {
    render(<JobRoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    for (const key of ['name', 'description', 'jobActivities']) {
      expect(
        screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(`jobRoles.form.${key}`) }) }),
      ).toBeInTheDocument()
    }
  })

  it('shows the error on the closed row and does not call the API when name is empty', async () => {
    render(<JobRoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')

    openRow('jobRoles.form.name')
    expect(screen.getByLabelText(labelFor('jobRoles.form.name'))).toHaveAttribute('aria-invalid', 'true')
    expect(createJobRoleMock).not.toHaveBeenCalled()
  })

  it('submits the name and the picked company ids', async () => {
    createJobRoleMock.mockResolvedValue(created())
    const onSuccess = vi.fn()

    render(<JobRoleForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('jobRoles.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('jobRoles.form.name')), { target: { value: 'Clerk' } })
    openRow('jobRoles.form.jobActivities')
    fireEvent.click(screen.getByRole('button', { name: 'pick activity 70' }))
    save()

    await waitFor(() => expect(createJobRoleMock).toHaveBeenCalledTimes(1))
    expect(createJobRoleMock).toHaveBeenCalledWith({ name: 'Clerk', description: null, job_activity_ids: [70] })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(created()))
  })

  it('maps a server 422 on the name onto the form', async () => {
    createJobRoleMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )

    render(<JobRoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('jobRoles.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('jobRoles.form.name')), { target: { value: 'Clerk' } })
    save()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })
})
