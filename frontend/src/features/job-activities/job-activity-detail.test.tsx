import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { JobActivityDetailView } from '@/features/job-activities/job-activity-detail'
import { moduleScreen } from '@/features/job-activities/job-activity-screens'
import type { JobActivityDetailWithPermissions } from '@/features/job-activities/types'

/** Spec 0271: the detail edits every row in place, each save a PATCH of that field alone. */

const updateJobActivityMock = vi.fn()

vi.mock('@/features/job-activities/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/job-activities/api')>()),
  updateJobActivity: (...args: unknown[]) => updateJobActivityMock(...args),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function jobActivity(overrides: Partial<JobActivityDetailWithPermissions> = {}): JobActivityDetailWithPermissions {
  return {
    id: 4,
    name: 'Cleaning',
    description: 'Daily',
    job_role_ids: [1, 2],
    job_roles: [
      { id: 1, name: 'Clerk' },
      { id: 2, name: 'Manager' },
    ],
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
  updateJobActivityMock.mockReset()
})

describe('JobActivityDetailView - detail fields', () => {
  it('shows name, description, job roles and both timestamps', () => {
    render(<JobActivityDetailView jobActivity={jobActivity()} />)

    expect(screen.getByRole('heading', { name: 'Cleaning' })).toBeInTheDocument()
    expect(screen.getByText('Daily')).toBeInTheDocument()
    expect(screen.getByText('Clerk')).toBeInTheDocument()
    expect(screen.getByText('Manager')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when there are no job roles', () => {
    render(<JobActivityDetailView jobActivity={jobActivity({ job_role_ids: [], job_roles: [], description: 'x' })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('JobActivityDetailView - in-place editing', () => {
  it('has no Edit button and the registry generates no edit route', () => {
    render(<JobActivityDetailView jobActivity={jobActivity()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(moduleScreen.generateEditRoute).toBe(false)
    for (const key of ['name', 'description']) {
      expect(pencilOf(`jobActivities.form.${key}`)).toBeInTheDocument()
    }
    expect(pencilOf('jobActivities.detail.jobRoles')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateJobActivityMock.mockResolvedValue(jobActivity({ name: 'Filing' }))
    render(<JobActivityDetailView jobActivity={jobActivity()} />)

    fireEvent.click(pencilOf('jobActivities.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Filing' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateJobActivityMock).toHaveBeenCalledWith(4, { name: 'Filing' }))
  })

  it('offers no pencil when update is not allowed', () => {
    const base = jobActivity()
    render(
      <JobActivityDetailView
        jobActivity={jobActivity({ permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } } })}
      />,
    )

    expect(pencilOf('jobActivities.form.name')).not.toBeInTheDocument()
  })
})
