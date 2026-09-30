import { beforeAll, describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import i18n from '@/i18n'
import { buildAttributeValuesSchema } from '@/features/request-management/attribute-values-schema'
import { customFields as enCustomFields } from '@/i18n/locales/en-custom-fields'
import type { ApplicableAttribute } from '@/features/request-management/types'

/** Spec 0180: the `table` branch of the attribute schema (work orders, quotes, products, requests). */

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { customFields: enCustomFields }, true, true)
})

const t = i18n.t.bind(i18n) as unknown as TFunction

const AUDITS: ApplicableAttribute = {
  id: 1,
  code: 'audits',
  name: 'Audits',
  type: 'table',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: {
    columns: [
      { key: 'audit_date', label: 'Audit date', type: 'date', required: true },
      { key: 'inspector', label: 'Inspector', type: 'text' },
    ],
    selectable: { key: 'active', label: 'Active' },
    max_rows: 2,
  },
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

function paths(value: unknown): string[] | null {
  const result = buildAttributeValuesSchema([AUDITS], t).safeParse({ audits: value })
  return result.success ? null : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('attribute table schema', () => {
  it('accepts null and a valid value, dropping the server summary', () => {
    expect(paths(null)).toBeNull()
    const parsed = buildAttributeValuesSchema([AUDITS], t).parse({
      audits: { summary: 'x', rows: [{ id: 'a', audit_date: '2026-10-01', inspector: null, active: true }] },
    })
    expect(parsed.audits).not.toHaveProperty('summary')
  })

  it('reports a required cell, two selected rows and too many rows on the contract paths', () => {
    expect(paths({ rows: [{ audit_date: null }] })).toEqual(['audits.rows.0.audit_date'])
    expect(paths({ rows: [{ audit_date: 'a', active: true }, { audit_date: 'b', active: true }] })).toEqual(['audits.rows'])
    expect(paths({ rows: [{ audit_date: 'a' }, { audit_date: 'b' }, { audit_date: 'c' }] })).toEqual(['audits.rows'])
  })
})
