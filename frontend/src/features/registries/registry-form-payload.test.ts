import { describe, expect, it } from 'vitest'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataCard, PersonalDataDraft } from '@/features/personal-data/types'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/registries/registry-form-payload'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/** Spec 0020 AC-023: create payload shape, update payload diffs only changes. */

function individualDraft(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return {
    ...emptyPersonalDataDraft('individual'),
    first_name: 'Ada',
    last_name: 'Lovelace',
    // Matches `card()`'s gender below, so a draft built from defaults is
    // identical to `cardToDraft(original().personal_data)` unless overridden.
    gender: 'female',
    ...overrides,
  }
}

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'individual',
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
    gender: 'female',
    personable_type: 'registry',
    personable_id: 7,
    contacts: [],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

function original(
  overrides: Partial<RegistryDetailWithPermissions> = {},
): RegistryDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    source_id: 1,
    source: { id: 1, name: 'Website' },
    sector_ids: [10, 11],
    sectors: [{ id: 10, name: 'Sector A' }, { id: 11, name: 'Sector B' }],
    referent_ids: [20],
    referents: [{ id: 20, name: 'Referent A' }],
    manager_ids: [30],
    managers: [{ id: 30, name: 'Manager A', position: 1 }],
    manager_slots: [30],
    supervisor_id: 40,
    supervisor: { id: 40, name: 'Supervisor A' },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    vat_group: 'Group A',
    is_supplier: false,
    is_qualified_supplier: false,
    agreement_status: 'negotiating',
    agreement_notes: 'Some notes',
    size_class: 'small',
    employee_count: 12,
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

const formValues: RegistryFormValues = {
  source_id: 1,
  sector_ids: [10, 11],
  referent_ids: [20],
  manager_slots: [30],
  supervisor_id: 40,
  commercial_id: null,
  reporter_id: null,
  vat_group: 'Group A',
  is_supplier: false,
  is_qualified_supplier: false,
  agreement_status: 'negotiating',
  agreement_notes: 'Some notes',
  size_class: 'small',
  employee_count: 12,
  custom_fields: {},
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape, always carrying personal_data', () => {
    const payload = buildCreatePayload(formValues, individualDraft())

    expect(payload.source_id).toBe(1)
    expect(payload.sector_ids).toEqual([10, 11])
    expect(payload.manager_slots).toEqual([30])
    expect(payload.is_supplier).toBe(false)
    expect(payload.personal_data.first_name).toBe('Ada')
  })

  it('maps empty vat_group/agreement_notes strings to null', () => {
    const payload = buildCreatePayload(
      { ...formValues, vat_group: '', agreement_notes: '' },
      individualDraft(),
    )
    expect(payload.vat_group).toBeNull()
    expect(payload.agreement_notes).toBeNull()
  })

  it('forces is_qualified_supplier to false when is_supplier is false, regardless of stale state', () => {
    const payload = buildCreatePayload(
      { ...formValues, is_supplier: false, is_qualified_supplier: true },
      individualDraft(),
    )
    expect(payload.is_qualified_supplier).toBe(false)
  })

  it('sends is_qualified_supplier as chosen when is_supplier is true', () => {
    const payload = buildCreatePayload(
      { ...formValues, is_supplier: true, is_qualified_supplier: true },
      individualDraft(),
    )
    expect(payload.is_qualified_supplier).toBe(true)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field, including personal_data, when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original(), individualDraft())
    expect(payload).toEqual({})
  })

  it('includes only the changed scalar (vat_group)', () => {
    const payload = buildUpdatePayload(
      { ...formValues, vat_group: 'Group B' },
      original(),
      individualDraft(),
    )
    expect(payload).toEqual({ vat_group: 'Group B' })
  })

  it('sends supervisor_id: null when the relation is cleared', () => {
    const payload = buildUpdatePayload(
      { ...formValues, supervisor_id: null },
      original(),
      individualDraft(),
    )
    expect(payload).toEqual({ supervisor_id: null })
  })

  it('treats a reordered pivot array as unchanged (order-insensitive set compare)', () => {
    const payload = buildUpdatePayload(
      { ...formValues, sector_ids: [11, 10] },
      original(),
      individualDraft(),
    )
    expect(payload.sector_ids).toBeUndefined()
  })

  it('includes a pivot array when its id set actually changed', () => {
    const payload = buildUpdatePayload(
      { ...formValues, sector_ids: [10] },
      original(),
      individualDraft(),
    )
    expect(payload.sector_ids).toEqual([10])
  })

  it('detaches all sectors when the array is sent empty', () => {
    const payload = buildUpdatePayload(
      { ...formValues, sector_ids: [] },
      original(),
      individualDraft(),
    )
    expect(payload.sector_ids).toEqual([])
  })

  it('includes personal_data only when the buffered draft actually differs', () => {
    const changedDraft = individualDraft({ first_name: 'Grace' })
    const payload = buildUpdatePayload(formValues, original(), changedDraft)

    expect(payload.personal_data).toBeDefined()
    expect(payload.personal_data?.first_name).toBe('Grace')
    expect(payload.source_id).toBeUndefined()
    expect(payload.vat_group).toBeUndefined()
  })

  it('forces is_qualified_supplier false in the diff when is_supplier flips to false', () => {
    const payload = buildUpdatePayload(
      { ...formValues, is_supplier: false, is_qualified_supplier: true },
      original({ is_supplier: true, is_qualified_supplier: true }),
      individualDraft(),
    )
    expect(payload.is_supplier).toBe(false)
    expect(payload.is_qualified_supplier).toBe(false)
  })

  it('combines multiple changed fields in a single payload', () => {
    const payload = buildUpdatePayload(
      { ...formValues, agreement_status: 'agreed', employee_count: 20 },
      original(),
      individualDraft(),
    )

    expect(payload).toEqual({ agreement_status: 'agreed', employee_count: 20 })
  })
})
