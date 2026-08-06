import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityDetailSections } from '@/features/opportunities/opportunity-detail-sections'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * Spec 0080: the manager list shows the resolved role label as VISIBLE text
 * (never a `title`-only tooltip — invisible on touch, unreliable for screen
 * readers), falling back to today's shared default with no configuration.
 */

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function opportunity(overrides: Partial<OpportunityDetailWithPermissions> = {}): OpportunityDetailWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: 10,
    registry: { id: 10, name: 'Acme S.p.A.' },
    status: { source: 'default', distinct_count: 0, entries: [] },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: null,
    source: null,
    product_lines: [],
    lead_id: null,
    lead: null,
    managers: [
      { id: 200, name: 'Anna Bianchi', position: 1 },
      { id: 201, name: 'Marco Gialli', position: 2 },
    ],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    locked_fields: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('OpportunityDetailSections — manager role labels (spec 0080)', () => {
  it('AC-032: shows the shared default "Account manager n" as VISIBLE text with no configuration', () => {
    render(<OpportunityDetailSections opportunity={opportunity()} />)

    expect(screen.getByText('Account manager 1')).toBeInTheDocument()
    expect(screen.getByText('Account manager 2')).toBeInTheDocument()
  })

  it("AC-044: shows the opportunity's resolved override as VISIBLE text, unconfigured positions keep the default", () => {
    render(<OpportunityDetailSections opportunity={opportunity({ manager_labels: { '1': 'Commercial' } })} />)

    expect(screen.getByText('Commercial')).toBeInTheDocument()
    expect(screen.getByText('Account manager 2')).toBeInTheDocument()
    expect(screen.queryByText('Account manager 1')).not.toBeInTheDocument()
  })

  it('AC-053 (amendment A1): a label configured past the 4th position shows the same as the first four', () => {
    render(
      <OpportunityDetailSections
        opportunity={opportunity({
          managers: [
            { id: 200, name: 'Anna Bianchi', position: 1 },
            { id: 205, name: 'Elio Ferro', position: 5 },
          ],
          manager_labels: { '5': 'Field consultant' },
        })}
      />,
    )

    expect(screen.getByText('Account manager 1')).toBeInTheDocument()
    expect(screen.getByText('Field consultant')).toBeInTheDocument()
    expect(screen.queryByText('Account manager 5')).not.toBeInTheDocument()
  })
})
