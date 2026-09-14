import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
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
    manager_id: null,
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
      member({ id: 2, name: 'Bruno Manager', manager_id: 1 }),
    ])

    expect(await screen.findByText('Anna Ceo')).toBeInTheDocument()
    expect(screen.getByText('Bruno Manager')).toBeInTheDocument()
  })

  it('does not call onSelectMember when clicking the current user own row', async () => {
    const { onSelectMember } = renderTeamPulse([
      member({ id: 1, name: 'Anna Ceo' }),
      member({ id: 2, name: 'Bruno Manager', manager_id: 1 }),
    ])

    const ownRow = await screen.findByText('Anna Ceo')
    fireEvent.click(ownRow.closest('button') as HTMLButtonElement)

    expect(onSelectMember).not.toHaveBeenCalled()
  })

  it('calls onSelectMember with the clicked member', async () => {
    const target = member({ id: 2, name: 'Bruno Manager', manager_id: 1 })
    const { onSelectMember } = renderTeamPulse([member({ id: 1, name: 'Anna Ceo' }), target])

    const row = await screen.findByText('Bruno Manager')
    fireEvent.click(row.closest('button') as HTMLButtonElement)

    expect(onSelectMember).toHaveBeenCalledWith(target)
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
