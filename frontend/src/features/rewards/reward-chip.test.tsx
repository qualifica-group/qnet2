import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { RewardChip } from '@/features/rewards/reward-chip'

const REWARD_TYPE = { id: 1, name: 'Amazon voucher', color: 'blue' }

describe('RewardChip', () => {
  it('renders the type name and no remove button when onRemove is omitted', () => {
    render(<RewardChip rewardType={REWARD_TYPE} />)

    expect(screen.getByText('Amazon voucher')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('renders an accessible remove button and calls onRemove on click', () => {
    const onRemove = vi.fn()
    render(<RewardChip rewardType={REWARD_TYPE} onRemove={onRemove} removeLabel="Remove Amazon voucher" />)

    const button = screen.getByRole('button', { name: 'Remove Amazon voucher' })
    button.click()

    expect(onRemove).toHaveBeenCalledTimes(1)
  })

  it('falls back to the neutral badge when the color token is unknown', () => {
    render(<RewardChip rewardType={{ id: 2, name: 'Unknown token', color: 'not-a-token' }} />)

    expect(screen.getByText('Unknown token')).toBeInTheDocument()
  })
})
