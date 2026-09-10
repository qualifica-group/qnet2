import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { AssignOperatorsDialog } from '@/features/leads/assign-operators-dialog'

/**
 * Shared "Assegna operatori" popup (spec 0048, reshaped by spec 0113): the
 * user picks the assignment mode and, for `single`, the Operatore. The Sede is
 * no longer a user choice on the assignment surfaces — it is derived from the
 * records and only handed down as `operatorSiteId` to scope the picker — and
 * comes back as a real field only for the contact-transfer flow
 * (`showSiteField`, spec 0079), where it is the transfer destination.
 * The real `AsyncPaginatedSelect` is mocked to a plain button so these tests
 * exercise only this component's own orchestration (mode/site/operator state,
 * submit gating, pending/close-on-success), not the async select internals
 * (already covered by `async-paginated-select.test.tsx`).
 */

const SITE_PICK_ID = 7
const OPERATOR_PICK_ID = 42
const DERIVED_SITE_ID = 3

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    disabled,
    params,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    params?: Record<string, string | number | string[] | number[]>
    labels: { triggerLabel: string; empty: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      disabled={disabled}
      data-params={params ? JSON.stringify(params) : ''}
      data-empty={labels.empty}
      onClick={() => onChange(resource === 'operational-sites' ? SITE_PICK_ID : OPERATOR_PICK_ID)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

function pickMode(name: 'Balanced split' | 'Assign to operator') {
  fireEvent.click(screen.getByRole('radio', { name }))
}

function pickOperator() {
  fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
}

function confirm() {
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
}

function operatorButton() {
  return screen.getByRole('button', { name: 'Operator' })
}

/**
 * Default shape (spec 0113 AC-026/AC-028/AC-034): the three assignment
 * surfaces (import wizard, Lead table, Gestione richieste). No Sede field at
 * all; the picker is scoped by the Sede the call site resolved.
 */
describe('AssignOperatorsDialog — derived Sede (spec 0113)', () => {
  it('renders the two mode radios and never a Sede field (AC-026)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={3}
        operatorSiteId={DERIVED_SITE_ID}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('3 lead(s) selected.')).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Balanced split' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()

    pickMode('Assign to operator')
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()
    expect(operatorButton()).toBeInTheDocument()
  })

  it('confirms balanced on the mode alone, with no Sede in the payload (AC-026)', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    const onOpenChange = vi.fn()
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        operatorSiteId={DERIVED_SITE_ID}
        onAssign={onAssign}
      />,
    )
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    pickMode('Balanced split')
    expect(screen.queryByRole('button', { name: 'Operator' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
    confirm()

    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ mode: 'balanced' }))
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('single needs only the operator and sends no Sede (AC-026)', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        operatorSiteId={DERIVED_SITE_ID}
        onAssign={onAssign}
      />,
    )
    pickMode('Assign to operator')
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    pickOperator()
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
    confirm()

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({ mode: 'single', operator_id: OPERATOR_PICK_ID }),
    )
  })

  it('scopes the picker with the resolved Sede and the competence filter (AC-028)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        operatorSiteId={DERIVED_SITE_ID}
        competenceCategoryIds={[4, 9]}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Assign to operator')

    expect(operatorButton()).not.toBeDisabled()
    expect(operatorButton()).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: DERIVED_SITE_ID, competence_category_ids: [4, 9] }),
    )
    expect(
      screen.getByText('Only operators of the selected records Site, competent for their categories.'),
    ).toBeInTheDocument()
  })

  it('keeps the picker disabled and unfiltered while the scope is unresolved (AC-034)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Assign to operator')

    expect(operatorButton()).toBeDisabled()
    expect(operatorButton()).toHaveAttribute('data-params', '')
    expect(
      screen.getByText('The Site of the selected records is not available yet.'),
    ).toBeInTheDocument()
  })

  it('drops the Sede scope, keeping competence, when the selection spans several Sedi', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        operatorSiteId={null}
        competenceCategoryIds={[4]}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Assign to operator')

    expect(operatorButton()).not.toBeDisabled()
    expect(operatorButton()).toHaveAttribute(
      'data-params',
      JSON.stringify({ competence_category_ids: [4] }),
    )
    expect(
      screen.getByText(
        'The selection spans more than one Site: the list is filtered by competence only.',
      ),
    ).toBeInTheDocument()
  })

  it('disables the picker while the scope is being resolved (spec 0110 AC-043)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        operatorSiteId={DERIVED_SITE_ID}
        isResolvingCompetence
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Assign to operator')

    expect(operatorButton()).toBeDisabled()
    expect(screen.getByText('Looking up the competent operators…')).toBeInTheDocument()
  })

  it('names the competence empty state on the picker', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        operatorSiteId={DERIVED_SITE_ID}
        competenceCategoryIds={[4]}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Assign to operator')

    expect(operatorButton()).toHaveAttribute(
      'data-empty',
      'No operator is competent for the selected records.',
    )
  })

  it('keeps the dialog open and the picks intact when onAssign rejects', async () => {
    const onAssign = vi.fn().mockRejectedValue(new Error('failed'))
    const onOpenChange = vi.fn()
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        operatorSiteId={DERIVED_SITE_ID}
        onAssign={onAssign}
      />,
    )
    pickMode('Balanced split')
    confirm()

    await waitFor(() => expect(onAssign).toHaveBeenCalledTimes(1))
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
  })
})

/**
 * Spec 0113 AC-029/AC-030/D-5: the import review bar rules out `single` when
 * the selection spans several campaigns, and says why. `balanced` works row by
 * row and stays available. The other two surfaces pass nothing and keep both
 * cards active (AC-031, covered by every test above).
 */
describe('AssignOperatorsDialog — disabled modes (spec 0113)', () => {
  const MIXED_CAMPAIGNS_REASON = 'Unavailable: the selection spans rows from different campaigns.'

  function renderWithDisabledSingle() {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={5}
        operatorSiteId={null}
        disabledModes={['single']}
        disabledModeHints={{ single: MIXED_CAMPAIGNS_REASON }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
  }

  it('marks the single card as disabled and shows the reason (AC-029)', () => {
    renderWithDisabledSingle()

    const single = screen.getByRole('radio', { name: 'Assign to operator' })
    expect(single).toHaveAttribute('aria-disabled', 'true')
    expect(screen.getByText(MIXED_CAMPAIGNS_REASON)).toBeInTheDocument()
    // The reason is wired to the card, not just painted next to it.
    expect(single).toHaveAttribute('aria-describedby', screen.getByText(MIXED_CAMPAIGNS_REASON).id)
  })

  it('ignores clicks on the disabled card: no operator step, no confirm (AC-029)', () => {
    renderWithDisabledSingle()

    pickMode('Assign to operator')
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).toHaveAttribute(
      'aria-checked',
      'false',
    )
    expect(screen.queryByRole('button', { name: 'Operator' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
  })

  it('leaves the balanced card selectable and confirmable (AC-029)', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={5}
        operatorSiteId={null}
        disabledModes={['single']}
        disabledModeHints={{ single: MIXED_CAMPAIGNS_REASON }}
        onAssign={onAssign}
      />,
    )

    const balanced = screen.getByRole('radio', { name: 'Balanced split' })
    expect(balanced).not.toHaveAttribute('aria-disabled')
    pickMode('Balanced split')
    expect(balanced).toHaveAttribute('aria-checked', 'true')

    confirm()
    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ mode: 'balanced' }))
  })

  it('keeps both cards active when the call site disables nothing (AC-030/AC-031)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={5}
        operatorSiteId={DERIVED_SITE_ID}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).not.toHaveAttribute(
      'aria-disabled',
    )
    expect(screen.getByRole('radio', { name: 'Balanced split' })).not.toHaveAttribute(
      'aria-disabled',
    )
  })
})
