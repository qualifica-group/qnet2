import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'

const REWARD = { id: 1, reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  assigned_at: '2026-01-01',
  notes: null,
}

describe('RewardChipsSection', () => {
  it('shows the empty label under the title when nothing is assigned and a label is given', () => {
    render(<RewardChipsSection title="Buoni" rewards={[]} emptyLabel="Nessun buono" />)

    expect(screen.getByRole('heading', { name: 'Buoni' })).toBeInTheDocument()
    expect(screen.getByText('Nessun buono')).toBeInTheDocument()
  })

  it('renders nothing when empty and no label is given (create form)', () => {
    const { container } = render(<RewardChipsSection title="Buoni" rewards={[]} />)

    expect(container).toBeEmptyDOMElement()
  })

  it('renders the chips and not the empty label when rewards are assigned', () => {
    render(<RewardChipsSection title="Buoni" rewards={[REWARD]} emptyLabel="Nessun buono" />)

    expect(screen.getByText('Amazon voucher')).toBeInTheDocument()
    expect(screen.queryByText('Nessun buono')).not.toBeInTheDocument()
  })
})
