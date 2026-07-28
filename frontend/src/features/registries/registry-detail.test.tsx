import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

// The owner-agnostic managers are covered by their own suites; stub them so this
// test isolates the RegistryDetailView's own sections (identity + responsible
// people with their primary contacts).
vi.mock('@/features/personal-data/addresses-manager', () => ({
  AddressesManager: () => <div data-testid="addresses" />,
}))
vi.mock('@/features/personal-data/contacts-manager', () => ({
  ContactsManager: () => <div data-testid="contacts" />,
}))

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 1,
    type: 'company',
    first_name: null,
    last_name: null,
    company_name: 'Acme S.p.A.',
    full_name: 'Acme S.p.A.',
    ceo: null,
    tax_code: 'RSSMRA80A01H501U',
    vat_number: 'IT12345678901',
    sdi_code: 'ABCDEF1',
    birth_date: null,
    birth_city_id: null,
    residence_city_id: null,
    gender: null,
    personable_type: 'registry',
    personable_id: 1,
    contacts: [],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

function registry(overrides: Partial<RegistryDetailWithPermissions> = {}): RegistryDetailWithPermissions {
  return {
    id: 1,
    name: 'Acme S.p.A.',
    source_id: null,
    source: null,
    sector_ids: [],
    sectors: [],
    referent_ids: [],
    referents: [],
    manager_ids: [],
    managers: [],
    manager_slots: [],
    supervisor_id: 9,
    supervisor: {
      id: 9,
      name: 'Mario Rossi',
      primary_contacts: [
        { type: 'email', icon: null, label: 'Email', value: 'mario@acme.it' },
      ],
    },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    vat_group: null,
    is_supplier: false,
    is_qualified_supplier: false,
    agreement_status: null,
    agreement_notes: null,
    size_class: null,
    employee_count: null,
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

describe('RegistryDetailView', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('renders the identity section with the fiscal fields', () => {
    render(<RegistryDetailView registry={registry()} />)
    expect(screen.getByText('RSSMRA80A01H501U')).toBeInTheDocument()
    expect(screen.getByText('IT12345678901')).toBeInTheDocument()
    expect(screen.getByText('ABCDEF1')).toBeInTheDocument()
  })

  it("shows a responsible person's name and their primary contact", () => {
    render(<RegistryDetailView registry={registry()} />)
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText(/mario@acme\.it/)).toBeInTheDocument()
  })
})
