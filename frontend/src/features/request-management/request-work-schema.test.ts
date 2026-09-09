import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildRequestWorkSchema,
  type RequestWorkOriginalState,
} from '@/features/request-management/request-work-schema'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import type { QuoteWorkflowStatusRef } from '@/features/quotes/types'

/**
 * A buffered client card. Left ABSENT from `original()` on purpose, so
 * `clientIdentityChanged` reads "no card loaded, nothing travels" and the
 * card's own fiscal rules stay dormant: these suites are about the two
 * transition gates, not about the anagraphic schema.
 */
function card(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return {
    type: 'individual',
    first_name: 'Mario',
    last_name: 'Rossi',
    company_name: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    residence_city_id: null,
    gender: 'male',
    contacts: [],
    addresses: [],
    ...overrides,
  }
}

/**
 * The panel's loaded state: what the schema compares against to decide
 * whether a key is going to be sent at all (the endpoint is sparse).
 */
function original(overrides: Partial<RequestWorkOriginalState> = {}): RequestWorkOriginalState {
  return {
    product_lines: [{ business_function_id: 40, product_category_id: 500 }],
    attribute_values: {},
    quote_workflow_status_id: null,
    client_identity: null,
    client_contacts: [],
    client_address: null,
    ...overrides,
  }
}

function values(overrides: Record<string, unknown> = {}) {
  return {
    next_callback_at: null,
    general_notes: '',
    client_identity: null,
    client_contacts: [],
    client_address: [],
    // Editable since the user directive 2026-07-31; unchanged here, so the
    // collection's own rules stay dormant (see the dedicated suite below).
    product_lines: [{ business_function_id: 40, product_category_id: 500 }],
    // Editable since the user directive 2026-08-07; empty here, which the
    // schema accepts (an offer may legitimately carry no row).
    offer_lines: [],
    rewards: [],
    // Mandatory since the user directive 2026-07-29 (see the dedicated suite below).
    source_id: 30,
    reporter_id: null,
    supervisor_id: null,
    // Spec 0097 D-1: the team replaces the single "Operatore" field.
    manager_slots: [null, null, null, null],
    operational_site_id: null,
    attribute_values: {},
    quote_workflow_status_id: null,
    note: null,
    ...overrides,
  }
}

// User directive 2026-07-31: funzione aziendale + categoria prodotto are
// edited from the panel too, under the create form's own two rules — but only
// once the collection is actually touched (same sparse gate as above).
describe('buildRequestWorkSchema — product lines', () => {
  const EDITED = [{ business_function_id: 41, product_category_id: 501 }]

  it('rejects clearing the collection', () => {
    const schema = buildRequestWorkSchema(original(), [], [], i18n.t)
    const result = schema.safeParse(values({ product_lines: [] }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
    }
  })

  it('rejects a row missing its product category', () => {
    const schema = buildRequestWorkSchema(original(), [], [], i18n.t)
    const result = schema.safeParse(
      values({ product_lines: [{ business_function_id: 41, product_category_id: null }] }),
    )

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines.0')).toBe(true)
    }
  })

  it('accepts a complete replacement', () => {
    const schema = buildRequestWorkSchema(original(), [], [], i18n.t)

    expect(schema.safeParse(values({ product_lines: EDITED })).success).toBe(true)
  })

  it('leaves an untouched collection alone, even when the request has no line', () => {
    const schema = buildRequestWorkSchema(original({ product_lines: [] }), [], [], i18n.t)

    expect(schema.safeParse(values({ product_lines: [] })).success).toBe(true)
  })

  /**
   * Spec 0077 INV-2: every row shares the same Funzione aziendale, in both
   * management modes — gated by the SAME sparse rule as the two suites
   * above (AC-043: identical behaviour to `request-create-schema.ts` and
   * `opportunity-schema.ts`).
   */
  describe('shared business function (spec 0077 INV-2)', () => {
    it('rejects mismatched functions once the collection is edited', () => {
      const schema = buildRequestWorkSchema(original(), [], [], i18n.t)
      const result = schema.safeParse(
        values({
          product_lines: [
            { business_function_id: 41, product_category_id: 501 },
            { business_function_id: 42, product_category_id: 502 },
          ],
        }),
      )

      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
      }
    })

    it('accepts several edited rows sharing the same business function', () => {
      const schema = buildRequestWorkSchema(original(), [], [], i18n.t)
      const result = schema.safeParse(
        values({
          product_lines: [
            { business_function_id: 41, product_category_id: 501 },
            { business_function_id: 41, product_category_id: 502 },
          ],
        }),
      )

      expect(result.success).toBe(true)
    })

    /** AC-044: a historic record whose rows never conformed stays saveable while `product_lines` is left untouched. */
    it('leaves a non-conformant historic collection alone while it stays untouched (D-5, AC-044)', () => {
      const historicRows = [
        { business_function_id: 41, product_category_id: 501 },
        { business_function_id: 42, product_category_id: 502 },
      ]
      const schema = buildRequestWorkSchema(original({ product_lines: historicRows }), [], [], i18n.t)

      // The panel resubmits the SAME historic rows unchanged, only `next_callback_at` differs.
      const result = schema.safeParse(
        values({ product_lines: historicRows, next_callback_at: '2026-08-10T09:00:00Z' }),
      )

      expect(result.success).toBe(true)
    })
  })
})

/**
 * User directive 2026-07-29: the Fonte is mandatory, and DELIBERATELY not
 * gated on the key travelling the way the two rules above are — a request
 * without a source may not be saved at all, legacy rows included. The blocking
 * field is named in the panel's own save summary (`describeInvalidFields`), so
 * this refusal is visible rather than a silent dead button.
 */
describe('buildRequestWorkSchema — source (Fonte)', () => {
  it('rejects a null source', () => {
    const schema = buildRequestWorkSchema(original(), [], [], i18n.t)
    const result = schema.safeParse(values({ source_id: null }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'source_id')).toBe(true)
    }
  })

  it('accepts a chosen source', () => {
    const schema = buildRequestWorkSchema(original(), [], [], i18n.t)

    expect(schema.safeParse(values({ source_id: 30 })).success).toBe(true)
  })
})

/**
 * Spec 0102 AC-044/045: mirrors the server gate in
 * `QuoteWorkflowStatusWriter::apply()` — a transition into a status whose
 * `group` is `closed_won`/`closed_lost`/`validated` demands at least one
 * REVENUE row; `open`/`pending` do not, and neither does reissuing the
 * status the request already holds (spec 0083 AC-026/AC-018).
 */
describe('buildRequestWorkSchema — offer lines required for status', () => {
  const OPEN: QuoteWorkflowStatusRef = {
    id: 1,
    name: 'Aperta',
    color: 'slate',
    description: null,
    group: 'open',
    requires_note: false,
  }
  const PENDING: QuoteWorkflowStatusRef = {
    id: 2,
    name: 'In corso',
    color: 'amber',
    description: null,
    group: 'pending',
    requires_note: false,
  }
  const CLOSED_WON: QuoteWorkflowStatusRef = {
    id: 3,
    name: 'Vinta',
    color: 'green',
    description: null,
    group: 'closed_won',
    requires_note: false,
  }
  const CLOSED_LOST: QuoteWorkflowStatusRef = {
    id: 4,
    name: 'Persa',
    color: 'red',
    description: null,
    group: 'closed_lost',
    requires_note: false,
  }
  const VALIDATED: QuoteWorkflowStatusRef = {
    id: 5,
    name: 'Validata',
    color: 'blue',
    description: null,
    group: 'validated',
    requires_note: false,
  }
  const STATUSES = [OPEN, PENDING, CLOSED_WON, CLOSED_LOST, VALIDATED]
  const COMPLETE_LINE = { product_id: 10, quantity: 1, unit_price: 100, vat_rate_id: 1 }

  it('rejects a transition to closed_won with no offer lines (AC-044)', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(values({ quote_workflow_status_id: CLOSED_WON.id }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines')).toBe(true)
    }
  })

  it('rejects a transition to closed_lost with no offer lines (AC-044)', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(values({ quote_workflow_status_id: CLOSED_LOST.id }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines')).toBe(true)
    }
  })

  it('rejects a transition to validated with no offer lines (AC-044)', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(values({ quote_workflow_status_id: VALIDATED.id }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines')).toBe(true)
    }
  })

  it('ignores a lone pristine row: it does not count as an offer line', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(
      values({
        quote_workflow_status_id: CLOSED_WON.id,
        offer_lines: [{ product_id: null, quantity: null, unit_price: null, vat_rate_id: null }],
      }),
    )

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines')).toBe(true)
    }
  })

  // The card travels along since the direttiva utente 2026-09-09: a positive
  // close also demands a fiscal identity (see the suite below), so without one
  // this transition would now be refused for the OTHER reason.
  it('accepts a transition to closed_won once a complete offer line is present', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(
      values({
        quote_workflow_status_id: CLOSED_WON.id,
        offer_lines: [COMPLETE_LINE],
        client_identity: card({ tax_code: 'RSSMRA80A01H501U' }),
      }),
    )

    expect(result.success).toBe(true)
  })

  it('accepts a transition to open with no offer lines (AC-045)', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)

    expect(schema.safeParse(values({ quote_workflow_status_id: OPEN.id })).success).toBe(true)
  })

  it('accepts a transition to pending with no offer lines (AC-045)', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)

    expect(schema.safeParse(values({ quote_workflow_status_id: PENDING.id })).success).toBe(true)
  })

  // The card rides along for the same reason as the test above: since the
  // direttiva utente 2026-09-09 a request SITTING in closed_won also needs a
  // fiscal identity to be saved at all (the suite below), which is not what
  // this case is about.
  it('does not gate a reissue of the status the request already holds', () => {
    const schema = buildRequestWorkSchema(original({ quote_workflow_status_id: CLOSED_WON.id }), [], STATUSES, i18n.t)
    const result = schema.safeParse(
      values({
        quote_workflow_status_id: CLOSED_WON.id,
        client_identity: card({ tax_code: 'RSSMRA80A01H501U' }),
      }),
    )

    expect(result.success).toBe(true)
  })
})

/**
 * Direttiva utente 2026-09-09: mirror of the server gate in
 * `RequestWorkflowStatusWriter::assertClientFiscalIdentity()` — a transition
 * into `closed_won` demands the client's codice fiscale OR partita IVA,
 * either one of the two. Every other destination is untouched, and reissuing
 * the status the request already holds is not a transition.
 */
describe('buildRequestWorkSchema — fiscal identity required for a positive close', () => {
  const CLOSED_WON: QuoteWorkflowStatusRef = {
    id: 3,
    name: 'Vinta',
    color: 'green',
    description: null,
    group: 'closed_won',
    requires_note: false,
  }
  const CLOSED_LOST: QuoteWorkflowStatusRef = {
    id: 4,
    name: 'Persa',
    color: 'red',
    description: null,
    group: 'closed_lost',
    requires_note: false,
  }
  const STATUSES = [CLOSED_WON, CLOSED_LOST]
  const COMPLETE_LINE = { product_id: 10, quantity: 1, unit_price: 100, vat_rate_id: 1 }

  function closing(overrides: Record<string, unknown> = {}) {
    return values({
      quote_workflow_status_id: CLOSED_WON.id,
      offer_lines: [COMPLETE_LINE],
      ...overrides,
    })
  }

  it('rejects a positive close on a card carrying neither identifier', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(closing({ client_identity: card() }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'client_identity')).toBe(true)
    }
  })

  it('rejects a positive close on a request whose client has no card at all', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(closing({ client_identity: null }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'client_identity')).toBe(true)
    }
  })

  it('accepts a positive close on the tax code alone', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)

    expect(schema.safeParse(closing({ client_identity: card({ tax_code: 'RSSMRA80A01H501U' }) })).success).toBe(true)
  })

  it('accepts a positive close on the VAT number alone', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)

    expect(schema.safeParse(closing({ client_identity: card({ vat_number: '01234567897' }) })).success).toBe(true)
  })

  it('treats a blank identifier as missing', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(closing({ client_identity: card({ tax_code: '   ' }) }))

    expect(result.success).toBe(false)
  })

  it('leaves a NEGATIVE close alone: only the positive outcome is gated', () => {
    const schema = buildRequestWorkSchema(original(), [], STATUSES, i18n.t)
    const result = schema.safeParse(
      closing({ quote_workflow_status_id: CLOSED_LOST.id, client_identity: card() }),
    )

    expect(result.success).toBe(true)
  })

  // Decisione utente 2026-09-09: INVARIANTE, non gate di transizione — una
  // richiesta gia' chiusa positiva (chiusa prima che la regola esistesse) non
  // si salva dal pannello finche' il dato fiscale manca, anche se questo
  // salvataggio non tocca lo stato.
  it('blocks a save on a request ALREADY closed positive with no fiscal identity', () => {
    const schema = buildRequestWorkSchema(
      original({ quote_workflow_status_id: CLOSED_WON.id }),
      [],
      STATUSES,
      i18n.t,
    )
    const result = schema.safeParse(closing({ client_identity: card() }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'client_identity')).toBe(true)
    }
  })

  it('lets an already closed positive request be saved once the identifier is there', () => {
    const schema = buildRequestWorkSchema(
      original({ quote_workflow_status_id: CLOSED_WON.id }),
      [],
      STATUSES,
      i18n.t,
    )

    expect(schema.safeParse(closing({ client_identity: card({ vat_number: '01234567897' }) })).success).toBe(true)
  })
})
