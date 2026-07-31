import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  areCreateContactsValid,
  hasPhoneContact,
  isCreateAddressValid,
} from '@/features/personal-data/create-validation'
import type { AddressDraft, ContactDraft } from '@/features/personal-data/types'

// The contact schema only uses `t` to build messages; these tests assert on
// success/failure, mirroring `contact-schema.test.ts`.
const t = ((key: string) => key) as unknown as TFunction

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

describe('isCreateAddressValid', () => {
  it('is valid when the buffer is empty (optional)', () => {
    expect(isCreateAddressValid([])).toBe(true)
  })

  it('is valid once line1 and the city are both set', () => {
    expect(isCreateAddressValid([address()])).toBe(true)
  })

  it('is invalid when line1 is missing', () => {
    expect(isCreateAddressValid([address({ line1: '' })])).toBe(false)
  })

  it('is invalid when the city is missing', () => {
    expect(isCreateAddressValid([address({ city_id: null })])).toBe(false)
  })
})

describe('areCreateContactsValid', () => {
  it('is valid when the buffer is empty', () => {
    expect(areCreateContactsValid([], t)).toBe(true)
  })

  it('is valid when every contact matches its per-type shape', () => {
    expect(
      areCreateContactsValid(
        [
          contact({ type: 'email', value: 'ada@example.com' }),
          contact({ _key: 'draft-2', type: 'phone', value: '+39 02 1234567' }),
        ],
        t,
      ),
    ).toBe(true)
  })

  it('is invalid when a contact does not match its type shape', () => {
    expect(
      areCreateContactsValid([contact({ type: 'email', value: 'not-an-email' })], t),
    ).toBe(false)
  })
})

describe('hasPhoneContact', () => {
  it('is false on an empty buffer', () => {
    expect(hasPhoneContact([])).toBe(false)
  })

  it('is false when no contact is a telephone number', () => {
    expect(hasPhoneContact([contact({ type: 'email' }), contact({ _key: 'draft-2', type: 'fax', value: '021234567' })])).toBe(
      false,
    )
  })

  it('is true on a landline', () => {
    expect(hasPhoneContact([contact({ type: 'phone', value: '+39 02 1234567' })])).toBe(true)
  })

  it('is true on a mobile', () => {
    expect(hasPhoneContact([contact({ type: 'mobile', value: '+39 333 1234567' })])).toBe(true)
  })

  it('is false when the phone row was emptied but still buffered', () => {
    expect(hasPhoneContact([contact({ type: 'phone', value: '   ' })])).toBe(false)
  })
})
