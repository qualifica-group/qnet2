import type { ReactElement } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import type { FieldPermission } from '@/features/authorization/types'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * AC-077: `/opportunities/:id` shows every field via the record-panel kit;
 * spec 0198: each editable one through an in-place row (pencil), no Edit page.
 */

const READ_ONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

/** The in-place editor needs a query client (its save) and a router (the record links). */
function renderDetail(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <MemoryRouter>
      <QueryClientProvider client={client}>{ui}</QueryClientProvider>
    </MemoryRouter>,
  )
}

/**
 * Since the user directive of 2026-08-06 the Team rows are buttons too: they
 * open the shared read-only user-profile Sheet on click. AC-077 is about
 * controls that MUTATE the opportunity, so "read-only" can no longer be
 * asserted as "no button at all" — the profile openers are excluded by their
 * accessible name (`common.viewProfile`, "View {{name}}'s profile").
 */
const PROFILE_BUTTON_NAME = /'s profile$/

function mutatingButtons(): HTMLElement[] {
  return screen
    .queryAllByRole('button')
    .filter((button) => !PROFILE_BUTTON_NAME.test(button.getAttribute('aria-label') ?? ''))
}

// The collaboration card reads the actor's client abilities to gate its Notes
// tab (`request-management.view`): stub them, this suite has no AuthProvider
// (mirrors `request-work-panel.test.tsx`). Defaults to false so the existing
// read-only assertions below stay unaffected by the collaboration card.
const { canMock } = vi.hoisted(() => ({ canMock: vi.fn<(permission: string) => boolean>() }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

// Documents/notes/activity each have their own dedicated test suite: stubbed
// here to a plain marker so this suite only asserts the gating/composition,
// without the network/QueryClient dependencies those real sections need.
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: ({ entityType, entityId }: { entityType: string; entityId: number }) => (
    <div>{`notes:${entityType}:${entityId}`}</div>
  ),
}))
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: ({ resource, id }: { resource: string; id: number }) => (
    <div>{`documents:${resource}:${id}`}</div>
  ),
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: ({ resource, id }: { resource: string; id: number }) => (
    <div>{`activity:${resource}:${id}`}</div>
  ),
}))

function opportunity(
  overrides: Partial<OpportunityDetailWithPermissions> = {},
): OpportunityDetailWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: 10,
    registry: { id: 10, name: 'Acme S.p.A.' },
    status: { source: 'quotes', distinct_count: 1, entries: [{ id: 1, name: 'New', color: 'slate', group: 'open', count: 1 }] },
    referent_id: 60,
    referent: { id: 60, name: 'Mario Rossi' },
    commercial_id: 70,
    commercial: { id: 70, name: 'Luca Verdi' },
    reporter_id: 80,
    reporter: { id: 80, name: 'Giulia Neri' },
    supervisor_id: 90,
    supervisor: { id: 90, name: 'Paolo Blu' },
    source_id: 100,
    source: { id: 100, name: 'Web' },
    operational_site_id: 8,
    operational_site: { id: 8, label: 'Warehouse A - Milan' },
    product_lines: [
      {
        id: 500,
        business_function: { id: 50, name: 'Sales' },
        product_category: { id: 110, name: 'Consulting' },
      },
    ],
    lead_id: null,
    lead: null,
    managers: [
      { id: 200, name: 'Anna Bianchi', position: 1 },
      { id: 201, name: 'Marco Gialli', position: 2 },
    ],
    start_date: '2026-01-01',
    estimated_value: '15000.00',
    expected_close_date: '2026-06-30',
    success_probability: 60,
    locked_fields: [],
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockImplementation((): boolean => false)
})

describe('OpportunityDetailView — read-only (AC-077)', () => {
  it('renders every relation, the planning fields and the manager list', () => {
    renderDetail(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.getByRole('heading', { name: 'Enterprise deal' })).toBeInTheDocument()
    // "Acme S.p.A." appears twice: the header subtitle and the registry field.
    expect(screen.getAllByText('Acme S.p.A.').length).toBe(2)
    expect(screen.getByText('New')).toBeInTheDocument()
    expect(screen.getByText('Sales')).toBeInTheDocument()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText('Luca Verdi')).toBeInTheDocument()
    expect(screen.getByText('Giulia Neri')).toBeInTheDocument()
    expect(screen.getByText('Paolo Blu')).toBeInTheDocument()
    expect(screen.getByText('Web')).toBeInTheDocument()
    expect(screen.getByText(/Consulting/)).toBeInTheDocument()
    expect(screen.getByText('Anna Bianchi')).toBeInTheDocument()
    expect(screen.getByText('Marco Gialli')).toBeInTheDocument()
    // The KPI strip and the Details row (spec 0198) both show it.
    expect(screen.getAllByText('60%')).toHaveLength(2)
  })

  /**
   * User directive 2026-08-05: the operational site is hidden here exactly as
   * it already is in the form — the value still travels on the payload.
   */
  it('renders no operational site', () => {
    renderDetail(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.queryByText('Warehouse A - Milan')).not.toBeInTheDocument()
    expect(screen.queryByText('Operational site')).not.toBeInTheDocument()
  })

  // REQUIREMENT CHANGED (spec 0198): the record is no longer read-only for an
  // actor who may write it; a read-only actor still sees no control at all.
  it('renders no editable control for an actor whose fields are all read-only', () => {
    const fields = Object.fromEntries(
      [
        'name', 'registry_id', 'referent_id', 'commercial_id', 'reporter_id', 'supervisor_id', 'source_id',
        'product_lines', 'products_of_interest', 'manager_slots', 'start_date', 'estimated_value',
        'expected_close_date', 'success_probability', 'general_notes',
      ].map((field) => [field, READ_ONLY_FIELD]),
    )
    renderDetail(
      <OpportunityDetailView
        opportunity={opportunity({
          permissions: {
            resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
            fields,
            actions: {},
          },
        })}
      />,
    )

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(mutatingButtons()).toHaveLength(0)
    // Tab triggers carry `role="tab"`, not `button` — this fixture also has no
    // collaboration ability, so the tab strip itself is absent.
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
  })

  it('shows an em dash placeholder for unset optional fields', () => {
    renderDetail(
      <OpportunityDetailView
        opportunity={opportunity({
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
          operational_site_id: null,
          operational_site: null,
          product_lines: [],
          managers: [],
          start_date: null,
          estimated_value: null,
          expected_close_date: null,
          success_probability: null,
        })}
      />,
    )

    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(4)
  })

  it('shows the originating lead when the opportunity is linked to one', () => {
    renderDetail(
      <OpportunityDetailView
        opportunity={opportunity({ lead_id: 5, lead: { id: 5, label: 'Mario Rossi' } })}
      />,
    )

    expect(screen.getByText('Originating lead')).toBeInTheDocument()
  })

  it('does not show the originating lead section for a manually created opportunity', () => {
    renderDetail(<OpportunityDetailView opportunity={opportunity({ lead_id: null, lead: null })} />)

    expect(screen.queryByText('Originating lead')).not.toBeInTheDocument()
  })
})

/** Spec 0198 AC-001: the detail edits in place, there is no Edit action at all. */
describe('OpportunityDetailView — no edit page', () => {
  it('shows no Edit action, only the in-place pencils', () => {
    renderDetail(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Title' }) })).toBeInTheDocument()
  })
})

/**
 * The collaboration card mirrors `RequestWorkCollaboration`: one card, a
 * Notes | Documents | Activity tab strip, each tab gated by its own
 * authorization source, absent as a whole when nothing is authorized.
 */
describe('OpportunityDetailView — collaboration', () => {
  it('renders no collaboration card when nothing is authorized', () => {
    renderDetail(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
  })

  it('shows the Notes tab, selected by default, when request-management.view is granted', () => {
    canMock.mockImplementation((permission: string) => permission === 'request-management.view')
    renderDetail(<OpportunityDetailView opportunity={opportunity()} />)

    const notesTab = screen.getByRole('tab', { name: 'Notes' })
    expect(notesTab).toHaveAttribute('aria-selected', 'true')
    expect(screen.queryByRole('tab', { name: 'Documents' })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Activity log' })).not.toBeInTheDocument()
    expect(screen.getByText('notes:request-management:1')).toBeInTheDocument()
  })

  it('shows the Documents tab, reading the opportunity\'s own view_documents gate', () => {
    renderDetail(
      <OpportunityDetailView
        opportunity={opportunity({
          permissions: {
            resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
            fields: {},
            actions: { view_documents: true },
          },
        })}
      />,
    )

    expect(screen.getByRole('tab', { name: 'Documents' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Notes' })).not.toBeInTheDocument()
    expect(screen.getByText('documents:opportunity:1')).toBeInTheDocument()
  })

  it("shows the read-only Registry documents tab on the opportunity's registry with registries.viewDocuments (spec 0173)", () => {
    canMock.mockImplementation((permission: string) => permission === 'registries.viewDocuments')
    renderDetail(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.getByRole('tab', { name: 'Registry documents' })).toBeInTheDocument()
    expect(screen.getByText('documents:registry:10')).toBeInTheDocument()
  })

  it("shows the Activity log tab, reading the opportunity's own view_activity gate", () => {
    renderDetail(
      <OpportunityDetailView
        opportunity={opportunity({
          permissions: {
            resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
            fields: {},
            actions: { view_activity: true },
          },
        })}
      />,
    )

    expect(screen.getByRole('tab', { name: 'Activity log' })).toBeInTheDocument()
    expect(screen.getByText('activity:opportunities:1')).toBeInTheDocument()
  })
})

/** Spec 0059 D-3: the reward assignments recorded for the opportunity's reporter. */
describe('OpportunityDetailView — rewards', () => {
  it('renders the reward chips when the opportunity carries rewards', () => {
    renderDetail(
      <OpportunityDetailView
        opportunity={opportunity({
          rewards: [
            {
              id: 900,
              reward_type: { id: 1, name: 'Gift card', color: 'blue' },
              assigned_at: '2026-01-05',
              notes: null,
            },
          ],
        })}
      />,
    )

    expect(screen.getByText('Rewards')).toBeInTheDocument()
    expect(screen.getByText('Gift card')).toBeInTheDocument()
  })

  it('omits the rewards section when there is none', () => {
    renderDetail(<OpportunityDetailView opportunity={opportunity({ rewards: [] })} />)

    expect(screen.queryByText('Rewards')).not.toBeInTheDocument()
  })
})
