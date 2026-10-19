import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/business-functions/business-function-form-payload'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'
import type { BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'

/** Spec 0010 AC-019: create payload shape, update payload diffs only changes. */

const formValues: BusinessFunctionFormValues = {
  name: 'Engineering',
  type: 'business_unit',
  manager_id: 1,
  users: [2, 3],
  parent_id: 100,
  is_active: true,
  operational_sites: [200, 201],
  company_ids: [400, 401],
  custom_fields: {},
}

function original(
  overrides: Partial<BusinessFunctionDetailWithPermissions> = {},
): BusinessFunctionDetailWithPermissions {
  return {
    id: 7,
    name: 'Engineering',
    is_business_unit: true,
    is_business_service: false,
    type: 'business_unit',
    manager_id: 1,
    manager: { id: 1, name: 'Ada Lovelace', avatar_url: null },
    user_ids: [2, 3],
    users: [
      { id: 2, name: 'Grace Hopper', avatar_url: null },
      { id: 3, name: 'Alan Turing', avatar_url: null },
    ],
    parent_id: 100,
    parent: { id: 100, name: 'Operations' },
    is_active: true,
    inactive_ancestor: null,
    operational_site_ids: [200, 201],
    operational_sites: [
      { id: 200, label: 'Via Roma 1 - Milano' },
      { id: 201, label: 'Via Torino 2 - Torino' },
    ],
    company_ids: [400, 401],
    companies: [
      { id: 400, label: 'Acme' },
      { id: 401, label: 'Beta' },
    ],
    created_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape', () => {
    const payload = buildCreatePayload(formValues)

    expect(payload).toEqual({
      name: 'Engineering',
      type: 'business_unit',
      manager_id: 1,
      users: [2, 3],
      is_active: true,
      parent_id: 100,
      operational_sites: [200, 201],
      company_ids: [400, 401],
    })
  })

  it('carries a null type, manager_id and parent_id through unchanged', () => {
    const payload = buildCreatePayload({
      ...formValues,
      type: null,
      manager_id: null,
      users: [],
      parent_id: null,
      operational_sites: [],
      company_ids: [],
    })

    expect(payload.type).toBeNull()
    expect(payload.manager_id).toBeNull()
    expect(payload.users).toEqual([])
    expect(payload.parent_id).toBeNull()
    expect(payload.operational_sites).toEqual([])
    expect(payload.company_ids).toEqual([])
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original())

    expect(payload).toEqual({})
  })

  it('sends is_active only when the flag changed (spec 0280)', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: true }, original())).toEqual({})
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({ is_active: false })
  })

  it('includes only the changed name', () => {
    const payload = buildUpdatePayload({ ...formValues, name: 'Platform' }, original())

    expect(payload).toEqual({ name: 'Platform' })
  })

  it('includes users only when the id set actually differs (order-insensitive)', () => {
    const reordered = buildUpdatePayload({ ...formValues, users: [3, 2] }, original())
    expect(reordered).toEqual({})

    const changed = buildUpdatePayload({ ...formValues, users: [2, 4] }, original())
    expect(changed).toEqual({ users: [2, 4] })
  })

  it('sends manager_id: null when the responsabile is cleared', () => {
    const payload = buildUpdatePayload({ ...formValues, manager_id: null }, original())

    expect(payload).toEqual({ manager_id: null })
  })

  it('remaps type when the selection changes, including to/from null', () => {
    const toService = buildUpdatePayload({ ...formValues, type: 'business_service' }, original())
    expect(toService).toEqual({ type: 'business_service' })

    const toNone = buildUpdatePayload({ ...formValues, type: null }, original())
    expect(toNone).toEqual({ type: null })
  })

  it('remaps parent_id when the selection changes, including to/from null', () => {
    const toOther = buildUpdatePayload({ ...formValues, parent_id: 300 }, original())
    expect(toOther).toEqual({ parent_id: 300 })

    const toRoot = buildUpdatePayload({ ...formValues, parent_id: null }, original())
    expect(toRoot).toEqual({ parent_id: null })
  })

  it('includes operational_sites only when the id set actually differs (order-insensitive)', () => {
    const reordered = buildUpdatePayload({ ...formValues, operational_sites: [201, 200] }, original())
    expect(reordered).toEqual({})

    const changed = buildUpdatePayload({ ...formValues, operational_sites: [200, 202] }, original())
    expect(changed).toEqual({ operational_sites: [200, 202] })
  })

  it('combines multiple changed fields in a single payload', () => {
    const payload = buildUpdatePayload(
      {
        name: 'Platform',
        type: 'business_service',
        manager_id: null,
        users: [9],
        parent_id: null,
        is_active: true,
        operational_sites: [9],
        company_ids: [9],
        custom_fields: {},
      },
      original(),
    )

    expect(payload).toEqual({
      name: 'Platform',
      type: 'business_service',
      manager_id: null,
      users: [9],
      parent_id: null,
      operational_sites: [9],
      company_ids: [9],
    })
  })

  it('sends company_ids only when the id set differs (order-insensitive)', () => {
    expect(buildUpdatePayload({ ...formValues, company_ids: [401, 400] }, original())).toEqual({})
    expect(buildUpdatePayload({ ...formValues, company_ids: [] }, original())).toEqual({ company_ids: [] })
  })
})
