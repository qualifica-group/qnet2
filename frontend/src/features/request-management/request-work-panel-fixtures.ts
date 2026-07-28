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
    id: 1,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    source_id: null,
    source: null,
    reporter_id: null,
    reporter: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    opportunity_status: { id: 5, name: 'New', color: 'slate' },
    workflow_status: { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
    workflow_statuses: [
      { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
      { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: false },
    ],
    product_lines: [{ id: 1, business_function: { id: 40, name: 'Sales' }, product_category: { id: 500, name: 'Consulting' } }],
    // Mandatory since the user directive 2026-07-23: the panel starts with one.
    products_of_interest: [{ id: 700, name: 'Fibra 1000', product_category: { id: 500, name: 'Consulting' } }],
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
    applicable_attributes: [
      {
        id: 1,
        code: 'notes',
        name: 'Notes',
        type: 'text',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: true,
        sort_order: 1,
        options: [],
      },
      {
        id: 2,
        code: 'priority',
        name: 'Priority',
        type: 'enum',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: false,
        sort_order: 2,
        options: [
          { value: 'low', label: 'Low', color: null },
          { value: 'high', label: 'High', color: null },
        ],
      },
    ],
    attribute_values: { notes: 'Some notes', priority: 'low' },
    attribute_layout: null,
    next_callback_at: null,
    context: { estimated_value: 1234.5, expected_close_date: '2026-08-01', success_probability: null },
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}
