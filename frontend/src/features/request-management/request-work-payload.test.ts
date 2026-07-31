import { describe, expect, it } from 'vitest'
import {
  buildRequestWorkPayload,
  seedAttributeValues,
} from '@/features/request-management/request-work-payload'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestWorkPanel } from '@/features/request-management/types'

/** Spec 0049 AC-062: the PATCH payload is sparse — only what actually changed. */

function panel(overrides: Partial<RequestWorkPanel> = {}): RequestWorkPanel {
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
    product_lines: [],
    products_of_interest: [],
    client_identity: null,
    client_contacts: { owner: { type: 'personal_data', id: 10 }, items: [] },
    client_address: null,
    referent_contacts: { owner: { type: 'personal_data', id: 20 }, items: [] },
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
        is_required: false,
        sort_order: 1,
        options: [],
      },
      {
        id: 2,
        code: 'budget',
        name: 'Budget',
        type: 'integer',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: false,
        sort_order: 2,
        options: [],
      },
    ],
    attribute_values: { notes: 'existing note', budget: 1000 },
    next_callback_at: null,
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    ...overrides,
  }
}

function formValues(overrides: Partial<RequestWorkFormValues> = {}): RequestWorkFormValues {
  return {
    opportunity_workflow_status_id: 100,
    next_callback_at: null,
    note: '',
    client_identity: null,
    client_contacts: [],
    client_address: [],
    products_of_interest: [],
    product_lines: [],
    rewards: [],
    source_id: null,
    reporter_id: null,
    operator_id: null,
    operational_site_id: null,
    attribute_values: { notes: 'existing note', budget: 1000 },
    ...overrides,
  }
}

describe('buildRequestWorkPayload — attribution (user directive 2026-07-22)', () => {
  it('sends only the attribution id that changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ source_id: 4 }),
      panel({ source_id: null, reporter_id: 9, operator_id: 3 }),
    )

    expect(payload).toEqual({ source_id: 4, reporter_id: null, operator_id: null })
  })

  it('omits every attribution key when none was touched', () => {
    const payload = buildRequestWorkPayload(
      formValues({ source_id: 4, reporter_id: 9, operator_id: 3 }),
      panel({ source_id: 4, reporter_id: 9, operator_id: 3 }),
    )

    expect(payload).toEqual({})
  })

  it('sends an explicit null when an attribution field is cleared', () => {
    const payload = buildRequestWorkPayload(formValues(), panel({ operator_id: 3 }))

    expect(payload).toEqual({ operator_id: null })
  })

  /** Spec 0056: the operational site is a fourth attribution field, diffed the same way. */
  it('sends operational_site_id when changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ operational_site_id: 8 }),
      panel({ operational_site_id: null }),
    )

    expect(payload).toEqual({ operational_site_id: 8 })
  })

  it('sends an explicit null when the operational site is cleared', () => {
    const payload = buildRequestWorkPayload(formValues(), panel({ operational_site_id: 8 }))

    expect(payload).toEqual({ operational_site_id: null })
  })
})

describe('buildRequestWorkPayload — products of interest (user directive 2026-07-22)', () => {
  it('sends the whole id set when the selection changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ products_of_interest: [3, 7] }),
      panel({ products_of_interest: [{ id: 3, name: 'Fibra', product_category: { id: 1, name: 'Connettivita' } }] }),
    )

    expect(payload.products_of_interest).toEqual([3, 7])
  })

  it('sends [] when the operator cleared the collection', () => {
    const payload = buildRequestWorkPayload(
      formValues({ products_of_interest: [] }),
      panel({ products_of_interest: [{ id: 3, name: 'Fibra', product_category: null }] }),
    )

    expect(payload.products_of_interest).toEqual([])
  })

  it('omits the key when the SET is unchanged, whatever the order', () => {
    const payload = buildRequestWorkPayload(
      formValues({ products_of_interest: [7, 3] }),
      panel({
        products_of_interest: [
          { id: 3, name: 'Fibra', product_category: null },
          { id: 7, name: 'Mobile', product_category: null },
        ],
      }),
    )

    expect(payload).not.toHaveProperty('products_of_interest')
  })
})

describe('buildRequestWorkPayload — product lines (user directive 2026-07-31)', () => {
  const LINE = {
    id: 1,
    business_function: { id: 40, name: 'Sales' },
    product_category: { id: 500, name: 'Consulting' },
  }

  it('sends the whole collection when a pair changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ product_lines: [{ business_function_id: 41, product_category_id: 501 }] }),
      panel({ product_lines: [LINE] }),
    )

    expect(payload.product_lines).toEqual([{ business_function_id: 41, product_category_id: 501 }])
  })

  it('omits the key when the SET is unchanged, whatever the order', () => {
    const payload = buildRequestWorkPayload(
      formValues({
        product_lines: [
          { business_function_id: 41, product_category_id: 501 },
          { business_function_id: 40, product_category_id: 500 },
        ],
      }),
      panel({
        product_lines: [
          LINE,
          { id: 2, business_function: { id: 41, name: 'Ops' }, product_category: { id: 501, name: 'Hardware' } },
        ],
      }),
    )

    expect(payload).not.toHaveProperty('product_lines')
  })
})

describe('buildRequestWorkPayload — rewards (spec 0059 D-3, AC-029/031)', () => {
  it('sends the whole reward-type id set when it changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ rewards: [{ reward_type_id: 3 }, { reward_type_id: 7 }] }),
      panel({
        rewards: [
          { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-01-01', notes: null },
        ],
      }),
    )

    expect(payload.rewards).toEqual([{ reward_type_id: 3 }, { reward_type_id: 7 }])
  })

  it('sends [] when the last reward was removed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ rewards: [] }),
      panel({
        rewards: [
          { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-01-01', notes: null },
        ],
      }),
    )

    expect(payload.rewards).toEqual([])
  })

  it('omits the key when the SET is unchanged, whatever the order', () => {
    const payload = buildRequestWorkPayload(
      formValues({ rewards: [{ reward_type_id: 7 }, { reward_type_id: 3 }] }),
      panel({
        rewards: [
          { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-01-01', notes: null },
          { id: 901, reward_type: { id: 7, name: 'Buono spesa', color: 'green' }, assigned_at: '2026-01-02', notes: null },
        ],
      }),
    )

    expect(payload).not.toHaveProperty('rewards')
  })

  /**
   * Data-loss guard: the three states of the sparse contract (spec 0059
   * §4/`sync_semantics`) must never collapse. An untouched selection is a
   * KEY ABSENT from the payload (server: no-op); a fully-cleared selection
   * is `rewards: []` (server: delete every assignment). Sending `[]` for
   * "untouched" would silently wipe every reward on any save that doesn't
   * touch this field.
   */
  it('never collapses "untouched" (key absent) into an explicit clear ([])', () => {
    const loadedRewards = {
      rewards: [
        { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-01-01', notes: null },
      ],
    }

    const untouched = buildRequestWorkPayload(formValues({ rewards: [{ reward_type_id: 3 }] }), panel(loadedRewards))
    expect(untouched).not.toHaveProperty('rewards')

    const explicitlyCleared = buildRequestWorkPayload(formValues({ rewards: [] }), panel(loadedRewards))
    expect(explicitlyCleared).toHaveProperty('rewards', [])
  })
})

describe('buildRequestWorkPayload (spec 0049 AC-062)', () => {
  it('returns an empty payload when nothing changed', () => {
    expect(buildRequestWorkPayload(formValues(), panel())).toEqual({})
  })

  it('sends only the working status when just that changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101 }),
      panel(),
    )
    expect(payload).toEqual({ opportunity_workflow_status_id: 101 })
  })

  it('sends the whole attribute_values map when at least one value changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ attribute_values: { notes: 'updated note', budget: 1000 } }),
      panel(),
    )
    expect(payload).toEqual({ attribute_values: { notes: 'updated note', budget: 1000 } })
  })

  it('sends both keys when the working status and an attribute value both changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101, attribute_values: { notes: 'new', budget: 1000 } }),
      panel(),
    )
    expect(payload).toEqual({
      opportunity_workflow_status_id: 101,
      attribute_values: { notes: 'new', budget: 1000 },
    })
  })

  it('sends the whole client_contacts set when one channel was typed in', () => {
    const payload = buildRequestWorkPayload(
      formValues({
        client_contacts: [
          { _key: 'c1', type: 'email', value: 'ops@acme.test', label: null, is_primary: true },
        ],
      }),
      panel(),
    )
    expect(payload).toEqual({
      client_contacts: [{ type: 'email', value: 'ops@acme.test', label: null, is_primary: true }],
    })
  })

  it('keeps the loaded client contacts out of the payload when untouched', () => {
    const loaded = { id: 7, type: 'email', value: 'ops@acme.test', label: null, is_primary: true }
    const payload = buildRequestWorkPayload(
      formValues({ client_contacts: [{ ...loaded, _key: 'contact-7' }] }),
      panel({ client_contacts: { owner: { type: 'personal_data', id: 10 }, items: [loaded] } }),
    )
    expect(payload).toEqual({})
  })

  it('sends client_address with its id when the loaded address was edited', () => {
    const loaded = {
      id: 3,
      line1: 'Via Vecchia 1',
      line2: null,
      postal_code: '20100',
      city_id: 5,
      province_id: null,
      state_id: null,
      country_id: 1,
    }
    const payload = buildRequestWorkPayload(
      formValues({
        client_address: [
          { ...loaded, _key: 'address-3', line1: 'Via Nuova 2', is_primary: true, site_type: 'billing' },
        ],
      }),
      panel({ client_address: { ...loaded, is_primary: true } as RequestWorkPanel['client_address'] }),
    )
    expect(payload).toEqual({ client_address: { ...loaded, line1: 'Via Nuova 2' } })
  })

  it('omits client_address entirely when the inline fields were left blank', () => {
    expect(buildRequestWorkPayload(formValues({ client_address: [] }), panel())).toEqual({})
  })

  it('treats null and an absent original value as equal (no spurious diff)', () => {
    const payload = buildRequestWorkPayload(
      formValues(),
      panel({ attribute_values: { notes: 'existing note', budget: 1000, extra: undefined } }),
    )
    expect(payload).toEqual({})
  })
})

describe('buildRequestWorkPayload — note on a requires_note status change (spec 0054 D-5)', () => {
  const requiresNotePanel = panel({
    workflow_statuses: [
      { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
      { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: true },
    ],
  })

  it('sends the note alongside the status when the target status requires one', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101, note: 'Client confirmed by phone.' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({ opportunity_workflow_status_id: 101, note: 'Client confirmed by phone.' })
  })

  it('omits the note when the target status does not require one', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 100, note: 'Ignored, status is unchanged anyway.' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({})
  })

  it('omits a blank note even when the target status requires one', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101, note: '   ' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({ opportunity_workflow_status_id: 101 })
  })

  it('never sends the note when the status did not change', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 100, note: 'Stray text left in the field.' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({})
  })
})

/**
 * User directive 2026-07-31: a `boolean` attribute has no "unanswered" state.
 * Before this, an unset flag was seeded `null`, `z.boolean()` refused it and
 * the panel showed PSP / DID / Documenti Identificativi as required.
 */
describe('seedAttributeValues — boolean flags default to false', () => {
  const flagPanel = panel({
    applicable_attributes: [
      ...panel().applicable_attributes,
      {
        id: 3,
        code: 'psp',
        name: 'PSP',
        type: 'boolean',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: false,
        sort_order: 3,
        options: [],
      },
    ],
  })

  it('fills an unset boolean with false and every other unset type with null', () => {
    const seeded = seedAttributeValues(flagPanel.applicable_attributes, {})

    expect(seeded).toEqual({ notes: null, budget: null, psp: false })
  })

  it('keeps a stored false, which `??` on a nullish default would preserve but a falsy one would not', () => {
    const seeded = seedAttributeValues(flagPanel.applicable_attributes, { psp: false })

    expect(seeded.psp).toBe(false)
  })

  it('does not report a request whose flag was never filled as modified', () => {
    const payload = buildRequestWorkPayload(
      formValues({ attribute_values: { notes: 'existing note', budget: 1000, psp: false } }),
      flagPanel,
    )

    expect(payload).toEqual({})
  })

  it('sends the whole map once the flag is ticked', () => {
    const payload = buildRequestWorkPayload(
      formValues({ attribute_values: { notes: 'existing note', budget: 1000, psp: true } }),
      flagPanel,
    )

    expect(payload).toEqual({ attribute_values: { notes: 'existing note', budget: 1000, psp: true } })
  })
})
