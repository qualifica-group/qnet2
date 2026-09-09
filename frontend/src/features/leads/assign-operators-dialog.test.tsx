import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { AssignOperatorsDialog } from '@/features/leads/assign-operators-dialog'

/**
 * Shared "Assegna operatori" popup (spec 0048): a sequential flow that first
 * picks the assignment mode (two radios), then the Sede, then (for `single`)
 * the Operatore filtered by that Sede, and finally a single confirm action.
 * The real `AsyncPaginatedSelect` is mocked to a plain button so these tests
 * exercise only this component's own orchestration (mode/site/operator
 * state, submit gating, pending/close-on-success), not the async select
 * internals (already covered by `async-paginated-select.test.tsx`).
 */

const SITE_PICK_ID = 7
const OPERATOR_PICK_ID = 42

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

function pickSite() {
  fireEvent.click(screen.getByRole('button', { name: 'Site' }))
}

function pickOperator() {
  fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
}

function confirm() {
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
}

describe('AssignOperatorsDialog', () => {
  it('renders the two mode radios and the confirm action, hiding the pickers until a mode is chosen', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={3}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('3 lead(s) selected.')).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Balanced split' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeInTheDocument()
    // Pickers only appear once a mode is selected.
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()

    pickMode('Assign to operator')
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Operator' })).toBeInTheDocument()
  })

  it('shows only the Sede picker for balanced mode (no Operatore)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Balanced split')
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Operator' })).not.toBeInTheDocument()
  })

  it('precompiles the Sede from defaultSite', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        defaultSite={{ id: 12, label: 'Milano' }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Balanced split')
    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('12')
  })

  it('disables the operator picker until a Sede is chosen, then filters it by that Sede', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Assign to operator')
    expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled()

    pickSite()

    const operatorButton = screen.getByRole('button', { name: 'Operator' })
    expect(operatorButton).not.toBeDisabled()
    expect(operatorButton).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_PICK_ID }),
    )
  })

  it('clears a previously chosen operator when the Sede changes', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickMode('Assign to operator')
    pickSite()
    pickOperator()
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveTextContent(String(OPERATOR_PICK_ID))

    pickSite()
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveTextContent('none')
  })

  it('balanced mode requires only a Sede and calls onAssign with mode=balanced, no operator', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    const onOpenChange = vi.fn()
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        onAssign={onAssign}
      />,
    )
    // Confirm stays disabled with no mode, and with a mode but no Sede.
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
    pickMode('Balanced split')
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    pickSite()
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
    confirm()

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({ operational_site_id: SITE_PICK_ID, mode: 'balanced' }),
    )
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('single mode keeps confirm disabled until an operator is chosen, then calls onAssign with mode=single', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    const onOpenChange = vi.fn()
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        onAssign={onAssign}
      />,
    )
    pickMode('Assign to operator')
    pickSite()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    pickOperator()
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
    confirm()

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({
        operational_site_id: SITE_PICK_ID,
        mode: 'single',
        operator_id: OPERATOR_PICK_ID,
      }),
    )
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('keeps the dialog open and the picks intact when onAssign rejects', async () => {
    const onAssign = vi.fn().mockRejectedValue(new Error('failed'))
    const onOpenChange = vi.fn()
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        onAssign={onAssign}
      />,
    )
    pickMode('Balanced split')
    pickSite()
    confirm()

    await waitFor(() => expect(onAssign).toHaveBeenCalledTimes(1))
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
  })
})

/**
 * `lockedMode` (spec 0079, additive/retro-compatible): skips the step-1 mode
 * picker entirely, fixes `mode`, and always shows the Operatore field. The
 * three pre-existing consumers never pass it (AC-029), asserted by every test
 * above still passing unmodified.
 */
describe('AssignOperatorsDialog — lockedMode (spec 0079)', () => {
  it('never renders the mode radios and shows Sede + Operatore right away (AC-027)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        lockedMode="single"
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.queryByRole('radio', { name: 'Balanced split' })).not.toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Assign to operator' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Operator' })).toBeInTheDocument()
  })

  it('overrides the title/description from copy', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        lockedMode="single"
        copy={{ title: 'Transfer contact', description: '2 request(s) selected.', modeHints: { balanced: '', single: '' } }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByRole('dialog')).toHaveTextContent('Transfer contact')
    expect(screen.getByText('2 request(s) selected.')).toBeInTheDocument()
  })

  it('requires both Sede and Operatore before submitting, then calls onAssign with the locked mode (AC-027)', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    const onOpenChange = vi.fn()
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={1}
        lockedMode="single"
        onAssign={onAssign}
      />,
    )
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    pickSite()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    pickOperator()
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
    confirm()

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({
        operational_site_id: SITE_PICK_ID,
        mode: 'single',
        operator_id: OPERATOR_PICK_ID,
      }),
    )
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('clears the chosen Operatore when the Sede changes (AC-028)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        lockedMode="single"
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickSite()
    pickOperator()
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveTextContent(String(OPERATOR_PICK_ID))

    pickSite()
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveTextContent('none')
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_PICK_ID }),
    )
  })
})

/**
 * Spec 0110 AC-041/AC-043: the dialog stays dumb — the CALL SITE resolves the
 * competence of the selection and hands it down; the dialog only forwards it
 * to the picker, disables it while the resolution is in flight, and names the
 * empty state for what it is.
 */
describe('AssignOperatorsDialog — competence filter (spec 0110)', () => {
  function renderWithCompetence(props: {
    competenceCategoryIds?: number[]
    isResolvingCompetence?: boolean
  }) {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        onAssign={vi.fn().mockResolvedValue(undefined)}
        {...props}
      />,
    )
    pickMode('Assign to operator')
  }

  it('adds the competence filter to the Sede scope of the operator picker', () => {
    renderWithCompetence({ competenceCategoryIds: [4, 9] })
    pickSite()

    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_PICK_ID, competence_category_ids: [4, 9] }),
    )
  })

  it('keeps the picker on the plain Sede scope when there is no requirement', () => {
    renderWithCompetence({})
    pickSite()

    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_PICK_ID }),
    )
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
      'data-empty',
      'No results found.',
    )
  })

  it('names the competence empty state, and keeps confirm disabled with no candidate (AC-043)', () => {
    renderWithCompetence({ competenceCategoryIds: [4] })
    pickSite()

    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
      'data-empty',
      'No operator is competent for the selected records.',
    )
    // Nothing can be picked from an empty list, so `single` cannot be confirmed.
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
  })

  it('disables the picker, and explains why, while the requirement is being resolved (AC-043)', () => {
    renderWithCompetence({ isResolvingCompetence: true })
    pickSite()

    expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled()
    expect(screen.getByText('Looking up the competent operators…')).toBeInTheDocument()
  })

  it('tells the user the list is competence-scoped once the filter applies', () => {
    renderWithCompetence({ competenceCategoryIds: [4] })
    pickSite()

    expect(
      screen.getByText('Only Site operators competent for the selected records.'),
    ).toBeInTheDocument()
  })
})
