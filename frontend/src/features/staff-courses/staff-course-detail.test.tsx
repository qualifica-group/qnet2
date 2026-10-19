import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { StaffCourseDetailView } from '@/features/staff-courses/staff-course-detail'
import { moduleScreen } from '@/features/staff-courses/staff-course-screens'
import type { StaffCourseDetail } from '@/features/staff-courses/types'

/** The detail edits every row in place, each save a PATCH of that field alone. */

const updateMock = vi.fn()
vi.mock('@/features/staff-courses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/staff-courses/api')>()),
  updateStaffCourse: (...args: unknown[]) => updateMock(...args),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function course(update = true): StaffCourseDetail {
  return {
    id: 4,
    name: 'Sicurezza',
    description: null,
    duration_minutes: 450,
    validity_months: 60,
    is_active: true,
    job_roles: [{ id: 2, name: 'Operaio' }],
    trainings_count: 3,
    created_at: '2026-10-19T08:00:00Z',
    updated_at: '2026-10-19T08:00:00Z',
    permissions: {
      resource: { view: true, create: false, update, delete: false, export: false, import: false },
      fields: {},
      actions: {},
    },
  }
}

function renderDetail(node: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>{node}</MemoryRouter>
    </QueryClientProvider>,
  )
}

const pencil = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  updateMock.mockReset()
})

describe('StaffCourseDetailView', () => {
  it('shows hours, validity, job roles and the trainings count', () => {
    renderDetail(<StaffCourseDetailView course={course()} />)

    expect(screen.getByText('7.5')).toBeInTheDocument()
    expect(screen.getByText('60')).toBeInTheDocument()
    expect(screen.getByText('Operaio')).toBeInTheDocument()
    expect(screen.getByText(i18n.t('staffCourses.detail.trainingsCount'))).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  it('hides the edit pencils without the update permission', () => {
    renderDetail(<StaffCourseDetailView course={course(false)} />)

    expect(pencil('staffCourses.form.name')).not.toBeInTheDocument()
    expect(pencil('staffCourses.form.validityMonths')).not.toBeInTheDocument()
  })

  it('saves one field alone with a PATCH', async () => {
    updateMock.mockResolvedValue(course())
    renderDetail(<StaffCourseDetailView course={course()} />)

    fireEvent.click(pencil('staffCourses.form.validityMonths') as HTMLElement)
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '24' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(4, { validity_months: 24 }))
  })

  it('registers as a module with the staff-courses domain and no edit route', () => {
    expect(moduleScreen).toMatchObject({ domain: 'staff-courses', basePath: '/staff-courses', generateEditRoute: false })
  })
})
