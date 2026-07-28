import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ReferentDetailView } from '@/features/referents/referent-detail'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * `ContactsManager`/`AddressesManager` call `useConfirm()` (needs a
 * `ConfirmDialogProvider`) and `useEnumOptions()` (needs a `QueryClient`)
 * unconditionally, even read-only — every render needs both ancestors.
 */
function renderDetail(referent: ReferentDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <ReferentDetailView referent={referent} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
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
    contacts: [
      {
        id: 5,
        type: 'email',
        label: 'Work',
        value: 'ada@work.com',
        is_primary: true,
        contactable_type: 'personal_data',
        contactable_id: 99,
        created_at: null,
      },
    ],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

function referent(overrides: Partial<ReferentDetailWithPermissions> = {}): ReferentDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    referent_type_id: 3,
    referent_type: { id: 3, name: 'Sponsor' },
    contact_scope: 'internal',
    notes: 'VIP sponsor',
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ReferentDetailView (AC-023)', () => {
  it('renders the name, contact scope, referent type and notes read-only', () => {
    renderDetail(referent())

    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.getByText('Internal')).toBeInTheDocument()
    expect(screen.getByText('Sponsor')).toBeInTheDocument()
    expect(screen.getByText('VIP sponsor')).toBeInTheDocument()
  })

  it('renders the contacts read-only, without any edit/add affordance', () => {
    renderDetail(referent())

    expect(screen.getByText('ada@work.com')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add contact' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit contact' })).not.toBeInTheDocument()
  })

  it('falls back to an em dash when the referent has no type', () => {
    renderDetail(referent({ referent_type: null, referent_type_id: null }))

    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })
})
