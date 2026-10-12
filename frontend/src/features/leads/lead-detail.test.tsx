import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { LeadDetailView } from '@/features/leads/lead-detail'
import type { LeadDetailWithPermissions } from '@/features/leads/types'

/**
 * AC-065: `/leads/:id` shows the lead with no control open. Since the card
 * moved onto the enterprise-CRM record kit it also owns real cross-record
 * LINKS (anagrafica, campagna, Sede, opportunita') and the anagrafica's
 * actionable contacts, so the view needs a Router and a QueryClient — and,
 * since its rows edit in place (spec 0217), the confirm dialog of the
 * Campaign cascade. REQUIREMENT CHANGED (spec 0217): the imported data
 * section is always there (empty = a dash), since it is where they are added.
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

// The conversion CTA in the identity band resolves the opportunities open mode,
// which reads the authenticated user's preference. The preference itself is not
// what these tests are about (`lead-conversion-action.test.tsx` owns it), so the
// resolver is stubbed rather than dragging an AuthProvider into every render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

function lead(overrides: Partial<LeadDetailWithPermissions> = {}): LeadDetailWithPermissions {
  return {
    id: 1,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'associated',
    operational_site_id: 30,
    operational_site: { id: 30, label: 'Via Roma 1 - Milano' },
    source_id: 40,
    source: { id: 40, name: 'Web' },
    operator_id: 50,
    operator: { id: 50, name: 'Anna Bianchi' },
    notes: 'Interested in the enterprise plan.',
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

/** A QueryClient per test, never per render (frontend.md §10): a shared cache makes these flaky. */
function renderDetail(data: LeadDetailWithPermissions = lead()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>
          <LeadDetailView lead={data} />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(false)
})

describe('LeadDetailView — record card (AC-065)', () => {
  it('renders the lead fields, the anagrafica as the heading and the colored status badge (AC-015)', () => {
    renderDetail()

    expect(screen.getByRole('heading', { name: 'Mario Rossi' })).toBeInTheDocument()
    // Twice by design: the subtitle of the identity band, and the link in Collegamenti.
    expect(screen.getAllByText('CMP-0001 — Spring push').length).toBe(2)
    expect(screen.getAllByText('Via Roma 1 - Milano').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Web').length).toBeGreaterThan(0)
    expect(screen.getByText('Associated')).toBeInTheDocument()
    expect(screen.getAllByText('Anna Bianchi').length).toBeGreaterThan(0)
    expect(screen.getByText('Interested in the enterprise plan.')).toBeInTheDocument()
  })

  it('renders no open control until a row is opened', () => {
    renderDetail()

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('shows an em dash placeholder for unset optional fields', () => {
    renderDetail(
      lead({
        operational_site_id: null,
        operational_site: null,
        source_id: null,
        source: null,
        operator_id: null,
        operator: null,
        notes: null,
      }),
    )

    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(4)
  })

  it('falls back to a placeholder heading when the registry relation is missing', () => {
    renderDetail(lead({ registry_id: 10, registry: null }))

    expect(screen.getByRole('heading', { name: 'Unknown registry' })).toBeInTheDocument()
  })
})

/** The point of the record card: a Lead is a junction, so every record it points at is reachable. */
describe('LeadDetailView — linked records', () => {
  it('links the anagrafica, the campaign and the Sede to their own module routes', () => {
    canMock.mockImplementation((permission) =>
      ['registries.view', 'campaigns.view', 'operational-sites.view'].includes(permission),
    )
    renderDetail()

    expect(screen.getByRole('link', { name: /Mario Rossi/ })).toHaveAttribute('href', '/registries/10')
    expect(screen.getByRole('link', { name: /Spring push/ })).toHaveAttribute('href', '/campaigns/20')
    expect(screen.getByRole('link', { name: /Via Roma 1 - Milano/ })).toHaveAttribute(
      'href',
      '/operational-sites/30',
    )
  })

  /**
   * The generated opportunity is reachable from the identity band's CTA ("Go to
   * opportunity", covered by `lead-conversion-action.test.tsx`), so the sections
   * below must not carry a SECOND link to the same record — exactly the kind of
   * duplication that made the old "links" group read as foreign here.
   */
  it('links the generated opportunity exactly once, from the identity band', () => {
    // The CTA is a link only for an actor who may view opportunities (user directive 2026-09-28).
    canMock.mockImplementation((permission) => permission === 'opportunities.view')
    renderDetail(lead({ opportunity: { id: 42, name: 'Deal Rossi' } }))

    const opportunityLinks = screen
      .getAllByRole('link')
      .filter((link) => link.getAttribute('href') === '/opportunities/42')

    expect(opportunityLinks).toHaveLength(1)
  })

  it('does not link a Sede whose server-composed label is empty', () => {
    renderDetail(lead({ operational_site: { id: 30, label: '' } }))

    expect(screen.queryByRole('link', { name: /operational-site/i })).not.toBeInTheDocument()
  })
})

/** The anagrafica's PRIMARY contacts, actionable: a lead you cannot call is not a lead. */
describe('LeadDetailView — contacts', () => {
  it('renders each primary contact as a dialable/mailable chip', () => {
    renderDetail(
      lead({
        registry: {
          id: 10,
          name: 'Mario Rossi',
          primary_contacts: [
            { type: 'email', icon: 'mail', label: 'Work', value: 'mario@example.com' },
            { type: 'phone', icon: 'phone', label: 'Personal', value: '+39 333 123 4567' },
          ],
        },
      }),
    )

    expect(screen.getByRole('link', { name: /mario@example.com/ })).toHaveAttribute(
      'href',
      'mailto:mario@example.com',
    )
    // Spaces are stripped: a dialler cannot parse them, and they invalidate the href.
    expect(screen.getByRole('link', { name: /\+39 333 123 4567/ })).toHaveAttribute(
      'href',
      'tel:+393331234567',
    )
  })

  it('never builds an href from an unsafe scheme carried in the value', () => {
    renderDetail(
      lead({
        registry: {
          id: 10,
          name: 'Mario Rossi',
          primary_contacts: [
            { type: 'website', icon: 'globe', label: 'Site', value: 'javascript:alert(1)' },
          ],
        },
      }),
    )

    expect(screen.queryByRole('link', { name: /javascript/ })).not.toBeInTheDocument()
    expect(screen.getByText('javascript:alert(1)')).toBeInTheDocument()
  })

  it('shows no contact chips when the anagrafica carries none', () => {
    canMock.mockImplementation((permission) => permission === 'registries.view')
    renderDetail(lead({ registry: { id: 10, name: 'Mario Rossi', primary_contacts: [] } }))

    expect(screen.getByRole('link', { name: /Mario Rossi/ })).toHaveAttribute('href', '/registries/10')
  })
})

/** Spec 0094 (D-5): the chosen products, which the previous card never showed at all. */
describe('LeadDetailView — products of interest (spec 0094)', () => {
  it('lists each product with the category it belongs to', () => {
    renderDetail(
      lead({
        products_of_interest: [
          { id: 7, name: 'Fotovoltaico 6kW', product_category: { id: 2, name: 'Energia' } },
        ],
      }),
    )

    expect(screen.getByText('Fotovoltaico 6kW')).toBeInTheDocument()
    expect(screen.getByText('— Energia')).toBeInTheDocument()
  })

  it('counts them in the KPI strip, zero included', () => {
    const { container } = renderDetail(lead({ products_of_interest: [] }))

    expect(within(container).getByText('0')).toBeInTheDocument()
  })
})

/** AC-014: the "Imported data" section lists extra_fields; empty, it shows a dash (spec 0217). */
describe('LeadDetailView — imported data (AC-014)', () => {
  it('shows every key/value pair when extra_fields is set', () => {
    renderDetail(lead({ extra_fields: { 'Original column A': 'foo', 'Original column B': 'bar' } }))

    expect(screen.getByRole('heading', { name: 'Imported data' })).toBeInTheDocument()
    expect(screen.getByText('Original column A')).toBeInTheDocument()
    expect(screen.getByText('foo')).toBeInTheDocument()
    expect(screen.getByText('Original column B')).toBeInTheDocument()
    expect(screen.getByText('bar')).toBeInTheDocument()
  })

  it('keeps the section, with a pencil to add pairs, when extra_fields is empty', () => {
    renderDetail(lead({ extra_fields: null }))

    expect(screen.getByRole('heading', { name: 'Imported data' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Imported data' })).toBeInTheDocument()
  })
})

/** The activity card is the record's side column, gated by its OWN authorization flag. */
describe('LeadDetailView — activity', () => {
  it('omits the activity card when the actor may not view it', () => {
    renderDetail()

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
  })
})
