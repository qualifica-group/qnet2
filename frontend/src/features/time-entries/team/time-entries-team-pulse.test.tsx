import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TimeEntriesTeamPulse } from '@/features/time-entries/team/time-entries-team-pulse'
import type { TeamPulse, TeamPulseMember } from '@/features/time-entries/types'

/** Spec 0122 AC-037/AC-038: click selects a member, the actor's own row is inert. */

const fetchTimeEntriesTeamMock = vi.fn()

vi.mock('@/features/time-entries/api', () => ({
  fetchTimeEntriesTeam: (...args: unknown[]) => fetchTimeEntriesTeamMock(...args),
}))

function member(overrides: Partial<TeamPulseMember> & { id: number; name: string }): TeamPulseMember {
  const { id, name, ...rest } = overrides
  return {
    user: { id, name, email: `${name}@example.test`, avatar_url: null },
    manager_ids: [],
    job_description: null,
    roles: [],
    business_functions: [],
    operational_site: null,
    coverage: { percentage: 60, working_days: 5, tracked_days: 3 },
    primary_cluster: null,
    ...rest,
  }
}

function renderTeamPulse(items: TeamPulseMember[], onSelectMember = vi.fn()) {
  fetchTimeEntriesTeamMock.mockResolvedValue({ is_full_list: false, items } satisfies TeamPulse)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <TimeEntriesTeamPulse
          periodParams={{ period_preset: 'week' }}
          currentUserId={1}
          enabled
          onSelectMember={onSelectMember}
        />
      </TooltipProvider>
    </QueryClientProvider>,
  )
  return { onSelectMember }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchTimeEntriesTeamMock.mockReset()
})

describe('TimeEntriesTeamPulse', () => {
  it('renders the manager and their subordinate once loaded', async () => {
    renderTeamPulse([
      member({ id: 1, name: 'Anna Ceo' }),
      member({ id: 2, name: 'Bruno Manager', manager_ids: [1] }),
    ])

    expect(await screen.findByText('Anna Ceo')).toBeInTheDocument()
    expect(screen.getByText('Bruno Manager')).toBeInTheDocument()
  })

  it('does not call onSelectMember when clicking the current user own row', async () => {
    const { onSelectMember } = renderTeamPulse([
      member({ id: 1, name: 'Anna Ceo' }),
      member({ id: 2, name: 'Bruno Manager', manager_ids: [1] }),
    ])

    const ownRow = await screen.findByText('Anna Ceo')
    fireEvent.click(ownRow.closest('button') as HTMLButtonElement)

    expect(onSelectMember).not.toHaveBeenCalled()
  })

  it('calls onSelectMember with the clicked member', async () => {
    const target = member({ id: 2, name: 'Bruno Manager', manager_ids: [1] })
    const { onSelectMember } = renderTeamPulse([member({ id: 1, name: 'Anna Ceo' }), target])

    const row = await screen.findByText('Bruno Manager')
    fireEvent.click(row.closest('button') as HTMLButtonElement)

    expect(onSelectMember).toHaveBeenCalledWith(target)
  })

  it('AC-013: collapsing one branch of a shared member leaves the other branch expanded', async () => {
    const managerA = member({ id: 10, name: 'Aldo A' })
    const managerB = member({ id: 11, name: 'Bice B' })
    const shared = member({ id: 12, name: 'Carlo Shared', manager_ids: [10, 11] })
    const grandchild = member({ id: 13, name: 'Dino Grandchild', manager_ids: [12] })

    renderTeamPulse([managerA, managerB, shared, grandchild])

    // Two distinct nodes for the same member (one per manager), each with its own toggle
    // keyed by path (`10/12` and `11/12`) — collapsing one must not flip the other.
    const carloNames = await screen.findAllByText('Carlo Shared')
    const [toggleUnderA, toggleUnderB] = carloNames.map(
      (name) => within(name.closest('div.relative') as HTMLElement).getByRole('button', { name: 'Collapse' }),
    )
    expect(toggleUnderA).toHaveAttribute('aria-expanded', 'true')
    expect(toggleUnderB).toHaveAttribute('aria-expanded', 'true')

    fireEvent.click(toggleUnderA)

    await waitFor(() => expect(toggleUnderA).toHaveAttribute('aria-expanded', 'false'))
    expect(toggleUnderB).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getAllByText('Carlo Shared')).toHaveLength(2)
  })

  it('shows the empty-team message when the API returns no members', async () => {
    renderTeamPulse([])

    expect(await screen.findByText('No team members in the current period.')).toBeInTheDocument()
  })

  it('shows the load-error message when the query fails', async () => {
    fetchTimeEntriesTeamMock.mockRejectedValue(new Error('network error'))
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <TooltipProvider>
          <TimeEntriesTeamPulse periodParams={{}} currentUserId={1} enabled onSelectMember={vi.fn()} />
        </TooltipProvider>
      </QueryClientProvider>,
    )

    await waitFor(() => expect(screen.getByText('Unable to load team data.')).toBeInTheDocument())
  })
})
