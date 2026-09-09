import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { UserDetailView } from '@/features/users/user-detail'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0034, AC-015: the "Activity log" DetailSection mounts only when the
 * detail envelope grants `permissions.actions.view_activity`, and — when
 * mounted — renders the shared `ActivityLogSection` (verified here by id and
 * resource props, its own rendering is covered by activity-log-section.test.tsx).
 * Spec 0111 AC-025: the Employment section lists the competence PAIRS instead
 * of the single business function it used to show.
 */

const fetchUserMock = vi.fn()
const activityLogSectionMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function user(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 1,
    name: 'Jane Doe',
    email: 'jane@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** An employment profile carrying only what AC-025 is about; every other field is unset. */
function employment(productLines: EmploymentDetail['product_lines']): EmploymentDetail {
  return {
    id: 1,
    is_manager: false,
    job_description: null,
    relationship_type: null,
    qualification_type: null,
    hired_at: null,
    terminated_at: null,
    standard_daily_minutes: null,
    break_daily_minutes: null,
    reports_to_id: null,
    company_id: null,
    primary_operational_site_id: null,
    remote_operational_site_ids: [],
    product_lines: productLines,
    reports_to: null,
    company: null,
    primary_operational_site: null,
    remote_operational_sites: [],
  }
}

function renderDetail(userDetail: UserDetailWithPermissions) {
  fetchUserMock.mockResolvedValue(userDetail)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <UserDetailView userId={userDetail.id} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchUserMock.mockReset()
  activityLogSectionMock.mockReset()
})

describe('UserDetailView — activity log section (AC-015)', () => {
  it('mounts the section for the viewed user when view_activity is granted', async () => {
    renderDetail(user({ permissions: { ...user().permissions, actions: { view_activity: true } } }))

    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument())
    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'users', id: 1 })
  })

  it('hides the section when view_activity is not granted', async () => {
    renderDetail(user({ permissions: { ...user().permissions, actions: { view_activity: false } } }))

    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument())
    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('UserDetailView — competence pairs (spec 0111 AC-025)', () => {
  it('lists one function/category pair per persisted row', async () => {
    renderDetail(
      user({
        employment: employment([
          {
            id: 91,
            business_function: { id: 4, name: 'Sales' },
            product_category: { id: 21, name: 'Photovoltaic' },
          },
          {
            id: 92,
            business_function: { id: 5, name: 'Marketing' },
            product_category: { id: 200, name: 'Campaigns' },
          },
        ]),
      }),
    )

    await waitFor(() => expect(screen.getByText('Competence')).toBeInTheDocument())
    const pairs = screen.getAllByRole('listitem')
    expect(pairs).toHaveLength(2)
    expect(pairs[0]).toHaveTextContent('Sales')
    expect(pairs[0]).toHaveTextContent('Photovoltaic')
    expect(pairs[1]).toHaveTextContent('Marketing')
    expect(pairs[1]).toHaveTextContent('Campaigns')
  })

  it('falls back to the empty marker for a user with no competence row', async () => {
    renderDetail(user({ employment: employment([]) }))

    await waitFor(() => expect(screen.getByText('Competence')).toBeInTheDocument())
    expect(screen.queryAllByRole('listitem')).toHaveLength(0)
  })
})
