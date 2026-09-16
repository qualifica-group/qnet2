import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { UserDetailSheetContext } from '@/features/users/user-detail-sheet-context'
import { WorkOrderDetailTeam } from '@/features/work-orders/work-order-detail-team'

/**
 * The commessa's Team block renders people with the shared `RecordPerson`
 * (user directive 2026-09-16, "come in opportunita' e offerte"): every
 * Responsabile and Partecipante opens the user modal on click.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('WorkOrderDetailTeam', () => {
  it('opens the user modal for every supervisor and participant', () => {
    const opened: number[] = []

    render(
      <UserDetailSheetContext.Provider value={{ openUserDetail: (id) => opened.push(id) }}>
        <WorkOrderDetailTeam
          supervisors={[
            { id: 21, name: 'Ada Alberti' },
            { id: 22, name: 'Carlo Conti' },
          ]}
          participants={[{ id: 31, name: 'Bruno Bianchi', position: 1 }]}
        />
      </UserDetailSheetContext.Provider>,
    )

    fireEvent.click(screen.getByRole('button', { name: "View Ada Alberti's profile" }))
    fireEvent.click(screen.getByRole('button', { name: "View Carlo Conti's profile" }))
    fireEvent.click(screen.getByRole('button', { name: "View Bruno Bianchi's profile" }))

    expect(opened).toEqual([21, 22, 31])
  })

  it('labels one row per participant slot in position order', () => {
    render(
      <WorkOrderDetailTeam
        supervisors={[]}
        participants={[
          { id: 32, name: 'Dario Dini', position: 3 },
          { id: 31, name: 'Bruno Bianchi', position: 1 },
        ]}
      />,
    )

    const labels = screen.getAllByText(/^Participant \d$/).map((label) => label.textContent)
    expect(labels).toEqual(['Participant 1', 'Participant 3'])
  })

  it('shows the empty placeholder for both roles when nobody is assigned', () => {
    render(<WorkOrderDetailTeam supervisors={[]} participants={[]} />)

    expect(screen.getByText('Participants')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
