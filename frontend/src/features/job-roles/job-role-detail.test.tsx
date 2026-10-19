import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { JobRoleDetailView } from '@/features/job-roles/job-role-detail'
import { moduleScreen } from '@/features/job-roles/job-role-screens'
import type { JobRoleDetailWithPermissions } from '@/features/job-roles/types'

/** Spec 0271: the detail edits every row in place, each save a PATCH of that field alone. */

const updateJobRoleMock = vi.fn()

vi.mock('@/features/job-roles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/job-roles/api')>()),
  updateJobRole: (...args: unknown[]) => updateJobRoleMock(...args),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value, onChange }: { value: number[]; onChange: (next: number[]) => void }) => (
    <button type="button" onClick={() => onChange(value.filter((id) => id !== 2))}>
      remove activity 2
    </button>
  ),
}))

function jobRole(overrides: Partial<JobRoleDetailWithPermissions> = {}): JobRoleDetailWithPermissions {
  return {
    id: 4,
    name: 'Clerk',
    description: 'Office work',
    job_activity_ids: [1, 2],
    job_activities: [
      { id: 1, name: 'Cleaning' },
      { id: 2, name: 'Filing' },
    ],
    users_count: 5,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    abilities: { update: true, delete: true },
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    ),
  })
}

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateJobRoleMock.mockReset()
})

describe('JobRoleDetailView - detail fields', () => {
  it('shows name, description, job_activities, people count and both timestamps', () => {
    render(<JobRoleDetailView jobRole={jobRole()} />)

    expect(screen.getByRole('heading', { name: 'Clerk' })).toBeInTheDocument()
    expect(screen.getByText('Office work')).toBeInTheDocument()
    expect(screen.getByText('Cleaning')).toBeInTheDocument()
    expect(screen.getByText('Filing')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when there are no job_activities', () => {
    render(<JobRoleDetailView jobRole={jobRole({ job_activity_ids: [], job_activities: [], description: 'x' })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('JobRoleDetailView - in-place editing', () => {
  it('has no Edit button and the registry generates no edit route', () => {
    render(<JobRoleDetailView jobRole={jobRole()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(moduleScreen.generateEditRoute).toBe(false)
    for (const key of ['name', 'description', 'jobActivities']) {
      expect(pencilOf(`jobRoles.form.${key}`)).toBeInTheDocument()
    }
    expect(pencilOf('jobRoles.detail.usersCount')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateJobRoleMock.mockResolvedValue(jobRole({ name: 'Manager' }))
    render(<JobRoleDetailView jobRole={jobRole()} />)

    fireEvent.click(pencilOf('jobRoles.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Manager' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateJobRoleMock).toHaveBeenCalledWith(4, { name: 'Manager' }))
  })

  it('edits the job_activities as a whole set', async () => {
    updateJobRoleMock.mockResolvedValue(jobRole({ job_activity_ids: [1] }))
    render(<JobRoleDetailView jobRole={jobRole()} />)

    fireEvent.click(pencilOf('jobRoles.form.jobActivities') as HTMLElement)
    fireEvent.click(screen.getByRole('button', { name: 'remove activity 2' }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateJobRoleMock).toHaveBeenCalledWith(4, { job_activity_ids: [1] }))
  })

  it('offers no pencil when update is not allowed', () => {
    const base = jobRole()
    render(
      <JobRoleDetailView
        jobRole={jobRole({ permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } } })}
      />,
    )

    expect(pencilOf('jobRoles.form.name')).not.toBeInTheDocument()
    expect(pencilOf('jobRoles.form.jobActivities')).not.toBeInTheDocument()
  })
})
