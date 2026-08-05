import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/** AC-077: `/opportunities/:id` shows every field read-only via the record-panel kit. */

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
    render(<OpportunityDetailView opportunity={opportunity()} />)

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
    expect(screen.getByText('Warehouse A - Milan')).toBeInTheDocument()
    expect(screen.getByText(/Consulting/)).toBeInTheDocument()
    expect(screen.getByText('Anna Bianchi')).toBeInTheDocument()
    expect(screen.getByText('Marco Gialli')).toBeInTheDocument()
    expect(screen.getByText('60%')).toBeInTheDocument()
  })

  it('renders no editable control and no edit action without onEdit', () => {
    render(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    // Tab triggers carry `role="tab"`, not `button` — this fixture also has no
    // collaboration ability, so the tab strip itself is absent.
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
  })

  it('shows an em dash placeholder for unset optional fields', () => {
    render(
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
    render(
      <OpportunityDetailView
        opportunity={opportunity({ lead_id: 5, lead: { id: 5, label: 'Mario Rossi' } })}
      />,
    )

    expect(screen.getByText('Originating lead')).toBeInTheDocument()
  })

  it('does not show the originating lead section for a manually created opportunity', () => {
    render(<OpportunityDetailView opportunity={opportunity({ lead_id: null, lead: null })} />)

    expect(screen.queryByText('Originating lead')).not.toBeInTheDocument()
  })
})

/** The Modifica action, gated by `onEdit` AND `permissions.resource.update`. */
describe('OpportunityDetailView — edit action', () => {
  it('shows Modifica when onEdit is supplied and the actor can update', () => {
    render(<OpportunityDetailView opportunity={opportunity()} onEdit={vi.fn()} />)

    const buttons = screen.getAllByRole('button')
    expect(buttons).toHaveLength(1)
    expect(buttons[0]).toHaveAccessibleName('Edit')
  })

  it('hides Modifica when onEdit is absent', () => {
    render(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })

  it('hides Modifica when the actor cannot update the opportunity', () => {
    render(
      <OpportunityDetailView
        opportunity={opportunity({
          permissions: {
            resource: { view: true, create: true, update: false, delete: true, export: true, import: true },
            fields: {},
            actions: {},
          },
        })}
        onEdit={vi.fn()}
      />,
    )

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })

  it('calls onEdit when Modifica is clicked', () => {
    const onEdit = vi.fn()
    render(<OpportunityDetailView opportunity={opportunity()} onEdit={onEdit} />)

    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))

    expect(onEdit).toHaveBeenCalledOnce()
  })
})

/**
 * The collaboration card mirrors `RequestWorkCollaboration`: one card, a
 * Notes | Documents | Activity tab strip, each tab gated by its own
 * authorization source, absent as a whole when nothing is authorized.
 */
describe('OpportunityDetailView — collaboration', () => {
  it('renders no collaboration card when nothing is authorized', () => {
    render(<OpportunityDetailView opportunity={opportunity()} />)

    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
  })

  it('shows the Notes tab, selected by default, when request-management.view is granted', () => {
    canMock.mockImplementation((permission: string) => permission === 'request-management.view')
    render(<OpportunityDetailView opportunity={opportunity()} />)

    const notesTab = screen.getByRole('tab', { name: 'Notes' })
    expect(notesTab).toHaveAttribute('aria-selected', 'true')
    expect(screen.queryByRole('tab', { name: 'Documents' })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Activity log' })).not.toBeInTheDocument()
    expect(screen.getByText('notes:request-management:1')).toBeInTheDocument()
  })

  it('shows the Documents tab, reading the opportunity\'s own view_documents gate', () => {
    render(
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

  it("shows the Activity log tab, reading the opportunity's own view_activity gate", () => {
    render(
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
    render(
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
    render(<OpportunityDetailView opportunity={opportunity({ rewards: [] })} />)

    expect(screen.queryByText('Rewards')).not.toBeInTheDocument()
  })
})

/** Spec 0049 D-8/AC-064: additive read-only "Collected information" section. */
describe('OpportunityDetailView — collected information (AC-064)', () => {
  it('renders the applicable attributes with their formatted collected values', () => {
    render(
      <OpportunityDetailView
        opportunity={opportunity({
          applicable_attributes: [
            { id: 1, code: 'floor_area', name: 'Floor area', type: 'decimal', description: null, help_text: null, placeholder: null, icon: null, config: null, relation_target: null, is_required: true, sort_order: 1, options: [] },
            { id: 2, code: 'has_elevator', name: 'Has elevator', type: 'boolean', description: null, help_text: null, placeholder: null, icon: null, config: null, relation_target: null, is_required: false, sort_order: 2, options: [] },
            {
              id: 3,
              code: 'building_kind',
              name: 'Building kind',
              type: 'enum',
              description: null,
              help_text: null,
              placeholder: null,
              icon: null,
              config: null,
              relation_target: null,
              is_required: false,
              sort_order: 3,
              options: [
                { value: 'office', label: 'Office', color: 'blue' },
                { value: 'retail', label: 'Retail', color: 'amber' },
              ],
            },
            { id: 4, code: 'notes', name: 'Notes', type: 'text', description: null, help_text: null, placeholder: null, icon: null, config: null, relation_target: null, is_required: false, sort_order: 4, options: [] },
          ],
          attribute_values: {
            floor_area: '120.50',
            has_elevator: true,
            building_kind: 'office',
          },
        })}
      />,
    )

    expect(screen.getByText('Collected information')).toBeInTheDocument()
    expect(screen.getByText('Floor area')).toBeInTheDocument()
    expect(screen.getByText('120.50')).toBeInTheDocument()
    expect(screen.getByText('Has elevator')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText('Building kind')).toBeInTheDocument()
    expect(screen.getByText('Office')).toBeInTheDocument()
    // Notes has no collected value: falls back to the shared em dash placeholder.
    expect(screen.getByText('Notes')).toBeInTheDocument()
  })

  it('does not render the section when there is no applicable attribute', () => {
    render(
      <OpportunityDetailView
        opportunity={opportunity({ applicable_attributes: [], attribute_values: {} })}
      />,
    )

    expect(screen.queryByText('Collected information')).not.toBeInTheDocument()
  })

  it('does not crash and omits the section when both fields are absent from the fixture (older shape)', () => {
    render(
      <OpportunityDetailView
        opportunity={opportunity({ applicable_attributes: undefined, attribute_values: undefined })}
      />,
    )

    expect(screen.queryByText('Collected information')).not.toBeInTheDocument()
  })
})
