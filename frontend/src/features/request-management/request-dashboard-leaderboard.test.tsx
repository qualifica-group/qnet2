import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { Leaderboard } from '@/features/request-management/dashboard-leaderboard'
import { DashboardLeaderboard } from '@/features/request-management/request-dashboard-leaderboard'

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

const LEADERBOARD: Leaderboard = {
  columns: [
    { key: 'telefonate', label: 'Telefonate', max: 4 },
    { key: 'potenziali', label: 'Potenziali', max: 3 },
  ],
  rows: [
    {
      label: 'Ada Rossi',
      values: { telefonate: 4, potenziali: 3 },
      breakdowns: { potenziali: [{ label: 'In trattativa', value: 2 }, { label: 'Validato', value: 1 }] },
      unassigned: false,
    },
  ],
}

describe('DashboardLeaderboard status breakdown (task 427193)', () => {
  it("opens an operator's split by status from their Potenziali cell only", () => {
    render(<DashboardLeaderboard leaderboard={LEADERBOARD} />)

    const trigger = screen.getByRole('button', { name: 'Dettaglio per stato di Ada Rossi - Potenziali' })
    expect(screen.getAllByRole('button', { name: /Dettaglio per stato/ })).toHaveLength(1)

    fireEvent.focus(trigger)

    const list = screen.getByRole('list', { name: 'Dettaglio per stato attuale' })
    expect(within(list).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'In trattativa: 2',
      'Validato: 1',
    ])
  })
})
