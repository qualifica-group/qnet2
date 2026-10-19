import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { JobActivityForm } from '@/features/job-activities/job-activity-form'
import type { JobActivityDetail } from '@/features/job-activities/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0271: the create form is a replica of the detail with closed rows; Save creates everything at once. */

const createJobActivityMock = vi.fn()

vi.mock('@/features/job-activities/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/job-activities/api')>()),
  createJobActivity: (...args: unknown[]) => createJobActivityMock(...args),
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

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function created(overrides: Partial<JobActivityDetail> = {}): JobActivityDetail {
  return {
    id: 9,
    name: 'Cleaning',
    description: null,
    job_role_ids: [],
    job_roles: [],
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

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('jobActivities.form.save') })[0])

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createJobActivityMock.mockReset()
})

describe('JobActivityForm - create (spec 0271)', () => {
  it('lists the name and description rows', () => {
    render(<JobActivityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    for (const key of ['name', 'description']) {
      expect(
        screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(`jobActivities.form.${key}`) }) }),
      ).toBeInTheDocument()
    }
  })

  it('shows the error on the closed row and does not call the API when name is empty', async () => {
    render(<JobActivityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')

    openRow('jobActivities.form.name')
    expect(screen.getByLabelText(labelFor('jobActivities.form.name'))).toHaveAttribute('aria-invalid', 'true')
    expect(createJobActivityMock).not.toHaveBeenCalled()
  })

  it('submits the name and description', async () => {
    createJobActivityMock.mockResolvedValue(created())
    const onSuccess = vi.fn()

    render(<JobActivityForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('jobActivities.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('jobActivities.form.name')), { target: { value: 'Cleaning' } })
    save()

    await waitFor(() => expect(createJobActivityMock).toHaveBeenCalledTimes(1))
    expect(createJobActivityMock).toHaveBeenCalledWith({ name: 'Cleaning', description: null })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(created()))
  })

  it('maps a server 422 on the name onto the form', async () => {
    createJobActivityMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )

    render(<JobActivityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('jobActivities.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('jobActivities.form.name')), { target: { value: 'Cleaning' } })
    save()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })
})
