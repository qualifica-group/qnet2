import { describe, expect, it } from 'vitest'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import { buildCreatePayload, buildUpdatePayload } from '@/features/users/user-form-payload'
import type { UserDetailWithPermissions } from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/** Spec 0008 AC-012: the payload builder omits non-editable fields/sections. */

/** Builds a resolver returning the permissive default, overridden per key. */
function resolverFrom(
  overrides: Record<string, Partial<PersonalDataFieldPermission>>,
): PersonalDataFieldPermissionResolver {
  return (key) => ({
    visible: true,
    editable: true,
    required: false,
    disabled: false,
    readonly: false,
    ...overrides[key],
  })
}

function draft(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return {
    ...emptyPersonalDataDraft(),
    first_name: 'Ada',
    last_name: 'Lovelace',
    contacts: [
      { _key: 'c1', id: 1, type: 'email', value: 'ada@example.com', label: null, is_primary: true },
    ],
    addresses: [
      {
        _key: 'a1',
        id: 1,
        line1: '221B Baker Street',
        line2: null,
        postal_code: null,
        city_id: null,
        province_id: null,
        state_id: null,
        country_id: null,
        site_type: null,
        is_primary: true,
      },
    ],
    ...overrides,
  }
}

/** Fixture password satisfying the schema's minimum length; not a real credential. */
const TEST_PASSWORD = 'x'.repeat(12)

/** Blank employment sub-form, mirroring `EMPTY_EMPLOYMENT` in `user-form-defaults.ts`. */
const emptyEmployment: UserFormValues['employment'] = {
  job_role_ids: [],
  business_function_ids: [],
  managed_business_function_ids: [],
  reports_to_ids: [],
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  is_assignable: true,
  covers_all_product_categories: false,
  product_lines: [],
  hired_at: '',
  terminated_at: '',
  standard_daily_minutes: null,
  break_daily_minutes: null,
  iban: '',
  iban_holder: '',
}

const formValues: UserFormValues = {
  email: 'ada@example.com',
  locale: 'en',
  is_active: true,
  roles: [],
  password: TEST_PASSWORD,
  password_confirmation: TEST_PASSWORD,
  employment: emptyEmployment,
  custom_fields: {},
}

function original(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    must_set_password: false,
    managed_business_functions: [],
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload — personal-data gating (spec 0008)', () => {
  it('includes every field/section when no resolver is supplied (AC-013 parity)', () => {
    const payload = buildCreatePayload(formValues, draft())

    expect(payload.personal_data.first_name).toBe('Ada')
    expect(payload.personal_data.last_name).toBe('Lovelace')
    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.addresses).toHaveLength(1)
  })

  it('omits a scalar field the resolver marks non-editable', () => {
    const payload = buildCreatePayload(
      formValues,
      draft(),
      resolverFrom({ 'personal_data.first_name': { editable: false } }),
    )

    expect('first_name' in payload.personal_data).toBe(false)
    // Editable fields are unaffected.
    expect(payload.personal_data.last_name).toBe('Lovelace')
  })

  it('omits the whole contacts/addresses key when the resolver marks the section non-editable', () => {
    const payload = buildCreatePayload(
      formValues,
      draft(),
      resolverFrom({
        'personal_data.contacts': { editable: false },
        'personal_data.addresses': { editable: false },
      }),
    )

    expect('contacts' in payload.personal_data).toBe(false)
    expect('addresses' in payload.personal_data).toBe(false)
  })

  it('keeps editable fields/sections present', () => {
    const payload = buildCreatePayload(
      formValues,
      draft(),
      resolverFrom({ 'personal_data.tax_code': { editable: false } }),
    )

    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.addresses).toHaveLength(1)
    expect(payload.personal_data.first_name).toBe('Ada')
    expect('tax_code' in payload.personal_data).toBe(false)
  })
})

describe('is_active in the payloads', () => {
  it('create: always carries is_active', () => {
    expect(buildCreatePayload({ ...formValues, is_active: false }, draft()).is_active).toBe(false)
    expect(buildCreatePayload({ ...formValues, is_active: true }, draft()).is_active).toBe(true)
  })

  it('update: sends is_active only when it changed from the original', () => {
    const base = { ...formValues, password: '', password_confirmation: '' }

    const unchanged = buildUpdatePayload(base, original({ is_active: true }), draft())
    expect('is_active' in unchanged).toBe(false)

    const toggled = buildUpdatePayload(
      { ...base, is_active: false },
      original({ is_active: true }),
      draft(),
    )
    expect(toggled.is_active).toBe(false)
  })
})

describe('buildUpdatePayload — personal-data gating (spec 0008)', () => {
  it('omits a non-editable scalar field from the PATCH personal_data tree', () => {
    const payload = buildUpdatePayload(
      { ...formValues, password: '', password_confirmation: '' },
      original(),
      draft(),
      resolverFrom({ 'personal_data.last_name': { editable: false } }),
    )

    expect(payload.personal_data).toBeDefined()
    expect('last_name' in (payload.personal_data ?? {})).toBe(false)
    expect(payload.personal_data?.first_name).toBe('Ada')
  })
})

/** Spec 0015 AC-015/AC-018: the nested `employment` object, snake_case, upserted. */
describe('buildCreatePayload — employment (spec 0015)', () => {
  it('includes the employment object with snake_case contract keys', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: {
          ...emptyEmployment,
          primary_operational_site_id: 8,
          remote_operational_site_ids: [11, 12],
          hired_at: '2026-01-15',
          terminated_at: '',
          standard_daily_minutes: 480,
          break_daily_minutes: 30,
          job_role_ids: [4, 7],
        },
      },
      draft(),
    )

    expect(payload.employment).toEqual({
      job_role_ids: [4, 7],
      business_function_ids: [],
      managed_business_function_ids: [],
      reports_to_ids: [],
      primary_operational_site_id: 8,
      remote_operational_site_ids: [11, 12],
      is_assignable: true,
      covers_all_product_categories: false,
      product_lines: [],
      hired_at: '2026-01-15',
      terminated_at: null,
      standard_daily_minutes: 480,
      break_daily_minutes: 30,
    })
  })

  /** Spec 0103 AC-028: the empty-remote-sites case serializes as `[]`, never omitted. */
  it('AC-028 — serializes an empty remote-sites selection as [], not omitted', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: { ...emptyEmployment, primary_operational_site_id: 8 },
      },
      draft(),
    )

    expect(payload.employment.primary_operational_site_id).toBe(8)
    expect('remote_operational_site_ids' in payload.employment).toBe(true)
    expect(payload.employment.remote_operational_site_ids).toEqual([])
  })

  /** Spec 0111 AC-023: the competence PAIRS ride on the same employment object. */
  it('AC-023 — carries the competence rows as function/category pairs', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: {
          ...emptyEmployment,
          product_lines: [
            { business_function_id: 4, product_category_id: 21 },
            { business_function_id: 4, product_category_id: 22 },
          ],
        },
      },
      draft(),
    )

    expect(payload.employment.product_lines).toEqual([
      { business_function_id: 4, product_category_id: 21 },
      { business_function_id: 4, product_category_id: 22 },
    ])
  })

  /** Defense in depth behind the schema: a row missing one id is not a pair. */
  it('drops a row still missing one of its two ids', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: {
          ...emptyEmployment,
          product_lines: [
            { business_function_id: 4, product_category_id: 21 },
            { business_function_id: 4, product_category_id: null },
            { business_function_id: null, product_category_id: 22 },
          ],
        },
      },
      draft(),
    )

    expect(payload.employment.product_lines).toEqual([
      { business_function_id: 4, product_category_id: 21 },
    ])
  })

  /** Spec 0111 D-8: an empty set is a legitimate VALUE — it just covers nothing (rev.2 D-9). */
  it('D-8 — serializes an empty competence set as [], never omitted', () => {
    const payload = buildCreatePayload({ ...formValues, employment: emptyEmployment }, draft())

    expect('product_lines' in payload.employment).toBe(true)
    expect(payload.employment.product_lines).toEqual([])
  })

  /** Spec 0129 AC-020: the flag always rides with an empty row set, whatever the form's own row array holds. */
  it('AC-020 — carries covers_all_product_categories and forces product_lines to [] when it is true', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: {
          ...emptyEmployment,
          covers_all_product_categories: true,
          product_lines: [{ business_function_id: 4, product_category_id: 21 }],
        },
      },
      draft(),
    )

    expect(payload.employment.covers_all_product_categories).toBe(true)
    expect(payload.employment.product_lines).toEqual([])
  })

  it('spec 0194 — always sends is_assignable, true or false', () => {
    const on = buildCreatePayload({ ...formValues, employment: { ...emptyEmployment, is_assignable: true } }, draft())
    const off = buildCreatePayload({ ...formValues, employment: { ...emptyEmployment, is_assignable: false } }, draft())

    expect(on.employment.is_assignable).toBe(true)
    expect(off.employment.is_assignable).toBe(false)
  })

  /** Spec 0129 AC-021: a row with "all categories" checked serializes its category as null. */
  it('AC-021 — serializes an "all categories" row with a null category', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: {
          ...emptyEmployment,
          product_lines: [{ business_function_id: 4, product_category_id: null, all_categories: true }],
        },
      },
      draft(),
    )

    expect(payload.employment.covers_all_product_categories).toBe(false)
    expect(payload.employment.product_lines).toEqual([{ business_function_id: 4, product_category_id: null }])
  })

  // REQUIREMENT CHANGED (spec 0276 D-2): the manager flag is gone, so reports_to_ids is never force-emptied.
  it('keeps reports_to_ids and sends the managed function ids (spec 0276 D-5)', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: { ...emptyEmployment, reports_to_ids: [42, 43], managed_business_function_ids: [5, 6] },
      },
      draft(),
    )

    expect(payload.employment.reports_to_ids).toEqual([42, 43])
    expect(payload.employment.managed_business_function_ids).toEqual([5, 6])
    expect(payload.employment).not.toHaveProperty('is_manager')
  })
})

describe('buildUpdatePayload — employment (spec 0015)', () => {
  // REQUIREMENT CHANGED (spec 0222 D-3): the in-place detail saves one row at
  // a time, so the profile travels only when one of its fields changed — and
  // then whole, since the server upserts the object.
  it('omits the employment object when none of its fields changed', () => {
    const payload = buildUpdatePayload({ ...formValues, password: '', password_confirmation: '' }, original(), draft())

    expect(payload).not.toHaveProperty('employment')
  })

  it('sends the whole employment object when one of its fields changed (upsert semantics)', () => {
    const payload = buildUpdatePayload(
      {
        ...formValues,
        password: '',
        password_confirmation: '',
        employment: { ...formValues.employment, job_role_ids: [9] },
      },
      original(),
      draft(),
    )

    expect(payload.employment).toEqual({
      job_role_ids: [9],
      business_function_ids: [],
      managed_business_function_ids: [],
      reports_to_ids: [],
      primary_operational_site_id: null,
      remote_operational_site_ids: [],
      is_assignable: true,
      covers_all_product_categories: false,
      product_lines: [],
      hired_at: null,
      terminated_at: null,
      standard_daily_minutes: null,
      break_daily_minutes: null,
    })
  })

  /** Spec 0103 AC-028: the PATCH payload always carries both site-membership keys. */
  it('AC-028 — carries primary + remote site keys on the PATCH payload', () => {
    const payload = buildUpdatePayload(
      {
        ...formValues,
        password: '',
        password_confirmation: '',
        employment: {
          ...emptyEmployment,
          primary_operational_site_id: 8,
          remote_operational_site_ids: [11, 12],
        },
      },
      original(),
      draft(),
    )

    expect(payload.employment?.primary_operational_site_id).toBe(8)
    expect(payload.employment?.remote_operational_site_ids).toEqual([11, 12])
  })
})

describe('buildCreatePayload — optional password (spec 0177 AC-016)', () => {
  it('omits password and confirmation when both are empty (invite mode)', () => {
    const payload = buildCreatePayload({ ...formValues, password: '', password_confirmation: '' }, draft())

    expect('password' in payload).toBe(false)
    expect('password_confirmation' in payload).toBe(false)
  })

  it('sends both when a temporary password was typed', () => {
    const payload = buildCreatePayload(formValues, draft())

    expect(payload.password).toBe(TEST_PASSWORD)
    expect(payload.password_confirmation).toBe(TEST_PASSWORD)
  })
})

/** Spec 0272 D-6/D-5: the payment account travels only when it changed. */
describe('employment payment account (spec 0272)', () => {
  it('omits iban and iban_holder on create while empty', () => {
    const payload = buildCreatePayload(formValues, draft())
    expect(payload.employment).not.toHaveProperty('iban')
    expect(payload.employment).not.toHaveProperty('iban_holder')
  })

  it('sends iban and iban_holder on create once typed', () => {
    const payload = buildCreatePayload(
      { ...formValues, employment: { ...emptyEmployment, iban: 'IT60X0542811101000000123456', iban_holder: 'Ada Lovelace' } },
      draft(),
    )
    expect(payload.employment).toMatchObject({ iban: 'IT60X0542811101000000123456', iban_holder: 'Ada Lovelace' })
  })

  it('does not send the payment account on a PATCH that did not touch it (no manageCompensation, no 403)', () => {
    const payload = buildUpdatePayload(
      { ...formValues, password: '', password_confirmation: '', employment: { ...emptyEmployment, primary_operational_site_id: 8 } },
      original(),
      draft(),
    )
    expect(payload.employment).toBeDefined()
    expect(payload.employment).not.toHaveProperty('iban')
    expect(payload.employment).not.toHaveProperty('iban_holder')
  })

  it('clears a stored iban by sending null when the field is emptied', () => {
    const base = original()
    const withIban = { ...base, employment: { ...base.employment!, iban: 'IT60X0542811101000000123456' } }
    const payload = buildUpdatePayload(
      { ...formValues, password: '', password_confirmation: '', employment: { ...emptyEmployment, iban: '' } },
      withIban,
      draft(),
    )
    expect(payload.employment).toMatchObject({ iban: null })
  })
})
