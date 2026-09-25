import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { AssignmentScopeBalancedGroup } from '@/features/assignment/types'
import {
  AssignOperatorsDialog,
  type AssignOperatorsDialogInput,
} from '@/features/leads/assign-operators-dialog'

// Switching to "single" mode renders the real Operatore `AsyncPaginatedSelect`
// (a TanStack Query hook); this suite never mounts a `QueryClientProvider`
// since it is entirely about the balanced list, so it is stubbed the same way
// `assign-operators-dialog.test.tsx` does.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button" aria-label={labels.triggerLabel} />
  ),
}))

/**
 * Spec 0168 AC-011..AC-015: with a `balancedScope` wired, "Smistamento equo"
 * shows the per-Sede operator list (all selected by default, D-3), a
 * per-group tri-state checkbox, load per operator, and gates Confirm on the
 * scope being resolved and at least one operator still selected. Split out of
 * `assign-operators-dialog.test.tsx` (spec 0113) to keep both files under the
 * size thresholds.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function pickMode(name: 'Balanced split' | 'Assign to operator') {
  fireEvent.click(screen.getByRole('radio', { name }))
}

function confirm() {
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
}

const NAPOLI: AssignmentScopeBalancedGroup = {
  operational_site_id: 1,
  operational_site_label: 'Napoli',
  record_count: 3,
  operators: [
    { id: 11, label: 'Anna Bianchi', avatar_url: null, load: 2 },
    { id: 12, label: 'Bruno Verdi', avatar_url: null, load: 0 },
  ],
}
const ROMA: AssignmentScopeBalancedGroup = {
  operational_site_id: 2,
  operational_site_label: 'Roma',
  record_count: 1,
  operators: [{ id: 13, label: 'Carla Neri', avatar_url: null, load: 5 }],
}

function renderBalanced(
  overrides: {
    groups?: AssignmentScopeBalancedGroup[]
    unassignableCount?: number
    isResolving?: boolean
    isError?: boolean
    onAssign?: (input: AssignOperatorsDialogInput) => Promise<void>
  } = {},
) {
  const onAssign = overrides.onAssign ?? vi.fn().mockResolvedValue(undefined)
  const view = render(
    <AssignOperatorsDialog
      open
      onOpenChange={vi.fn()}
      selectionCount={4}
      operatorSiteId={null}
      balancedScope={{
        groups: overrides.groups ?? [NAPOLI, ROMA],
        unassignableCount: overrides.unassignableCount ?? 0,
        isResolving: overrides.isResolving ?? false,
        isError: overrides.isError ?? false,
      }}
      onAssign={onAssign}
    />,
  )
  pickMode('Balanced split')
  return { onAssign, rerender: view.rerender }
}

describe('AssignOperatorsDialog — balanced operator list (spec 0168)', () => {
  it('AC-011: shows the per-Sede list only in balanced mode, hidden in single', () => {
    renderBalanced()

    expect(screen.getByText('Napoli')).toBeInTheDocument()
    expect(screen.getByText('Roma')).toBeInTheDocument()
    expect(screen.getByText('Anna Bianchi')).toBeInTheDocument()
    expect(screen.getByText('Carla Neri')).toBeInTheDocument()

    pickMode('Assign to operator')
    expect(screen.queryByText('Napoli')).not.toBeInTheDocument()
  })

  it('AC-012: the group checkbox is tri-state and toggling one operator never touches another group', () => {
    renderBalanced()

    const napoliGroup = screen.getByRole('checkbox', { name: 'Napoli — Select all' })
    expect(napoliGroup).toHaveAttribute('aria-checked', 'true')

    fireEvent.click(screen.getByRole('checkbox', { name: 'Anna Bianchi — Napoli' }))
    expect(napoliGroup).toHaveAttribute('aria-checked', 'mixed')
    // Roma is untouched: its own operator stays selected.
    expect(screen.getByRole('checkbox', { name: 'Carla Neri — Roma' })).toHaveAttribute(
      'aria-checked',
      'true',
    )

    fireEvent.click(screen.getByRole('checkbox', { name: 'Bruno Verdi — Napoli' }))
    expect(napoliGroup).toHaveAttribute('aria-checked', 'false')

    fireEvent.click(napoliGroup)
    expect(screen.getByRole('checkbox', { name: 'Anna Bianchi — Napoli' })).toHaveAttribute(
      'aria-checked',
      'true',
    )
    expect(screen.getByRole('checkbox', { name: 'Bruno Verdi — Napoli' })).toHaveAttribute(
      'aria-checked',
      'true',
    )
  })

  it('AC-013: a group left at zero warns about its own records, and zero selection overall disables Confirm', () => {
    renderBalanced()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Anna Bianchi — Napoli' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Bruno Verdi — Napoli' }))
    expect(
      screen.getByText('No operator selected: these 3 record(s) will not be assigned.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Carla Neri — Roma' }))
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
  })

  it('AC-013: a positive `balanced_unassignable_count` shows the global warning', () => {
    renderBalanced({ unassignableCount: 2 })

    expect(
      screen.getByText('2 record(s) have no available operator and will not be assigned.'),
    ).toBeInTheDocument()
  })

  it('AC-014: Confirm sends one operators_by_site entry per group, only the selected ids', async () => {
    const { onAssign } = renderBalanced()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Anna Bianchi — Napoli' }))
    confirm()

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({
        mode: 'balanced',
        operators_by_site: [
          { operational_site_id: 1, operator_ids: [12] },
          { operational_site_id: 2, operator_ids: [13] },
        ],
      }),
    )
  })

  it('AC-015: Confirm stays disabled and the list shows a loading state while the scope resolves', () => {
    renderBalanced({ isResolving: true })

    expect(screen.getByRole('status')).toHaveTextContent('Looking up the available operators…')
    expect(screen.queryByText('Napoli')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
  })

  // Bug found in the real app: a FAILED lookup also leaves `groups`
  // `undefined`, exactly like the loading state — without `isError` the
  // picker fell back to `groups ?? []` and showed the misleading "no operator
  // available" empty state instead of naming the actual failure.
  it('AC-015: a failed scope names the failure and keeps Confirm disabled, never the empty state', () => {
    renderBalanced({ groups: undefined, isError: true })

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Could not load the operators. Close and reopen the dialog to retry.',
    )
    expect(
      screen.queryByText('No operator is available for the selected records.'),
    ).not.toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()
  })

  // D-3: every dialog open starts with everyone selected — Radix unmounts
  // `DialogContent`'s subtree while closed (see `AssignOperatorsDialogBody`'s
  // own doc comment), which is what discards the previous pick.
  it('AC-015: D-3 — closing and reopening the dialog resets the selection to everyone selected', () => {
    const props = {
      selectionCount: 4,
      operatorSiteId: null as number | null,
      balancedScope: { groups: [NAPOLI, ROMA], unassignableCount: 0, isResolving: false, isError: false },
      onAssign: vi.fn().mockResolvedValue(undefined),
    }
    const { rerender } = render(<AssignOperatorsDialog open onOpenChange={vi.fn()} {...props} />)
    pickMode('Balanced split')

    fireEvent.click(screen.getByRole('checkbox', { name: 'Anna Bianchi — Napoli' }))
    expect(screen.getByRole('checkbox', { name: 'Anna Bianchi — Napoli' })).toHaveAttribute(
      'aria-checked',
      'false',
    )

    rerender(<AssignOperatorsDialog open={false} onOpenChange={vi.fn()} {...props} />)
    expect(screen.queryByRole('checkbox', { name: 'Anna Bianchi — Napoli' })).not.toBeInTheDocument()

    rerender(<AssignOperatorsDialog open onOpenChange={vi.fn()} {...props} />)
    pickMode('Balanced split')

    expect(screen.getByRole('checkbox', { name: 'Anna Bianchi — Napoli' })).toHaveAttribute(
      'aria-checked',
      'true',
    )
  })

  it('without a balancedScope, balanced still confirms on the mode alone (backward compat)', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(
      <AssignOperatorsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        operatorSiteId={null}
        onAssign={onAssign}
      />,
    )
    pickMode('Balanced split')

    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
    confirm()

    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ mode: 'balanced' }))
  })
})
