import { describe, expect, it } from 'vitest'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataCard, PersonalDataDraft } from '@/features/personal-data/types'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/referents/referent-form-payload'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

/** Spec 0016 AC-022: create payload shape, update payload diffs only changes. */

function individualDraft(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return {
    ...emptyPersonalDataDraft('individual'),
    first_name: 'Ada',
    last_name: 'Lovelace',
    ...overrides,
  }
}

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'individual',
    gender: null,
    first_name: 'Ada',
    last_name: 'Lovelace',
    company_name: null,
    full_name: 'Ada Lovelace',
    ceo: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    residence_city_id: null,
    personable_type: 'referent',
    personable_id: 7,
    contacts: [],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

function original(
  overrides: Partial<ReferentDetailWithPermissions> = {},
): ReferentDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    referent_type_id: 1,
    referent_type: { id: 1, name: 'Sponsor' },
    user_id: null,
    user: null,
    contact_scope: 'internal',
    notes: 'Some notes',
    personal_data: card(),
    created_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

const formValues: ReferentFormValues = {
  referent_type_id: 1,
  user_id: null,
  contact_scope: 'internal',
  notes: 'Some notes',
  custom_fields: {},
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape, always carrying personal_data', () => {
    const payload = buildCreatePayload(formValues, individualDraft())

    expect(payload.referent_type_id).toBe(1)
    expect(payload.contact_scope).toBe('internal')
    expect(payload.notes).toBe('Some notes')
    expect(payload.personal_data.first_name).toBe('Ada')
    expect(payload.personal_data.last_name).toBe('Lovelace')
  })

  it('maps an empty notes string to null', () => {
    const payload = buildCreatePayload({ ...formValues, notes: '' }, individualDraft())
    expect(payload.notes).toBeNull()
  })

  it('carries a null referent_type_id through unchanged', () => {
    const payload = buildCreatePayload(
      { ...formValues, referent_type_id: null },
      individualDraft(),
    )
    expect(payload.referent_type_id).toBeNull()
  })

  it('carries the linked user_id through', () => {
    const payload = buildCreatePayload({ ...formValues, user_id: 42 }, individualDraft())
    expect(payload.user_id).toBe(42)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field, including personal_data, when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original(), individualDraft())
    expect(payload).toEqual({})
  })

  it('includes only the changed contact_scope', () => {
    const payload = buildUpdatePayload(
      { ...formValues, contact_scope: 'external' },
      original(),
      individualDraft(),
    )
    expect(payload).toEqual({ contact_scope: 'external' })
  })

  it('sends referent_type_id: null when the type is cleared', () => {
    const payload = buildUpdatePayload(
      { ...formValues, referent_type_id: null },
      original(),
      individualDraft(),
    )
    expect(payload).toEqual({ referent_type_id: null })
  })

  it('includes personal_data only when the buffered draft actually differs', () => {
    const changedDraft = individualDraft({ first_name: 'Grace' })
    const payload = buildUpdatePayload(formValues, original(), changedDraft)

    expect(payload.personal_data).toBeDefined()
    expect(payload.personal_data?.first_name).toBe('Grace')
    expect(payload.referent_type_id).toBeUndefined()
    expect(payload.contact_scope).toBeUndefined()
  })

  it('combines multiple changed fields in a single payload', () => {
    const payload = buildUpdatePayload(
      { referent_type_id: 2, user_id: null, contact_scope: 'external', notes: '', custom_fields: {} },
      original(),
      individualDraft(),
    )

    expect(payload).toEqual({
      referent_type_id: 2,
      contact_scope: 'external',
      notes: null,
    })
  })

  it('includes user_id when a user is linked (AC-001)', () => {
    const payload = buildUpdatePayload(
      { ...formValues, user_id: 42 },
      original(),
      individualDraft(),
    )
    expect(payload).toEqual({ user_id: 42 })
  })

  it('sends user_id: null when unlinking (AC-003)', () => {
    const payload = buildUpdatePayload(
      formValues,
      original({ user_id: 42, user: { id: 42, name: 'Mario Rossi' } }),
      individualDraft(),
    )
    expect(payload).toEqual({ user_id: null })
  })

  it('does not diff user_id when it is entirely omitted from the original (AC-005, not visible)', () => {
    const withoutUser = original()
    delete (withoutUser as { user_id?: number | null }).user_id
    delete (withoutUser as { user?: unknown }).user
    const payload = buildUpdatePayload(formValues, withoutUser, individualDraft())
    expect(payload).toEqual({})
  })
})
