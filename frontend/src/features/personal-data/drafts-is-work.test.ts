import { describe, expect, it } from 'vitest'
import { cardToDraft, draftToPayload } from '@/features/personal-data/drafts'
import type { Contact, PersonalDataCard } from '@/features/personal-data/types'

/** Spec 0272 D-7 / AC-011: `is_work` round-trips on the user's contacts and is absent for owners that never set it. */

function card(contacts: Partial<Contact>[]): PersonalDataCard {
  return {
    id: 1,
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
    residence_city_id: null,
    gender: null,
    personable_type: 'user',
    personable_id: 7,
    contacts: contacts.map((contact, index) => ({
      id: index + 1,
      type: 'phone',
      label: null,
      value: '+39 0212345',
      is_primary: false,
      contactable_type: 'personal_data',
      contactable_id: 1,
      created_at: null,
      ...contact,
    })),
    addresses: [],
    created_at: null,
  }
}

describe('contact is_work in drafts', () => {
  it('carries is_work from the server row into the payload', () => {
    const payload = draftToPayload(cardToDraft(card([{ is_work: true }, { is_work: false }])))
    expect(payload.contacts?.map((contact) => contact.is_work)).toEqual([true, false])
  })

  it('leaves is_work out of the payload when the row has none', () => {
    const payload = draftToPayload(cardToDraft(card([{}])))
    expect(payload.contacts?.[0]).not.toHaveProperty('is_work')
  })
})
