import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCompanyClosureSchema, canRepeatYearly, daysBetween } from '@/features/company-closures/company-closure-schema'
import { buildPayload, CREATE_DEFAULTS } from '@/features/company-closures/company-closure-form-payload'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID = { ...CREATE_DEFAULTS, name: 'Patron saint', starts_on: '2026-06-24', ends_on: '2026-06-24' }

function issues(values: typeof VALID) {
  const result = buildCompanyClosureSchema(i18n.t).safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => `${issue.path.join('.')}: ${issue.message}`)
}

describe('company closure schema (spec 0304 D-13)', () => {
  it('accepts a single-day holiday on all sites', () => {
    expect(issues(VALID)).toEqual([])
  })

  // REQUIREMENT CHANGED: 31 days counting both ends, as the server checks them (2026-06-24..07-24 is the longest).
  it('refuses an end before the start and a closure over 31 days', () => {
    expect(issues({ ...VALID, ends_on: '2026-06-23' })).toEqual([`ends_on: ${i18n.t('companyClosures.form.endsBeforeStart')}`])
    expect(issues({ ...VALID, ends_on: '2026-07-25' })).toEqual([`ends_on: ${i18n.t('companyClosures.form.spanTooLong')}`])
    expect(issues({ ...VALID, ends_on: '2026-07-24' })).toEqual([])
    expect(daysBetween('2026-06-24', '2026-07-25')).toBe(31)
  })

  it('requires the absence type for a collective leave only', () => {
    expect(issues({ ...VALID, kind: 'collective_leave' })).toEqual([
      `absence_type_id: ${i18n.t('companyClosures.form.absenceTypeRequired')}`,
    ])
    expect(issues({ ...VALID, kind: 'collective_leave', absence_type_id: 3 })).toEqual([])
  })

  it('requires at least one site when it is not for all sites', () => {
    expect(issues({ ...VALID, applies_to_all_sites: false })).toEqual([
      `operational_site_ids: ${i18n.t('companyClosures.form.sitesRequired')}`,
    ])
  })
})

describe('company closure payload', () => {
  it('lets the yearly recurrence through only for a single-day holiday', () => {
    expect(canRepeatYearly(VALID)).toBe(true)
    expect(buildPayload({ ...VALID, repeats_yearly: true }).repeats_yearly).toBe(true)
    expect(buildPayload({ ...VALID, repeats_yearly: true, ends_on: '2026-06-25' }).repeats_yearly).toBe(false)
    expect(
      buildPayload({ ...VALID, repeats_yearly: true, kind: 'collective_leave', absence_type_id: 3 }).repeats_yearly,
    ).toBe(false)
  })

  it('sends the absence type and the sites only when their rule applies', () => {
    expect(buildPayload({ ...VALID, absence_type_id: 3, operational_site_ids: [1] })).toEqual({
      name: 'Patron saint',
      starts_on: '2026-06-24',
      ends_on: '2026-06-24',
      kind: 'holiday',
      applies_to_all_sites: true,
      repeats_yearly: false,
    })
    expect(
      buildPayload({ ...VALID, kind: 'collective_leave', absence_type_id: 3, applies_to_all_sites: false, operational_site_ids: [1, 2] }),
    ).toMatchObject({ absence_type_id: 3, applies_to_all_sites: false, operational_site_ids: [1, 2] })
  })
})
