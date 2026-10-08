import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render as rtlRender, screen, within } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Every render goes through a Router (the card links related records with real
 * `<Link>`s) and a QueryClient (the in-place editor reads the resource meta,
 * spec 0200).
 */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  )
  return rtlRender(ui, { wrapper: Wrapper })
}

// The resource meta carries the custom field definitions (spec 0200 D-3):
// none here, the in-place rows have their own suite.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: { resource: {}, fields: {}, actions: {} } }),
}))

// Related-record links open their target in a modal through `useModuleOpener`,
// whose mode resolver reads the authenticated user's preference. The preference
// is not what these tests are about, so the resolver is stubbed rather than
// dragging an AuthProvider into every render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

// Related-record links render only for an actor who can view the target
// module; these tests are not about abilities, so every ability is granted.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

// The owner-agnostic managers are covered by their own suites; stub them so this
// test isolates the RegistryDetailView's own sections (identity + responsible
// people with their primary contacts).
vi.mock('@/features/personal-data/addresses-manager', () => ({
  AddressesManager: () => <div data-testid="addresses" />,
}))
vi.mock('@/features/personal-data/contacts-manager', () => ({
  ContactsManager: () => <div data-testid="contacts" />,
}))

// Spec 0173: the documents tab mounts the shared DocumentsSection, whose own
// upload/delete flow is covered by `documents-section.test.tsx`.
const documentsSectionMock = vi.fn()
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number }) => {
    documentsSectionMock(props)
    return <div>{`documents-section:${props.resource}:${props.id}`}</div>
  },
}))

// Spec 0199: the related-records tabs mount the modules' own grids; their
// gating and wiring are covered by `registry-related-records.test.tsx`.
vi.mock('@/features/registries/registry-related-records', () => ({
  RegistryRelatedRecords: ({ registryId }: { registryId: number }) => <div>{`related-records:${registryId}`}</div>,
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
 * strip that sizes the anagrafica at a glance and the people block.
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

  // REQUIREMENT CHANGED (spec 0200): there is no edit page, so no Edit action —
  // the record's fields edit in place (`registry-detail-inline-edit.test.tsx`).
  it('offers no Edit action: the record edits in place', () => {
    render(<RegistryDetailView registry={registry()} />)
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})

describe('RegistryDetailView — documents tab (spec 0173)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('hides the Documents tab without the view_documents gate', () => {
    render(<RegistryDetailView registry={registry()} />)
    expect(screen.queryByRole('tab', { name: 'Documents' })).not.toBeInTheDocument()
  })

  it("shows the Documents tab on the registry's own attachable alias with view_documents", () => {
    render(
      <RegistryDetailView
        registry={registry({ permissions: { ...registry().permissions, actions: { view_documents: true } } })}
      />,
    )

    expect(screen.getByRole('tab', { name: 'Documents' })).toBeInTheDocument()
    expect(screen.getByText('documents-section:registry:1')).toBeInTheDocument()
    expect(documentsSectionMock).toHaveBeenCalledWith(expect.objectContaining({ resource: 'registry', id: 1 }))
  })
})

describe('RegistryDetailView — related records (spec 0199)', () => {
  it("mounts the client's related-records tabs for this anagrafica", () => {
    render(<RegistryDetailView registry={registry()} />)
    expect(screen.getByText('related-records:1')).toBeInTheDocument()
  })
})

/** The value `RecordStat` renders right under its label, addressed by that label's text. */
function statValue(label: string): string | undefined {
  return screen.getByText(label).nextElementSibling?.textContent ?? undefined
}
