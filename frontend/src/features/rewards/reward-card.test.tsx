import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { RewardCard, type RewardCardLabels } from '@/features/rewards/reward-card'
import type { RewardDetailItem } from '@/features/rewards/types'

const LABELS: RewardCardLabels = {
  assignedAt: 'Assigned on',
  sourceRemoved: 'Origin no longer available',
  client: 'Client',
  categories: 'Categories',
  commercialStatus: 'Commercial status',
  workflowStatus: 'Workflow status',
  operator: 'Operator',
}

const FULL_REWARD: RewardDetailItem = {
  id: 10,
  assigned_at: '2026-07-01',
  notes: 'Handed over at the trade fair.',
  reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  source: { type: 'opportunity', id: 42, name: 'Big Deal', path: '/opportunities/42' },
  context: {
    registry: { id: 5, name: 'Acme Srl' },
    product_categories: [
      { id: 1, name: 'Software' },
      { id: 2, name: 'Hardware' },
    ],
    opportunity_status: { id: 1, name: 'Won', color: 'green', group: 'closed' },
    workflow_status: { id: 2, name: 'Delivered', color: 'teal' },
    operator: { id: 7, name: 'Mario Rossi', avatar_url: null },
  },
}

const NULL_FIELDS_REWARD: RewardDetailItem = {
  id: 11,
  assigned_at: '2026-07-02',
  notes: null,
  reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  source: null,
  context: null,
}

function renderCard(reward: RewardDetailItem) {
  return render(
    <MemoryRouter>
      <RewardCard reward={reward} labels={LABELS} />
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RewardCard', () => {
  it('renders every field when fully populated', () => {
    renderCard(FULL_REWARD)

    expect(screen.getByText('Amazon voucher')).toBeInTheDocument()
    const link = screen.getByRole('link', { name: /Big Deal/ })
    expect(link).toHaveAttribute('href', '/opportunities/42')
    expect(screen.getByText('Acme Srl')).toBeInTheDocument()
    expect(screen.getByText('Software, Hardware')).toBeInTheDocument()
    expect(screen.getByText('Won')).toBeInTheDocument()
    expect(screen.getByText('Delivered')).toBeInTheDocument()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText('Handed over at the trade fair.')).toBeInTheDocument()
    expect(screen.getByText(/Jul 1, 2026/)).toBeInTheDocument()
  })

  it('handles every nullable field without crashing or leaving dangling rows', () => {
    renderCard(NULL_FIELDS_REWARD)

    expect(screen.getByText('Amazon voucher')).toBeInTheDocument()
    expect(screen.getByText('Origin no longer available')).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.queryByText('Client')).not.toBeInTheDocument()
    expect(screen.queryByText('Categories')).not.toBeInTheDocument()
    expect(screen.queryByText('Commercial status')).not.toBeInTheDocument()
    expect(screen.queryByText('Workflow status')).not.toBeInTheDocument()
    expect(screen.queryByText('Operator')).not.toBeInTheDocument()
  })
})
