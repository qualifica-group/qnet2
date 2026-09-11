import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
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

/**
 * The record kit the card was rebuilt on (user directive 2026-09-11): the KPI
 * strip that sizes the anagrafica at a glance, the people block, and the Edit
 * action the card now owns instead of leaving it to the page chrome.
 */
describe('RegistryDetailView — record card (user directive 2026-09-11)', () => {
  it('counts referents, account managers and sectors in the KPI strip', () => {
    render(
      <RegistryDetailView
        registry={registry({
          referents: [
            { id: 1, name: 'Ada Lovelace' },
            { id: 2, name: 'Grace Hopper' },
          ],
          sectors: [{ id: 5, name: 'Energy' }],
          managers: [{ id: 3, name: 'Mario Rossi', position: 1 }],
          manager_slots: [3],
          employee_count: 42,
        })}
      />,
    )

    expect(statValue('Linked referents')).toBe('2')
    expect(statValue('Assigned managers')).toBe('1')
    expect(statValue('Business sectors')).toBe('1')
    expect(statValue('Employees')).toBe('42')
  })

  it('puts the supervisor and the G.A. slots in ONE Team block, a row each', () => {
    render(
      <RegistryDetailView
        registry={registry({
          managers: [{ id: 3, name: 'Giulia Bianchi', position: 1 }],
          manager_slots: [3],
        })}
      />,
    )

    // Same block Opportunità/Offerta carry (user directive 2026-09-11): the
    // supervisor and every slot share one list, so their labels line up.
    const team = screen.getByText('Team').closest('section') as HTMLElement
    expect(within(team).getByText('Supervisor')).toBeInTheDocument()
    expect(within(team).getByText('Mario Rossi')).toBeInTheDocument()
    expect(within(team).getByText('Account manager 1')).toBeInTheDocument()
    expect(within(team).getByText('Giulia Bianchi')).toBeInTheDocument()
  })

  it('keeps an empty G.A. slot visible as a placeholder row, never drops it', () => {
    render(
      <RegistryDetailView
        registry={registry({
          managers: [{ id: 3, name: 'Mario Rossi', position: 2 }],
          // Slot 1 is deliberately empty: the gap is data, not a missing row —
          // dropping it would renumber every G.A. below it.
          manager_slots: [null, 3],
        })}
      />,
    )

    const team = screen.getByText('Team').closest('section') as HTMLElement
    expect(within(team).getByText('Account manager 1')).toBeInTheDocument()
    expect(within(team).getByText('Empty slot')).toBeInTheDocument()
    expect(within(team).getByText('Account manager 2')).toBeInTheDocument()
  })

  it('offers Edit only when the response grants update AND the host gives a handler', () => {
    const onEdit = vi.fn()

    const { rerender } = render(<RegistryDetailView registry={registry()} />)
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()

    rerender(<RegistryDetailView registry={registry()} onEdit={onEdit} />)
    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))
    expect(onEdit).toHaveBeenCalledTimes(1)

    rerender(
      <RegistryDetailView
        registry={registry({
          permissions: {
            resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
            fields: {},
            actions: {},
          },
        })}
        onEdit={onEdit}
      />,
    )
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})

/** The value `RecordStat` renders right under its label, addressed by that label's text. */
function statValue(label: string): string | undefined {
  return screen.getByText(label).nextElementSibling?.textContent ?? undefined
}
