import { describe, expect, it } from 'vitest'
import { buildRequestWorkPayload } from '@/features/request-management/request-work-payload'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestWorkPanel } from '@/features/request-management/types'

/** Spec 0049 AC-062: the PATCH payload is sparse — only what actually changed. */

function panel(overrides: Partial<RequestWorkPanel> = {}): RequestWorkPanel {
  return {
    id: 1,
    opportunity_id: 1,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    source_id: null,
    source: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    is_transferred: false,
    transferred_from: null,
    status: { source: 'default', distinct_count: 0, entries: [] },
    product_lines: [],
    offer_lines: [],
    client_identity: null,
    client_contacts: { owner: { type: 'personal_data', id: 10 }, items: [] },
    client_address: null,
    referent_contacts: { owner: { type: 'personal_data', id: 20 }, items: [] },
    next_callback_at: null,
    attribute_values: {},
    applicable_attributes: [],
    attribute_layout: null,
    quote_workflow_status_id: null,
    quote_workflow_status: null,
    quote_workflow_statuses: [],
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    ...overrides,
  }
}

function formValues(overrides: Partial<RequestWorkFormValues> = {}): RequestWorkFormValues {
  return {
    next_callback_at: null,
    client_identity: null,
    client_contacts: [],
    client_address: [],
    product_lines: [],
    offer_lines: [],
    rewards: [],
    source_id: null,
    reporter_id: null,
    supervisor_id: null,
    manager_slots: [],
    operational_site_id: null,
    attribute_values: {},
    quote_workflow_status_id: null,
    note: null,
    ...overrides,
  }
}

describe('buildRequestWorkPayload — attribution (user directive 2026-07-22)', () => {
  it('sends only the attribution id that changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ source_id: 4 }),
      panel({ source_id: null, reporter_id: 9 }),
    )

    expect(payload).toEqual({ source_id: 4, reporter_id: null })
  })

  it('omits every attribution key when none was touched', () => {
    const payload = buildRequestWorkPayload(
      formValues({ source_id: 4, reporter_id: 9 }),
      panel({ source_id: 4, reporter_id: 9 }),
    )

    expect(payload).toEqual({})
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

  /**
   * Spec 0097 rev-2 D-9/AC-013: the Supervisore is a fifth attribution field,
   * diffed the same way — and AC-014, it is INDEPENDENT of the team: writing
   * it puts no `manager_slots` on the wire.
   */
  it('sends supervisor_id when changed, alone', () => {
    const payload = buildRequestWorkPayload(
      formValues({ supervisor_id: 33, manager_slots: [null, 5, null, null] }),
      panel({ supervisor_id: null, managers: [{ id: 5, name: 'Ada Lovelace', position: 2 }] }),
    )

    expect(payload).toEqual({ supervisor_id: 33 })
  })

  it('sends an explicit null when the supervisor is cleared', () => {
    const payload = buildRequestWorkPayload(formValues(), panel({ supervisor_id: 33 }))

    expect(payload).toEqual({ supervisor_id: null })
  })

  /** AC-014, the other way round: a team edit never drags the supervisor along. */
  it('leaves supervisor_id off the wire when only the team changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ supervisor_id: 33, manager_slots: [null, 9, null, null] }),
      panel({ supervisor_id: 33, managers: [{ id: 5, name: 'Ada Lovelace', position: 2 }] }),
    )

    expect(payload).toEqual({ manager_slots: [null, 9, null, null] })
  })
})

/**
 * Spec 0097 D-1: the team replaces the single `operator_id` key of this
 * payload. One authoritative array, diffed POSITIONALLY against the loaded
 * pivot — a gap is information, not noise.
 */
describe('buildRequestWorkPayload — team slots (spec 0097)', () => {
  const ADA = { id: 5, name: 'Ada Lovelace', position: 2 }

  it('sends the whole array when a slot changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ manager_slots: [7, 5, null, null] }),
      panel({ managers: [ADA] }),
    )

    expect(payload).toEqual({ manager_slots: [7, 5, null, null] })
  })

  it('omits the key when the padded form value matches the loaded pivot', () => {
    const payload = buildRequestWorkPayload(
      formValues({ manager_slots: [null, 5, null, null] }),
      panel({ managers: [ADA] }),
    )

    expect(payload).not.toHaveProperty('manager_slots')
  })

  it('sends the emptied array when the operator slot is cleared', () => {
    const payload = buildRequestWorkPayload(
      formValues({ manager_slots: [null, null, null, null] }),
      panel({ managers: [ADA] }),
    )

    expect(payload).toEqual({ manager_slots: [null, null, null, null] })
  })

  /**
   * The gap-sensitivity guard: the same two users at DIFFERENT positions are a
   * different team. A set comparison would call this untouched and drop a real
   * reassignment on the floor.
   */
  it('sends the array when the same users only moved position', () => {
    const payload = buildRequestWorkPayload(
      formValues({ manager_slots: [5, 7] }),
      panel({
        managers: [
          { id: 7, name: 'Grace Hopper', position: 1 },
          { id: 5, name: 'Ada Lovelace', position: 2 },
        ],
      }),
    )

    expect(payload).toEqual({ manager_slots: [5, 7] })
  })

  /**
   * The other half of the same data-loss guard: a team reaching past the
   * padded card count is left alone by an unrelated save. The key travels only
   * on a real positional difference, and the loaded G.A. 6 is not one.
   */
  it('omits the key for a team reaching beyond the padded card count', () => {
    const payload = buildRequestWorkPayload(
      formValues({ manager_slots: [null, 5, null, null, null, 9] }),
      panel({
        managers: [ADA, { id: 9, name: 'Grace Hopper', position: 6 }],
      }),
    )

    expect(payload).not.toHaveProperty('manager_slots')
  })

  it('never sends operator_id: the panel writes the team only', () => {
    const payload = buildRequestWorkPayload(
      formValues({ manager_slots: [null, 9, null, null] }),
      panel({ operator_id: 5, operator: { id: 5, name: 'Ada Lovelace' }, managers: [ADA] }),
    )

    expect(payload).not.toHaveProperty('operator_id')
    expect(payload).toEqual({ manager_slots: [null, 9, null, null] })
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

  /**
   * "Linee dell'offerta" (user directive 2026-08-07): full-replace when sent,
   * so an untouched collection must stay OUT of the payload — including when
   * the persisted rows carry provvigioni, which this channel never sees.
   */
  describe('offer lines', () => {
    const persisted: RequestWorkPanel['offer_lines'] = [{
      id: 700,
      product_id: 900,
      product: { id: 900, code: 'FIB', name: 'Fibra', category: null, product_typology: null, business_function: null },
      quantity: '2.00',
      unit_of_measure: null,
      unit_price: '100.00',
      vat_rate_id: 4,
      vat_rate: { id: 4, name: '22%', rate: '22.00' },
      net_amount: '200.00',
      vat_amount: '44.00',
      total_amount: '244.00',
      sort_order: 0,
      commissions: [{
        id: 1,
        recipient_role: 'COMMERCIAL',
        recipient_type: 'referent',
        recipient_id: 5,
        recipient: { id: 5, name: 'Mario' },
        commission_type: 'PERCENTAGE',
        value: '10.00',
        calculated_amount: '20.00',
        internal_note: null,
        origin: 'MANUAL_OVERRIDE',
        commission_configuration_id: null,
      }],
    }]
    const asFormRow = { id: 700, product_id: 900, quantity: 2, unit_price: 100, vat_rate_id: 4 }

    it('omits the collection when the rows were not touched, provvigioni included', () => {
      expect(
        buildRequestWorkPayload(formValues({ offer_lines: [asFormRow] }), panel({ offer_lines: persisted })),
      ).toEqual({})
    })

    it('sends the whole collection, without commissions, once a row changed', () => {
      const payload = buildRequestWorkPayload(
        formValues({ offer_lines: [{ ...asFormRow, quantity: 3 }] }),
        panel({ offer_lines: persisted }),
      )

      expect(payload).toEqual({
        offer_lines: [{ id: 700, product_id: 900, quantity: 3, unit_price: 100, vat_rate_id: 4, sort_order: 0 }],
      })
    })

    it('sends an empty collection when the last row was removed', () => {
      const payload = buildRequestWorkPayload(
        formValues({ offer_lines: [] }),
        panel({ offer_lines: persisted }),
      )

      expect(payload).toEqual({ offer_lines: [] })
    })
  })
})
