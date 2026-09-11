import { beforeAll, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
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

/**
 * The record kit the card was rebuilt on (user directive 2026-09-11): the KPI
 * strip, the identity pills, and the Edit action the card now owns instead of
 * leaving it to the page chrome.
 */
describe('ReferentDetailView — record card (user directive 2026-09-11)', () => {
  it('counts the contacts and addresses of the anagraphic card in the KPI strip', () => {
    renderDetail(referent())

    expect(statValue('Contact details')).toBe('1')
    expect(statValue('Locations')).toBe('0')
    expect(statValue('Card type')).toBe('Individual')
  })

  it('names the linked system user ONCE, on its labelled row', () => {
    renderDetail(referent({ user_id: 4, user: { id: 4, name: 'Ada (account)' } }))

    expect(screen.getByText('Linked user')).toBeInTheDocument()
    // Once, not twice: the identity band deliberately carries no user pill.
    expect(screen.getAllByText('Ada (account)')).toHaveLength(1)
  })

  it("omits the row entirely when the actor may not see the link (the key is absent, not null)", () => {
    renderDetail(referent())

    expect(screen.queryByText('Linked user')).not.toBeInTheDocument()
  })

  it('offers Edit only when the response grants update AND the host gives a handler', () => {
    const onEdit = vi.fn()

    renderDetail(referent())
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()

    cleanup()
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <ConfirmDialogProvider>
          <ReferentDetailView referent={referent()} onEdit={onEdit} />
        </ConfirmDialogProvider>
      </QueryClientProvider>,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))
    expect(onEdit).toHaveBeenCalledTimes(1)
  })
})

/** The value `RecordStat` renders right under its label, addressed by that label's text. */
function statValue(label: string): string | undefined {
  return screen.getByText(label).nextElementSibling?.textContent ?? undefined
}
