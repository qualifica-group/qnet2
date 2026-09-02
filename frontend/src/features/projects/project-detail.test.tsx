import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectDetailView } from '@/features/projects/project-detail'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'

/** AC-044: the over-allocation warning shows only when `remaining_budget` is a negative amount. */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function project(overrides: Partial<ProjectDetailWithPermissions> = {}): ProjectDetailWithPermissions {
  return {
    id: 1,
    code: 'PRJ-0001',
    name: 'Acme rollout',
    description: null,
    pipeline_status_id: 3,
    pipeline_status: { id: 3, name: 'Active', color: 'blue' },
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: null,
    state: null,
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'country',
    product_lines: [],
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    start_date: null,
    end_date: null,
    total_budget: '1000.00',
    target_lead: null,
    allocated_budget: '1300.00',
    remaining_budget: '-300.00',
    campaigns_count: 2,
    created_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `projects` is not yet wired into `en.ts` (pending the wiring lane, see
  // handoff): registered here so the feature's own copy renders for real.
  i18n.addResourceBundle('en', 'translation', { projects: projectsEn }, true, true)
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('ProjectDetailView — budget over-allocation warning (AC-044)', () => {
  it('shows the warning with the exceeding amount when remaining_budget is negative', () => {
    render(<ProjectDetailView project={project()} />)

    const alert = screen.getByRole('alert')
    expect(alert).toHaveTextContent('300')
  })

  it('does not show the warning when remaining_budget is positive', () => {
    render(<ProjectDetailView project={project({ allocated_budget: '400.00', remaining_budget: '600.00' })} />)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('does not show the warning when remaining_budget is exactly zero', () => {
    render(<ProjectDetailView project={project({ allocated_budget: '1000.00', remaining_budget: '0.00' })} />)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('does not show the warning when total_budget (and so remaining_budget) is unset', () => {
    render(
      <ProjectDetailView
        project={project({ total_budget: null, allocated_budget: '0.00', remaining_budget: null })}
      />,
    )

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})

describe('ProjectDetailView — derived geo scope (spec 0027 AC-012)', () => {
  it('shows the scope badge and the geography fields for a city-scoped project', () => {
    render(
      <ProjectDetailView
        project={project({
          country_id: 1,
          country: { id: 1, name: 'Italy' },
          state_id: 2,
          state: { id: 2, name: 'Lombardy' },
          province_id: 3,
          province: { id: 3, name: 'Milan' },
          city_id: 4,
          city: { id: 4, name: 'Milan (city)' },
          geo_scope: 'city',
        })}
      />,
    )

    // "City" is both the scope badge label and the geography field label
    // (Geography section shows the full breakdown, the badge a compact
    // summary): assert presence via count rather than a single unique match.
    expect(screen.getAllByText('City').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Milan (city)').length).toBeGreaterThan(0)
    expect(screen.getByText('Italy')).toBeInTheDocument()
    expect(screen.getByText('Lombardy')).toBeInTheDocument()
  })

  it('shows no scope badge when the project has no geo at all (legacy row)', () => {
    render(<ProjectDetailView project={project({ country_id: null, country: null, geo_scope: null })} />)

    expect(screen.queryByText('National')).not.toBeInTheDocument()
  })
})

/** Spec 0034, AC-015: representative module for the activity log rollout beyond Users. */
describe('ProjectDetailView — activity log section', () => {
  it('mounts the section for the viewed project when view_activity is granted', () => {
    render(
      <ProjectDetailView
        project={project({ permissions: { ...project().permissions, actions: { view_activity: true } } })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'projects', id: 1 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(
      <ProjectDetailView
        project={project({ permissions: { ...project().permissions, actions: { view_activity: false } } })}
      />,
    )

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('ProjectDetailView — Sede (operational site)', () => {
  it('shows the linked Sede label', () => {
    render(
      <ProjectDetailView
        project={project({ operational_site_id: 8, operational_site: { id: 8, label: 'Warehouse A' } })}
      />,
    )

    expect(screen.getByText('Warehouse A')).toBeInTheDocument()
  })

  it('shows an empty placeholder when no Sede is linked', () => {
    render(<ProjectDetailView project={project({ operational_site_id: null, operational_site: null })} />)

    expect(screen.getByText('Site')).toBeInTheDocument()
    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })
})

/** Spec 0094: the scalar business_function/product_category pair is replaced by the shared read-only row list. */
describe('ProjectDetailView — product lines (spec 0094)', () => {
  it('lists every business-function + product-category row', () => {
    render(
      <ProjectDetailView
        project={project({
          product_lines: [
            { id: 1, business_function: { id: 5, name: 'Sales' }, product_category: { id: 6, name: 'Widgets' } },
            { id: 2, business_function: { id: 7, name: 'Support' }, product_category: { id: 8, name: 'Gadgets' } },
          ],
        })}
      />,
    )

    expect(screen.getByText('Sales')).toBeInTheDocument()
    expect(screen.getByText('Support')).toBeInTheDocument()
  })

  it('shows an empty placeholder when there are no rows', () => {
    render(<ProjectDetailView project={project({ product_lines: [] })} />)

    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })
})
