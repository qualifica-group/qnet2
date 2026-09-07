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

/** Blank employment sub-form, mirroring `EMPTY_EMPLOYMENT` in `use-user-form.ts`. */
const emptyEmployment: UserFormValues['employment'] = {
  is_manager: false,
  job_description: '',
  reports_to_id: null,
  business_function_id: null,
  relationship_type: null,
  company_id: null,
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  qualification_type: null,
  hired_at: '',
  terminated_at: '',
  standard_daily_minutes: null,
  break_daily_minutes: null,
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
          business_function_id: 3,
          relationship_type: 'employee',
          company_id: 5,
          primary_operational_site_id: 8,
          remote_operational_site_ids: [11, 12],
          qualification_type: 'coordinator',
          hired_at: '2026-01-15',
          terminated_at: '',
          standard_daily_minutes: 480,
          break_daily_minutes: 30,
          job_description: 'Backend engineer',
        },
      },
      draft(),
    )

    expect(payload.employment).toEqual({
      is_manager: false,
      job_description: 'Backend engineer',
      reports_to_id: null,
      business_function_id: 3,
      relationship_type: 'employee',
      company_id: 5,
      primary_operational_site_id: 8,
      remote_operational_site_ids: [11, 12],
      qualification_type: 'coordinator',
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

  it('AC-015 — force-nulls reports_to_id client-side when is_manager is true', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: { ...emptyEmployment, is_manager: true, reports_to_id: 42 },
      },
      draft(),
    )

    expect(payload.employment.is_manager).toBe(true)
    expect(payload.employment.reports_to_id).toBeNull()
  })

  it('keeps reports_to_id when is_manager is false', () => {
    const payload = buildCreatePayload(
      {
        ...formValues,
        employment: { ...emptyEmployment, is_manager: false, reports_to_id: 42 },
      },
      draft(),
    )

    expect(payload.employment.reports_to_id).toBe(42)
  })
})

describe('buildUpdatePayload — employment (spec 0015)', () => {
  it('always includes the employment object (upsert semantics)', () => {
    const payload = buildUpdatePayload(
      { ...formValues, password: '', password_confirmation: '' },
      original(),
      draft(),
    )

    expect(payload.employment).toEqual({
      is_manager: false,
      job_description: null,
      reports_to_id: null,
      business_function_id: null,
      relationship_type: null,
      company_id: null,
      primary_operational_site_id: null,
      remote_operational_site_ids: [],
      qualification_type: null,
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
