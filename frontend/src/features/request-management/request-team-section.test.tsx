import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * Spec 0097 rev-2 D-7/D-9: in the "Lavora" panel the team is a SECTION of its
 * own — the Offerta's Supervisore plus its G.A. slots — and "Attribuzione" is
 * back to Fonte/Segnalatore/Sede (AC-010). The Sede <-> slot 2 link across the
 * two sections has its own suite
 * (`request-attribution-operator-link.test.tsx`, AC-011).
 *
 * AC-013/AC-014: the Supervisore is written from this channel again, on its
 * own key and coupled to nothing — picking one PATCHes `supervisor_id` alone.
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

/** The user a click on the Supervisore trigger picks. */
const SUPERVISOR_ID = 33

/** Stubbed to its accessible trigger name: what is under test is where each control lives, and what a pick sends. */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" aria-label={labels.triggerLabel} onClick={() => onChange(SUPERVISOR_ID)}>
      {value ?? ''}
    </button>
  ),
}))

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
async function sectionOf(title: string): Promise<HTMLElement> {
  const heading = await screen.findByRole('heading', { name: title })
  return heading.closest('section') as HTMLElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('Work panel — the team is a section of its own (AC-010)', () => {
  it('groups the Supervisore and the G.A. slots under "Team"', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(workPanel())

    renderPanel()
    const team = await sectionOf('Team')

    expect(within(team).getByRole('button', { name: 'Supervisor' })).toBeInTheDocument()
    expect(within(team).getByRole('button', { name: 'Account manager 1' })).toBeInTheDocument()
    expect(within(team).getByRole('button', { name: 'Account manager 2' })).toBeInTheDocument()
  })

  it('leaves "Attribution" with the Fonte/Segnalatore/Sede trio alone', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(workPanel())

    renderPanel()
    const attribution = await sectionOf('Attribution')

    expect(within(attribution).getByRole('button', { name: 'Source' })).toBeInTheDocument()
    expect(within(attribution).getByRole('button', { name: 'Operational site' })).toBeInTheDocument()
    expect(within(attribution).queryByRole('button', { name: 'Supervisor' })).not.toBeInTheDocument()
    expect(within(attribution).queryByRole('button', { name: 'Account manager 2' })).not.toBeInTheDocument()
  })

  /**
   * The hint followed the control it describes, not the Sede that produces it:
   * the two are one section apart now. Its condition is unchanged — a Sede is
   * set, and both fields are really on screen.
   */
  it('keeps the scoping hint next to the slots it describes', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      workPanel({ operational_site_id: 77, operational_site: { id: 77, label: 'Warehouse A' } }),
    )

    renderPanel()
    const team = await sectionOf('Team')

    expect(within(team).getByText('Only the operators of the selected site.')).toBeInTheDocument()
  })
})

describe('Work panel — the Supervisore (AC-013/AC-014)', () => {
  it('PATCHes supervisor_id alone when it is the only field touched', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(workPanel())
    updateRequestWorkMock.mockResolvedValue(workPanel())

    renderPanel()
    fireEvent.click(await screen.findByRole('button', { name: 'Supervisor' }))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({ supervisor_id: SUPERVISOR_ID })
  })
})
