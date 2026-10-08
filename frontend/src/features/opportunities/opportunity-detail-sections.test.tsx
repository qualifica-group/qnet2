import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render as rtlRender, screen, within } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { GENERAL_NOTES_CALLOUT_CLASS } from '@/components/record-form/layout'
import { UserDetailSheetContext } from '@/features/users/user-detail-sheet-context'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * Every render goes through a Router (the card links related records with real
 * `<Link>`s) and a query client (the in-place editor's save, spec 0198).
 */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <MemoryRouter>
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    </MemoryRouter>
  )
  return rtlRender(ui, { wrapper })
}

/** The sections as the detail mounts them: inside its permissions and its in-place edit form. */
function Sections({ opportunity }: { opportunity: OpportunityDetailWithPermissions }) {
  return <OpportunityDetailView opportunity={opportunity} />
}

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

/**
 * Spec 0080: the manager list shows the resolved role label as VISIBLE text
 * (never a `title`-only tooltip — invisible on touch, unreliable for screen
 * readers), falling back to today's shared default with no configuration.
 */

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function opportunity(overrides: Partial<OpportunityDetailWithPermissions> = {}): OpportunityDetailWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: 10,
    registry: { id: 10, name: 'Acme S.p.A.' },
    status: { source: 'default', distinct_count: 0, entries: [] },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: null,
    source: null,
    product_lines: [],
    lead_id: null,
    lead: null,
    managers: [
      { id: 200, name: 'Anna Bianchi', position: 1 },
      { id: 201, name: 'Marco Gialli', position: 2 },
    ],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    locked_fields: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('OpportunityDetailSections — manager role labels (spec 0080)', () => {
  it('AC-032: shows the shared default "Account manager n" as VISIBLE text with no configuration', () => {
    render(<Sections opportunity={opportunity()} />)

    expect(screen.getByText('Account manager 1')).toBeInTheDocument()
    expect(screen.getByText('Account manager 2')).toBeInTheDocument()
  })

  it("AC-044: shows the opportunity's resolved override as VISIBLE text, unconfigured positions keep the default", () => {
    render(<Sections opportunity={opportunity({ manager_labels: { '1': 'Commercial' } })} />)

    expect(screen.getByText('Commercial')).toBeInTheDocument()
    expect(screen.getByText('Account manager 2')).toBeInTheDocument()
    expect(screen.queryByText('Account manager 1')).not.toBeInTheDocument()
  })

  it('opens the user profile Sheet from the supervisor and from every manager row', () => {
    const opened: number[] = []

    render(
      <UserDetailSheetContext.Provider value={{ openUserDetail: (id) => opened.push(id), canOpenUserDetail: true }}>
        <Sections
          opportunity={opportunity({ supervisor_id: 300, supervisor: { id: 300, name: 'Luca Verdi' } })}
        />
      </UserDetailSheetContext.Provider>,
    )

    // The hover card is a sighted-user enhancement; the row itself is the
    // button, so the profile stays reachable without hovering at all.
    fireEvent.click(screen.getByRole('button', { name: "View Luca Verdi's profile" }))
    fireEvent.click(screen.getByRole('button', { name: "View Anna Bianchi's profile" }))

    expect(opened).toEqual([300, 200])
  })

  it('shows the supervisor on the same person row as the managers, with its avatar initials', () => {
    render(
      <Sections
        opportunity={opportunity({ supervisor_id: 300, supervisor: { id: 300, name: 'Luca Verdi' } })}
      />,
    )

    expect(screen.getByText('Luca Verdi')).toBeInTheDocument()
    // The initials fallback is what tells the row apart from the plain-text
    // fields above it: before the restyle the supervisor had no avatar at all.
    expect(screen.getByText('LV')).toBeInTheDocument()
  })

  it('AC-053 (amendment A1): a label configured past the 4th position shows the same as the first four', () => {
    render(
      <Sections
        opportunity={opportunity({
          managers: [
            { id: 200, name: 'Anna Bianchi', position: 1 },
            { id: 205, name: 'Elio Ferro', position: 5 },
          ],
          manager_labels: { '5': 'Field consultant' },
        })}
      />,
    )

    expect(screen.getByText('Account manager 1')).toBeInTheDocument()
    expect(screen.getByText('Field consultant')).toBeInTheDocument()
    expect(screen.queryByText('Account manager 5')).not.toBeInTheDocument()
  })
})

/**
 * User directive 2026-08-06: read-only notes wear the SAME callout as the form's
 * editable field — same component, same colour. Asserted against the shared
 * constant, so the day the chrome changes both surfaces move together or this
 * fails.
 */
describe('OpportunityDetailSections — general notes', () => {
  it('renders the note in the shared callout, preserving its line breaks', () => {
    render(
      <Sections
        opportunity={opportunity({ general_notes: 'First line\nSecond line' })}
      />,
    )

    const region = screen.getByRole('region', { name: /general notes/i })
    expect(region).toHaveTextContent('First line')
    expect(region).toHaveClass(...GENERAL_NOTES_CALLOUT_CLASS.split(' '))
    expect(screen.getByText(/First line/)).toHaveClass('whitespace-pre-wrap')
  })

  /*
   * Requirement changed (user directive 2026-10-07, "lo stesso stile come in
   * gestione richieste"): an editable record with no note shows the empty
   * callout inviting the note, as the request work panel does; only a
   * read-only one still shows nothing.
   */
  it('renders the empty callout with its placeholder when the note is editable', () => {
    render(<Sections opportunity={opportunity({ general_notes: null })} />)

    const region = screen.getByRole('region', { name: /general notes/i })
    expect(region).toHaveTextContent('Write a note about this opportunity…')
    expect(region).toHaveClass(...GENERAL_NOTES_CALLOUT_CLASS.split(' '))
  })

  it('opens the textarea inside the callout', () => {
    render(<Sections opportunity={opportunity({ general_notes: null })} />)

    const region = screen.getByRole('region', { name: /general notes/i })
    fireEvent.click(within(region).getByRole('button', { name: /edit general notes/i }))

    expect(within(region).getByRole('textbox', { name: /general notes/i })).toBeInTheDocument()
  })

  it('renders no empty box when the note is read-only and empty', () => {
    render(
      <Sections
        opportunity={opportunity({
          general_notes: null,
          permissions: {
            ...FULL_PERMISSIONS,
            fields: {
              general_notes: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
            },
          },
        })}
      />,
    )

    expect(screen.queryByRole('region', { name: /general notes/i })).not.toBeInTheDocument()
  })
})

describe('OpportunityDetailSections — related records', () => {
  it('links the anagrafica, the referents, the source lead and the products of interest to their records', () => {
    render(
      <Sections
        opportunity={opportunity({
          referent: { id: 20, name: 'Ada Alberti' },
          commercial: { id: 21, name: 'Bruno Bianchi' },
          reporter: { id: 22, name: 'Carla Conti' },
          lead_id: 30,
          lead: { id: 30, label: 'Lead Rossi' },
          products_of_interest: [{ id: 40, name: 'Fotovoltaico', product_category: null }],
        })}
      />,
    )

    const hrefOf = (name: string) => screen.getByRole('link', { name }).getAttribute('href')
    expect(hrefOf('Acme S.p.A.')).toBe('/registries/10')
    expect(hrefOf('Ada Alberti')).toBe('/referents/20')
    expect(hrefOf('Bruno Bianchi')).toBe('/referents/21')
    expect(hrefOf('Carla Conti')).toBe('/referents/22')
    expect(hrefOf('Lead Rossi')).toBe('/leads/30')
    expect(hrefOf('Fotovoltaico')).toBe('/products/40')
  })
})
