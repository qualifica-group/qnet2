import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * The work panel's shared test fixture (spec 0049): a fully populated
 * `RequestWorkPanelWithPermissions` with an override hook, extracted from
 * `request-work-panel.test.tsx` — pure data, no `vi.mock` (those stay in the
 * suite that hoists them), so any panel suite can import it instead of
 * rebuilding the shape.
 */

export const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

export function workPanel(overrides: Partial<RequestWorkPanelWithPermissions> = {}): RequestWorkPanelWithPermissions {
  return {
    // Deliberately DIFFERENT from `opportunity_id` below (spec 0086 D-9/D-10):
    // an assert that would pass with either value is not verifying the id
    // split at all. `id` (this one) is the Offerta/Quote id — field-change
    // requests and the Fonte picker's interception key on it (D-10).
    id: 4001,
    // The underlying Opportunity's id — documents, notes and activity history
    // key on THIS one instead (D-9).
    opportunity_id: 8001,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    // Mandatory since the user directive 2026-07-29: a savable request always
    // carries a Fonte, so the shared fixture does too (a suite that needs the
    // missing-source case overrides both keys).
    source_id: 30,
    source: { id: 30, name: 'Web' },
    reporter_id: null,
    reporter: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    is_transferred: false,
    transferred_from: null,
    status: { source: 'quotes', distinct_count: 1, entries: [{ id: 1, name: 'New', color: 'slate', group: 'open', count: 1 }] },
    product_lines: [{ id: 1, business_function: { id: 40, name: 'Sales' }, product_category: { id: 500, name: 'Consulting' } }],
    // Read-only offer lines (spec 0086 D-7): the panel starts with one.
    offer_lines: [{ id: 700, name: 'Fibra 1000', product_category: { id: 500, name: 'Consulting' } }],
    client_identity: {
      id: 1000,
      type: 'company',
      first_name: null,
      last_name: null,
      company_name: 'Acme S.p.A.',
      tax_code: null,
      vat_number: 'IT01234567897',
      sdi_code: null,
      birth_date: null,
      birth_city_id: null,
      residence_city_id: null,
      birth_city: null,
      residence_city: null,
      gender: null,
    },
    client_contacts: {
      owner: { type: 'personal_data', id: 1000 },
      items: [{ id: 1, type: 'email', label: null, value: 'client@acme.test', is_primary: true }],
    },
    client_address: null,
    referent_contacts: { owner: { type: 'personal_data', id: 2000 }, items: [] },
    next_callback_at: null,
    context: { estimated_value: 1234.5, expected_close_date: '2026-08-01', success_probability: null },
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}
