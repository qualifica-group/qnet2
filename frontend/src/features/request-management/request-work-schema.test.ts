import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildRequestWorkSchema,
  type RequestWorkOriginalState,
} from '@/features/request-management/request-work-schema'
import type { ApplicableAttribute, RequestWorkflowStatusRef } from '@/features/request-management/types'

/**
 * Spec 0054 D-5: mirrors `RequestManagementService::updateWork()`'s server
 * rule client-side — a note is mandatory only when the working status
 * BOTH changes AND its target is flagged `requires_note`. The server stays
 * authoritative; this is the anticipatory half.
 */

const STATUSES: RequestWorkflowStatusRef[] = [
  { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
  { id: 101, name: 'Closed', color: 'green', system_key: null, description: null, requires_note: true },
]

/**
 * The panel's loaded state: what the schema compares against to decide
 * whether a key is going to be sent at all (the endpoint is sparse).
 */
function original(
  workflowStatusId: number,
  overrides: Partial<RequestWorkOriginalState> = {},
): RequestWorkOriginalState {
  return {
    workflow_status_id: workflowStatusId,
    attribute_values: {},
    products_of_interest: [7],
    client_identity: null,
    client_contacts: [],
    client_address: null,
    ...overrides,
  }
}

function values(overrides: Record<string, unknown> = {}) {
  return {
    opportunity_workflow_status_id: 100,
    next_callback_at: null,
    note: '',
    client_identity: null,
    client_contacts: [],
    client_address: [],
    // Mandatory since the user directive 2026-07-23 (>=1 product).
    products_of_interest: [7],
    rewards: [],
    // Mandatory since the user directive 2026-07-29 (see the dedicated suite below).
    source_id: 30,
    reporter_id: null,
    operator_id: null,
    operational_site_id: null,
    attribute_values: {},
    ...overrides,
  }
}

// User directive 2026-07-23: the panel writes the same collection as the
// opportunities form, so it carries the same mandatory rule.
describe('buildRequestWorkSchema — products of interest', () => {
  it('rejects an empty collection', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)
    const result = schema.safeParse(values({ products_of_interest: [] }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'products_of_interest')).toBe(true)
    }
  })

  it('accepts one or more products', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)

    expect(schema.safeParse(values({ products_of_interest: [7] })).success).toBe(true)
  })

  /**
   * `UpdateRequestRequest` marks the key `sometimes`: an untouched collection
   * is never sent, so it is never validated server-side either. Blocking the
   * submit for it would leave a record that legitimately has none unsavable
   * for every OTHER field — the panel would refuse the save with no request
   * ever going out.
   */
  it('leaves an empty collection alone while it stays untouched', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(100, { products_of_interest: [] }), i18n.t)

    expect(schema.safeParse(values({ products_of_interest: [] })).success).toBe(true)
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
    const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)
    const result = schema.safeParse(values({ source_id: null }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'source_id')).toBe(true)
    }
  })

  it('accepts a chosen source', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)

    expect(schema.safeParse(values({ source_id: 30 })).success).toBe(true)
  })
})

/**
 * Same sparse mirror for the dynamic Attributes: `AttributeValueValidator`
 * checks `is_required` only on SUBMITTED codes, and the panel sends the map
 * only when one of them changed.
 */
describe('buildRequestWorkSchema — required attributes', () => {
  const ATTRIBUTES: ApplicableAttribute[] = [
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
  ]

  it('leaves an empty required attribute alone while the map stays untouched', () => {
    const schema = buildRequestWorkSchema(
      ATTRIBUTES,
      STATUSES,
      original(100, { attribute_values: { notes: null } }),
      i18n.t,
    )

    expect(schema.safeParse(values({ attribute_values: { notes: null } })).success).toBe(true)
  })

  it('rejects an empty required attribute once the map is edited', () => {
    const schema = buildRequestWorkSchema(
      ATTRIBUTES,
      STATUSES,
      original(100, { attribute_values: { notes: 'Some notes' } }),
      i18n.t,
    )
    const result = schema.safeParse(values({ attribute_values: { notes: '' } }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'attribute_values.notes')).toBe(true)
    }
  })
})

describe('buildRequestWorkSchema — note requirement (spec 0054 D-5)', () => {
  it('requires a non-blank note when moving to a status flagged requires_note', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 101, note: '' }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'note')).toBe(true)
    }
  })

  it('accepts a blank note whitespace-only as still missing', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 101, note: '   ' }))

    expect(result.success).toBe(false)
  })

  it('passes once the note is filled in', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)
    const result = schema.safeParse(
      values({ opportunity_workflow_status_id: 101, note: 'Client confirmed by phone.' }),
    )

    expect(result.success).toBe(true)
  })

  it('does not require a note when the target status does not require one', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(101), i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 100, note: '' }))

    expect(result.success).toBe(true)
  })

  it('does not require a note when the status has not changed, even if it requires one', () => {
    const schema = buildRequestWorkSchema([], STATUSES, original(101), i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 101, note: '' }))

    expect(result.success).toBe(true)
  })
})
