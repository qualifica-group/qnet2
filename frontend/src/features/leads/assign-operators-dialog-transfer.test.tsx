import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { AssignOperatorsDialog } from '@/features/leads/assign-operators-dialog'

/**
 * Contact-transfer use of the shared popup (spec 0079), the only caller that
 * opts the Sede field back in (`showSiteField`, spec 0113 D-2/AC-027): there
 * the Sede is the DESTINATION the user picks, not a filter derived from the
 * records. Split out of `assign-operators-dialog.test.tsx` to keep both files
 * within the size thresholds. Same mock rationale as that file.
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

function pickSite() {
  fireEvent.click(screen.getByRole('button', { name: 'Site' }))
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
 * `showSiteField` (spec 0113 AC-027) + `lockedMode` (spec 0079): the
 * contact-transfer flow, unchanged. The Sede is a required user pick — it is
 * the DESTINATION of the transfer — and travels in the payload.
 */
describe('AssignOperatorsDialog — transfer flow (showSiteField + lockedMode)', () => {
  it('never renders the mode radios and shows Sede + Operatore right away (AC-027)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        showSiteField
        lockedMode="single"
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.queryByRole('radio', { name: 'Balanced split' })).not.toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Assign to operator' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
    expect(operatorButton()).toBeInTheDocument()
  })

  it('overrides the title/description from copy', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        showSiteField
        lockedMode="single"
        copy={{
          title: 'Transfer contact',
          description: '2 request(s) selected.',
          modeHints: { balanced: '', single: '' },
        }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByRole('dialog')).toHaveTextContent('Transfer contact')
    expect(screen.getByText('2 request(s) selected.')).toBeInTheDocument()
  })

  it('precompiles the Sede from defaultSite', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        showSiteField
        lockedMode="single"
        defaultSite={{ id: 12, label: 'Milano' }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('12')
  })

  it('requires both Sede and Operatore, then sends operational_site_id (AC-027)', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    const onOpenChange = vi.fn()
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={1}
        showSiteField
        lockedMode="single"
        onAssign={onAssign}
      />,
    )
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
    expect(operatorButton()).toBeDisabled()
    expect(screen.getByText('Choose a Site first to see its operators.')).toBeInTheDocument()

    pickSite()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
    expect(operatorButton()).not.toBeDisabled()
    expect(operatorButton()).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_PICK_ID }),
    )

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

  it('clears the chosen Operatore when the Sede changes (AC-027)', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        showSiteField
        lockedMode="single"
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickSite()
    pickOperator()
    expect(operatorButton()).toHaveTextContent(String(OPERATOR_PICK_ID))

    pickSite()
    expect(operatorButton()).toHaveTextContent('none')
    expect(operatorButton()).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_PICK_ID }),
    )
  })

  it('ignores operatorSiteId while the Sede field is shown', () => {
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={1}
        showSiteField
        operatorSiteId={DERIVED_SITE_ID}
        lockedMode="single"
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(operatorButton()).toBeDisabled()

    pickSite()
    expect(operatorButton()).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_PICK_ID }),
    )
  })
})
