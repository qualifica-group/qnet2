import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel } from '@/features/request-management/request-work-panel-fixtures'
import type { FieldPermission } from '@/features/authorization/types'

/**
 * Direttiva utente 2026-09-08 — the team block's third state: `manager_slots`
 * visible but locked, `permissions.actions.append_team_member` granted. The
 * squadra already assigned is frozen (no re-pick, no move, no removal) and the
 * actor may only add to its tail — the very prefix rule the server enforces on
 * the same payload (RequestManagementTeamAppendOnlyTest).
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** The user a click on an enabled slot trigger picks. */
const NEWCOMER_ID = 44

/** Stubbed to its accessible trigger name AND its disabled state — the latter is what this suite asserts. */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
    disabled,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      disabled={disabled}
      onClick={() => onChange(NEWCOMER_ID)}
    >
      {value ?? ''}
    </button>
  ),
}))

/** The Offerta's persisted squadra: the two rows the grant must freeze. */
const PERSISTED_MANAGERS = [
  { id: 11, name: 'Anna Neri', position: 1 },
  { id: 12, name: 'Bruno Verdi', position: 2 },
]

const LOCKED_TEAM: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

function panelWithTeamPermission(appendGranted: boolean) {
  return workPanel({
    managers: PERSISTED_MANAGERS,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: { manager_slots: LOCKED_TEAM },
      actions: { append_team_member: appendGranted },
    },
  })
}

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={4001} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

/** The card a section heading belongs to — `FormSection` renders no landmark of its own. */
async function teamSection(): Promise<HTMLElement> {
  const heading = await screen.findByRole('heading', { name: 'Team' })
  return heading.closest('section') as HTMLElement
}

/** The row a slot trigger sits in, the unit the frozen/free split applies to. */
function slotRow(team: HTMLElement, position: number): HTMLElement {
  return within(team).getByRole('button', { name: `Account manager ${position}` }).closest('li') as HTMLElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('Work panel — team append-only', () => {
  it('freezes the persisted members and leaves the tail writable', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panelWithTeamPermission(true))

    renderPanel()
    const team = await teamSection()

    const frozen = slotRow(team, 2)
    expect(within(frozen).getByRole('button', { name: 'Account manager 2' })).toBeDisabled()
    expect(within(frozen).getByRole('button', { name: 'Remove slot' })).toBeDisabled()
    expect(within(frozen).getByRole('button', { name: 'Move up' })).toBeDisabled()
    expect(within(frozen).getByRole('button', { name: 'Move down' })).toBeDisabled()

    const free = slotRow(team, 3)
    expect(within(free).getByRole('button', { name: 'Account manager 3' })).toBeEnabled()
    expect(within(free).getByRole('button', { name: 'Remove slot' })).toBeEnabled()
    // The first free row may not be moved UP: the swap would displace a frozen one.
    expect(within(free).getByRole('button', { name: 'Move up' })).toBeDisabled()
    expect(within(free).getByRole('button', { name: 'Move down' })).toBeEnabled()

    expect(within(team).getByRole('button', { name: 'Add account manager' })).toBeEnabled()
  })

  it('explains the state instead of showing the generic locked-field note', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panelWithTeamPermission(true))

    renderPanel()
    const team = await teamSection()

    expect(
      within(team).getByText('You may only add new managers: the ones already assigned cannot be changed.'),
    ).toBeInTheDocument()
  })

  it('PATCHes the whole array with the frozen prefix intact', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panelWithTeamPermission(true))
    updateRequestWorkMock.mockResolvedValue(panelWithTeamPermission(true))

    renderPanel()
    const team = await teamSection()
    fireEvent.click(within(team).getByRole('button', { name: 'Account manager 3' }))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      manager_slots: [PERSISTED_MANAGERS[0].id, PERSISTED_MANAGERS[1].id, NEWCOMER_ID, null],
    })
  })

  it('locks the whole block when the grant is missing', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panelWithTeamPermission(false))

    renderPanel()
    const team = await teamSection()

    expect(within(team).getByRole('button', { name: 'Account manager 3' })).toBeDisabled()
    expect(within(team).getByRole('button', { name: 'Add account manager' })).toBeDisabled()
  })

  it('leaves an editable team untouched — every slot stays free', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(workPanel({ managers: PERSISTED_MANAGERS }))

    renderPanel()
    const team = await teamSection()

    const first = slotRow(team, 1)
    expect(within(first).getByRole('button', { name: 'Account manager 1' })).toBeEnabled()
    expect(within(first).getByRole('button', { name: 'Remove slot' })).toBeEnabled()
    expect(within(slotRow(team, 2)).getByRole('button', { name: 'Move up' })).toBeEnabled()
  })
})
