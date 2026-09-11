import type { ReactElement } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render as rtlRender, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'

/**
 * The campaign record card (spec 0023 detail, rebuilt on the enterprise-CRM
 * record kit): identity band, KPI strip, linked records, geography. The 4 geo
 * fields carry the EFFECTIVE (merged) tuple (BR-5, spec 0027) — the view never
 * re-derives it, it only surfaces the inherited-levels hint.
 */

/** Every render goes through a Router: the card links related records with real `<Link>`s. */
function render(ui: ReactElement) {
  return rtlRender(ui, { wrapper: MemoryRouter })
}

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function campaign(
  overrides: Partial<CampaignDetailWithPermissions> = {},
): CampaignDetailWithPermissions {
  return {
    id: 1,
    code: 'CMP-0001',
    project_id: null,
    project: null,
    name: 'Spring push',
    description: null,
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    derived_from_project: false,
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
    geo_locked_levels: [],
    product_lines: [],
    start_date: null,
    end_date: null,
    total_budget: null,
    target_lead: null,
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
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('CampaignDetailView — identity band', () => {
  it('shows the name as the heading, the code as the subtitle and the pipeline status badge', () => {
    render(<CampaignDetailView campaign={campaign()} />)

    expect(screen.getByRole('heading', { name: 'Spring push' })).toBeInTheDocument()
    expect(screen.getByText('CMP-0001')).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
  })

  it('marks a campaign with no project as standalone', () => {
    render(<CampaignDetailView campaign={campaign({ derived_from_project: false })} />)

    expect(screen.getByText('Standalone')).toBeInTheDocument()
    expect(screen.queryByText('Linked to a project')).not.toBeInTheDocument()
  })

  it('marks a campaign reading through a project as linked', () => {
    render(<CampaignDetailView campaign={campaign({ derived_from_project: true })} />)

    expect(screen.getByText('Linked to a project')).toBeInTheDocument()
    expect(screen.queryByText('Standalone')).not.toBeInTheDocument()
  })

  it('renders no edit control when the actor may not update the campaign', () => {
    render(
      <CampaignDetailView
        campaign={campaign({
          permissions: {
            ...campaign().permissions,
            resource: { ...campaign().permissions.resource, update: false },
          },
        })}
        onEdit={() => {}}
      />,
    )

    expect(screen.queryByRole('button', { name: /edit/i })).not.toBeInTheDocument()
  })
})

describe('CampaignDetailView — linked records', () => {
  it('links the project, the Partner and the Sede to their own modules', () => {
    render(
      <CampaignDetailView
        campaign={campaign({
          project_id: 9,
          project: { id: 9, code: 'PRJ-0009', name: 'Acme rollout' },
          partner_id: 5,
          partner: { id: 5, name: 'Acme Partner' },
          operational_site_id: 8,
          operational_site: { id: 8, label: 'Warehouse A' },
        })}
      />,
    )

    expect(screen.getByRole('link', { name: /Acme rollout/ })).toHaveAttribute('href', '/projects/9')
    // The Partner is a Referent, NOT a User: it must resolve to /referents.
    expect(screen.getByRole('link', { name: /Acme Partner/ })).toHaveAttribute('href', '/referents/5')
    expect(screen.getByRole('link', { name: /Warehouse A/ })).toHaveAttribute(
      'href',
      '/operational-sites/8',
    )
  })

  it('shows the empty placeholder for every link the campaign does not have', () => {
    render(<CampaignDetailView campaign={campaign()} />)

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(3)
  })
})

describe('CampaignDetailView — geography (spec 0027)', () => {
  it('shows the effective tuple and the scope badge', () => {
    render(
      <CampaignDetailView
        campaign={campaign({
          state_id: 2,
          state: { id: 2, name: 'Lombardy' },
          geo_scope: 'state',
        })}
      />,
    )

    expect(screen.getByText('Italy')).toBeInTheDocument()
    expect(screen.getAllByText('Lombardy').length).toBeGreaterThan(0)
  })

  it('hints that the geo levels are inherited when the linked project owns them', () => {
    render(<CampaignDetailView campaign={campaign({ geo_locked_levels: ['country'] })} />)

    expect(
      screen.getByText(i18n.t('campaigns.form.geoInheritedFromProject')),
    ).toBeInTheDocument()
  })

  it('shows no inherited hint for a standalone campaign', () => {
    render(<CampaignDetailView campaign={campaign({ geo_locked_levels: [] })} />)

    expect(
      screen.queryByText(i18n.t('campaigns.form.geoInheritedFromProject')),
    ).not.toBeInTheDocument()
  })
})

describe('CampaignDetailView — description', () => {
  it('renders the description section only when there is one', () => {
    render(<CampaignDetailView campaign={campaign({ description: 'Push the spring catalogue.' })} />)

    expect(screen.getByText('Push the spring catalogue.')).toBeInTheDocument()
  })

  it('omits the section entirely when the description is null', () => {
    const { container } = render(<CampaignDetailView campaign={campaign({ description: null })} />)

    expect(container.textContent).not.toContain('Push the spring catalogue.')
  })
})

describe('CampaignDetailView — activity log section', () => {
  it('mounts the section for the viewed campaign when view_activity is granted', () => {
    render(
      <CampaignDetailView
        campaign={campaign({
          permissions: { ...campaign().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'campaigns', id: 1 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<CampaignDetailView campaign={campaign()} />)

    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
