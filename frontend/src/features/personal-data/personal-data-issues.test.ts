import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import {
  describeAddressIssues,
  describeCardIssues,
  describeContactIssues,
  isPersonalDataCardValid,
} from '@/features/personal-data/personal-data-issues'
import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'

const t = i18n.t.bind(i18n)

function card(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return { ...emptyPersonalDataDraft(), ...overrides }
}

function address(overrides: Partial<AddressDraft> = {}): AddressDraft {
  return {
    _key: 'draft-1',
    line1: 'Via Roma 1',
    line2: null,
    postal_code: null,
    city_id: 7,
    province_id: null,
    state_id: null,
    country_id: null,
    is_primary: true,
    site_type: 'billing',
    ...overrides,
  }
}

function contact(overrides: Partial<ContactDraft> = {}): ContactDraft {
  return {
    _key: 'draft-1',
    type: 'email',
    value: 'ada@example.com',
    label: null,
    is_primary: true,
    ...overrides,
  }
}

describe('describeCardIssues', () => {
  it('names both missing name fields of an individual card', () => {
    const issues = describeCardIssues(card({ type: 'individual' }), t)

    expect(issues).toEqual([
      `${t('personalData.fieldLabels.first_name')}: ${t('personalData.form.firstNameRequired')}`,
      `${t('personalData.fieldLabels.last_name')}: ${t('personalData.form.lastNameRequired')}`,
    ])
  })

  it('names the company name of a company card', () => {
    const issues = describeCardIssues(card({ type: 'company' }), t)

    expect(issues).toEqual([
      `${t('personalData.fieldLabels.company_name')}: ${t('personalData.form.companyNameRequired')}`,
    ])
  })

  it('names a fiscal field whose value is present but invalid', () => {
    const issues = describeCardIssues(
      card({ type: 'company', company_name: 'Acme', vat_number: '123' }),
      t,
    )

    expect(issues).toEqual([
      `${t('personalData.fieldLabels.vat_number')}: ${t('personalData.form.vatNumberInvalid')}`,
    ])
  })

  it('reports nothing for a complete card, agreeing with the save gate', () => {
    const complete = card({ type: 'individual', first_name: 'Ada', last_name: 'Lovelace' })

    expect(describeCardIssues(complete, t)).toEqual([])
    expect(isPersonalDataCardValid(complete, t)).toBe(true)
  })
})

describe('describeAddressIssues', () => {
  it('reports nothing for an empty buffer (the address is optional)', () => {
    expect(describeAddressIssues([], t)).toEqual([])
  })

  it('names the street and the city of a started, incomplete address', () => {
    const issues = describeAddressIssues([address({ line1: '', city_id: null })], t)

    expect(issues).toEqual([
      `${t('personalData.addresses.line1')}: ${t('personalData.addresses.line1Required')}`,
      `${t('geo.city')}: ${t('personalData.addresses.cityRequired')}`,
    ])
  })
})

describe('describeContactIssues', () => {
  it('names the quick field an invalid value was typed into', () => {
    const issues = describeContactIssues([contact({ value: 'not-an-email' })], t)

    expect(issues).toEqual([
      `${t('personalData.contacts.quickEmail')}: ${t('personalData.contacts.valueEmail')}`,
    ])
  })

  it('reports nothing when every buffered contact validates', () => {
    expect(describeContactIssues([contact()], t)).toEqual([])
  })
})
