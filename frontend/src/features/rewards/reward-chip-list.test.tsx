import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { RewardChipList, type RewardChipListItem } from '@/features/rewards/reward-chip-list'

const ITEMS: RewardChipListItem[] = [
  { id: 1, rewardType: { id: 1, name: 'Amazon voucher', color: 'blue' } },
  {
    id: 2,
    rewardType: { id: 2, name: 'Fuel card', color: 'green' },
    onRemove: vi.fn(),
    removeLabel: 'Remove Fuel card',
  },
]

describe('RewardChipList', () => {
  it('renders one chip per item, with the remove button only where onRemove is given', () => {
    render(<RewardChipList items={ITEMS} />)

    expect(screen.getByText('Amazon voucher')).toBeInTheDocument()
    expect(screen.getByText('Fuel card')).toBeInTheDocument()
    expect(screen.getAllByRole('button')).toHaveLength(1)
    expect(screen.getByRole('button', { name: 'Remove Fuel card' })).toBeInTheDocument()
  })

  it('renders the trailing action slot after the chips', () => {
    render(<RewardChipList items={ITEMS} action={<button type="button">Add reward</button>} />)

    expect(screen.getByRole('button', { name: 'Add reward' })).toBeInTheDocument()
  })

  it('renders nothing when there are no items and no action', () => {
    const { container } = render(<RewardChipList items={[]} />)

    expect(container).toBeEmptyDOMElement()
  })

  it('still renders the action slot alone when there are no items', () => {
    render(<RewardChipList items={[]} action={<button type="button">Add reward</button>} />)

    expect(screen.getByRole('button', { name: 'Add reward' })).toBeInTheDocument()
  })
})
