import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { DashboardKpiCard } from '@/features/request-management/request-dashboard-kpi-card'

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

const POTENTIALS = {
  key: 'potenziali',
  label: 'N. Potenziali associati',
  value: 3,
  breakdown: [
    { label: 'In trattativa', value: 2 },
    { label: 'Validato', value: 1 },
  ],
}

describe('DashboardKpiCard breakdown (task 427193)', () => {
  it('lists the split by status for assistive tech and opens it on focus', async () => {
    render(<DashboardKpiCard item={POTENTIALS} />)

    const list = screen.getByRole('list', { name: 'Dettaglio per stato attuale' })
    expect(within(list).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'In trattativa: 2',
      'Validato: 1',
    ])

    fireEvent.focus(screen.getByRole('button', { name: 'Dettaglio per stato di N. Potenziali associati' }))
    const card = await screen.findByText('Dettaglio per stato attuale', { selector: 'p' })
    expect(within(card.parentElement as HTMLElement).getByText('In trattativa')).toBeInTheDocument()
  })

  it('renders a plain number when the tile has no breakdown', () => {
    render(<DashboardKpiCard item={{ key: 'telefonate', label: 'N. Telefonate', value: 5 }} />)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(screen.queryByRole('list')).not.toBeInTheDocument()
  })
})
