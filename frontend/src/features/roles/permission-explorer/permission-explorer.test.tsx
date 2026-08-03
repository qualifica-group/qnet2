import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { PermissionExplorer } from '@/features/roles/permission-explorer/permission-explorer'
import type { PermissionCatalogueArea } from '@/features/roles/permission-catalogue-api'

/**
 * Spec 0076 mounted coverage of the two-panel explorer: AC-010 (counters),
 * AC-011 (actions + fields in the same module scheda, native/custom split),
 * AC-012/013/014 (search filter/expand/empty state), AC-015/016 (tri-state +
 * scoped toggle), AC-017 (disabled locks checkboxes, search stays usable),
 * AC-018 (mandatory field locked), AC-023 (accessible names, aria-current).
 */

const AREAS: PermissionCatalogueArea[] = [
  {
    key: 'marketing-leads',
    label_key: 'navigation.marketingLeads',
    resources: [
      {
        resource: 'leads',
        label_key: 'navigation.leads',
        permissions: [
          { name: 'leads.viewAny', ability: 'viewAny' },
          { name: 'leads.view', ability: 'view' },
          { name: 'leads.export', ability: 'export' },
        ],
        fields: [
          { key: 'registry_id', type: 'relation', group: null, mandatory: false, custom: false, label: null },
          { key: 'custom.budget', type: 'number', group: 'Extra', mandatory: false, custom: true, label: 'Budget stimato' },
        ],
      },
      {
        resource: 'opportunities',
        label_key: 'navigation.opportunities',
        permissions: [{ name: 'opportunities.viewAny', ability: 'viewAny' }],
        fields: [],
      },
    ],
  },
  {
    key: 'shared',
    label_key: 'permissions.areas.shared',
    resources: [
      {
        resource: 'notes',
        label_key: 'permissions.resources.notes',
        permissions: [{ name: 'notes.viewAny', ability: 'viewAny' }],
        fields: [],
      },
    ],
  },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderExplorer(overrides: Partial<Parameters<typeof PermissionExplorer>[0]> = {}) {
  const onChange = vi.fn()
  render(
    <PermissionExplorer
      areas={AREAS}
      value={[]}
      disabled={false}
      onChange={onChange}
      fieldPermissions={null}
      {...overrides}
    />,
  )
  return { onChange }
}

describe('PermissionExplorer', () => {
  it('AC-010: shows selected/total counters per module, per area, and the selected module detail', () => {
    renderExplorer({ value: ['leads.viewAny'] })

    // The Leads module row AND the detail panel header both show its 1/3 counter.
    expect(screen.getAllByText('1/3')).toHaveLength(2)
    // The area header shows the area-wide 1/4 counter (leads 3 + notes 1).
    expect(screen.getByText('1/4')).toBeInTheDocument()
    // Leads is selected by default (the first module) and its actions render.
    expect(screen.getByText('Actions')).toBeInTheDocument()
  })

  it('AC-011: the selected module shows its actions and fields (native + custom) in one scheda, no separate section', async () => {
    renderExplorer({
      fieldPermissions: { value: [], disabled: false, onToggle: vi.fn() },
    })

    expect(screen.getByText('Actions')).toBeInTheDocument()
    expect(screen.getByText('Fields')).toBeInTheDocument()
    expect(screen.getByText('Native')).toBeInTheDocument()
    expect(screen.getByText('Custom')).toBeInTheDocument()
    expect(screen.getByText('Budget stimato')).toBeInTheDocument()
    expect(screen.queryByText('Field permissions')).not.toBeInTheDocument()
  })

  it('AC-012/013: search filters the tree by module label and technical permission name', () => {
    renderExplorer()

    const search = screen.getByLabelText('Search the permission catalogue')
    fireEvent.change(search, { target: { value: 'notes.viewAny' } })

    expect(screen.getByRole('button', { name: /^Notes\b/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Leads\b/ })).not.toBeInTheDocument()
  })

  it('AC-014: shows an explicit empty state when nothing matches', () => {
    renderExplorer()

    fireEvent.change(screen.getByLabelText('Search the permission catalogue'), {
      target: { value: 'no-such-permission-anywhere' },
    })

    expect(screen.getByText('No module or permission found.')).toBeInTheDocument()
  })

  it('AC-016: toggling the area checkbox grants every permission of every module in that area only', () => {
    const { onChange } = renderExplorer({ value: [] })

    fireEvent.click(screen.getByRole('checkbox', { name: 'Marketing & Leads — Select area' }))

    expect(onChange).toHaveBeenCalledWith(
      expect.arrayContaining(['leads.viewAny', 'leads.view', 'leads.export', 'opportunities.viewAny']),
    )
    expect(onChange.mock.calls[0][0]).not.toContain('notes.viewAny')
  })

  it('AC-015/016: the module checkbox is indeterminate on a partial selection and toggling it scopes to that module', () => {
    const { onChange } = renderExplorer({ value: ['leads.viewAny'] })

    const moduleCheckbox = screen.getByRole('checkbox', { name: 'Leads — Select all' })
    expect(moduleCheckbox).toHaveAttribute('aria-checked', 'mixed')

    fireEvent.click(moduleCheckbox)
    expect(onChange).toHaveBeenCalledWith(
      expect.arrayContaining(['leads.viewAny', 'leads.view', 'leads.export']),
    )
  })

  it('AC-017: disabled locks every checkbox but the search stays usable', () => {
    renderExplorer({ value: ['leads.viewAny'], disabled: true })

    const checkboxes = screen.getAllByRole('checkbox')
    expect(checkboxes.length).toBeGreaterThan(0)
    checkboxes.forEach((checkbox) => expect(checkbox).toBeDisabled())

    const search = screen.getByLabelText('Search the permission catalogue')
    expect(search).toBeEnabled()
    fireEvent.change(search, { target: { value: 'leads' } })
    expect(search).toHaveValue('leads')
  })

  it('AC-018: a mandatory field renders its three toggles checked and disabled', () => {
    const areasWithMandatory: PermissionCatalogueArea[] = [
      {
        ...AREAS[0]!,
        resources: [
          {
            ...AREAS[0]!.resources[0]!,
            fields: [
              { key: 'email', type: 'email', group: null, mandatory: true, custom: false, label: null },
            ],
          },
        ],
      },
    ]

    renderExplorer({
      areas: areasWithMandatory,
      fieldPermissions: { value: [], disabled: false, onToggle: vi.fn() },
    })

    expect(screen.getByRole('checkbox', { name: 'Email — Visible' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Email — Visible' })).toBeDisabled()
    expect(screen.getByRole('checkbox', { name: 'Email — Required' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Email — Required' })).toBeDisabled()
  })

  it('AC-023: the selected module is exposed with aria-current', () => {
    renderExplorer()

    expect(screen.getByRole('button', { name: /^Leads\b/ })).toHaveAttribute('aria-current', 'true')

    // `shared` is collapsed by default (only the first area opens automatically).
    fireEvent.click(screen.getByRole('button', { name: /^Shared\b/ }))
    expect(screen.getByRole('button', { name: /^Notes\b/ })).not.toHaveAttribute('aria-current')
  })
})
