import { beforeAll, describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import type { TFunction } from 'i18next'
import i18n from '@/i18n'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import { applyTableServerErrors } from '@/features/custom-fields/custom-fields-errors'
import { buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'
import { isEmptyCustomFieldValue, isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import { TABLE_DESCRIPTOR } from '@/features/custom-fields/table-field.fixtures'
import { customFields as enCustomFields } from '@/i18n/locales/en-custom-fields'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor, TableFieldValue } from '@/features/custom-fields/types'

/** Spec 0180 AC-019 (schema + 422 mapping) and AC-020 (deep equality / payload). */

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { customFields: enCustomFields }, true, true)
})

const t = i18n.t.bind(i18n) as unknown as TFunction

function permissions(required: boolean): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: {
      'custom.audits': { visible: true, hidden: false, editable: true, readonly: false, required, disabled: false },
    },
    actions: {},
  }
}

function parse(value: TableFieldValue | null, descriptor: CustomFieldDescriptor = TABLE_DESCRIPTOR, required = false) {
  return buildCustomFieldsSchema([descriptor], permissions(required), t).safeParse({ audits: value })
}

function issuePaths(result: ReturnType<typeof parse>): string[] {
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('table schema', () => {
  it('accepts a valid value and strips summary', () => {
    const result = parse({ summary: '2026-10-01', rows: [{ id: 'a', audit_date: '2026-10-01', active: true }] })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.audits).not.toHaveProperty('summary')
    }
  })

  it('accepts null', () => {
    expect(parse(null).success).toBe(true)
  })

  it('flags an empty required cell on audits.rows.N.<col>', () => {
    const result = parse({ rows: [{ audit_date: '2026-10-01' }, { audit_date: null }] })
    expect(issuePaths(result)).toEqual(['audits.rows.1.audit_date'])
  })

  it('flags two selected rows on audits.rows', () => {
    const result = parse({ rows: [{ audit_date: 'a', active: true }, { audit_date: 'b', active: true }] })
    expect(issuePaths(result)).toEqual(['audits.rows'])
  })

  it('enforces min_rows and max_rows on audits.rows', () => {
    const strict: CustomFieldDescriptor = {
      ...TABLE_DESCRIPTOR,
      config: { ...TABLE_DESCRIPTOR.config, min_rows: 2, max_rows: 2 },
    }
    expect(issuePaths(parse({ rows: [{ audit_date: 'a' }] }, strict))).toEqual(['audits.rows'])
    expect(issuePaths(parse({ rows: [{ audit_date: 'a' }, { audit_date: 'b' }, { audit_date: 'c' }] }, strict))).toEqual([
      'audits.rows',
    ])
  })

  it('requires at least one row when the field is required', () => {
    expect(issuePaths(parse({ rows: [] }, TABLE_DESCRIPTOR, true))).toEqual(['audits'])
    expect(issuePaths(parse(null, TABLE_DESCRIPTOR, true))).toEqual(['audits'])
  })

  it('validates cells with the per-type rules (enum membership)', () => {
    expect(issuePaths(parse({ rows: [{ audit_date: 'a', stage: 'stage_9' }] }))).toEqual(['audits.rows.0.stage'])
  })
})

describe('table values', () => {
  const row = { id: 'a', audit_date: '2026-10-01', active: true }

  it('treats null and empty rows as empty', () => {
    expect(isEmptyCustomFieldValue({ rows: [] })).toBe(true)
    expect(isEmptyCustomFieldValue({ rows: [row] })).toBe(false)
  })

  it('compares deeply, with row order significant and summary ignored', () => {
    const other = { id: 'b', audit_date: '2026-11-01', active: false }
    expect(isEqualCustomFieldValue({ rows: [row, other], summary: 'x' }, { rows: [row, other] })).toBe(true)
    expect(isEqualCustomFieldValue({ rows: [row, other] }, { rows: [other, row] })).toBe(false)
    expect(isEqualCustomFieldValue({ rows: [row] }, { rows: [{ ...row, audit_date: 'z' }] })).toBe(false)
    expect(isEqualCustomFieldValue(null, { rows: [] })).toBe(true)
  })

  it('omits an unchanged table from the update payload and sends the whole rows when a cell changes', () => {
    const original = { audits: { rows: [row], summary: '2026-10-01' } }
    expect(buildCustomFieldsUpdate({ audits: { rows: [{ ...row }] } }, original)).toEqual({})
    const changed = { rows: [{ ...row, audit_date: '2026-12-01' }] }
    expect(buildCustomFieldsUpdate({ audits: changed }, original)).toEqual({ audits: changed })
  })
})

describe('applyTableServerErrors', () => {
  function unprocessable(errors: Record<string, string[]>): AxiosError {
    const error = new AxiosError('422')
    error.response = { status: 422, data: { errors } } as AxiosResponse
    return error
  }

  it('maps nested table keys onto RHF paths and ignores the rest', () => {
    const setError = vi.fn()
    applyTableServerErrors(
      unprocessable({
        'custom_fields.audits.rows.1.audit_date': ['bad date'],
        'custom_fields.audits.rows': ['too many'],
        'custom_fields.audits': ['not exact-path, handled elsewhere'],
        'custom_fields.other': ['nope'],
      }),
      setError,
      'custom_fields',
    )
    expect(setError.mock.calls).toEqual([
      ['custom_fields.audits.rows.1.audit_date', { message: 'bad date' }],
      ['custom_fields.audits.rows', { message: 'too many' }],
    ])
  })

  it('supports the attribute_values prefix', () => {
    const setError = vi.fn()
    applyTableServerErrors(unprocessable({ 'attribute_values.audits.rows.0.inspector': ['x'] }), setError, 'attribute_values')
    expect(setError).toHaveBeenCalledWith('attribute_values.audits.rows.0.inspector', { message: 'x' })
  })

  it('does nothing for non-422 errors', () => {
    const setError = vi.fn()
    applyTableServerErrors(new Error('boom'), setError, 'custom_fields')
    expect(setError).not.toHaveBeenCalled()
  })
})
