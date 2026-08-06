import type { OpportunityDetail } from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * Shared fixtures of the opportunity payload-builder suites, extracted when
 * the single test file crossed the 500-line hard limit (engineering.md §6):
 * `opportunity-form-payload.test.ts` (create) and
 * `opportunity-form-payload-update.test.ts` (PATCH sparse diff) build every
 * case from these two factories, so form values and stored detail can never
 * drift apart between the two halves.
 */

export function values(overrides: Partial<OpportunityFormValues> = {}): OpportunityFormValues {
  return {
    registry_id: 1,
    // Spec 0043 D-3: mandatory FK, mirrors registry_id.
    referent_id: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    source_id: null,
    // Spec 0056: never submit-blocking; the payload-diff tests cover it independently.
    operational_site_id: null,
    // Spec 0047: never submit-blocking; the payload-diff tests cover
    // each independently.
    state_id: null,
    product_lines: [],
    products_of_interest: [],
    rewards: [],
    manager_slots: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    // A-6: the form always holds a number (default 0) — never null.
    success_probability: 0,
    general_notes: null,
    ...overrides,
  }
}

export function createValues(overrides: Partial<OpportunityFormValues> = {}): OpportunityFormValues {
  return values({ supervisor_id: 9, ...overrides })
}

export function original(overrides: Partial<OpportunityDetail> = {}): OpportunityDetail {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: 1,
    registry: { id: 1, name: 'Acme S.p.A.' },
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
    operational_site_id: null,
    operational_site: null,
    state_id: null,
    product_lines: [],
    lead_id: null,
    lead: null,
    managers: [],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    locked_fields: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}
