import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { RewardCard, type RewardCardLabels } from '@/features/rewards/reward-card'
import type { RewardDetailItem } from '@/features/rewards/types'

/**
 * The status field's own component test coverage (AC-029/030/031) stubs the
 * shared `AsyncPaginatedSelect` — the for-select network/pagination behavior
 * is that component's own responsibility; here only the card's wiring
 * (picking a value forwards it, the trigger is an accessible combobox) is
 * under test, mirroring `review-operator-editor.test.tsx`.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
    disabled,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <button
      type="button"
      role="combobox"
      aria-label={labels.triggerLabel}
      disabled={disabled}
      onClick={() => onChange(99)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

const LABELS: RewardCardLabels = {
  assignedAt: 'Assigned on',
  sourceRemoved: 'Origin no longer available',
  client: 'Client',
  categories: 'Categories',
  commercialStatus: 'Commercial status',
  workflowStatus: 'Workflow status',
  operator: 'Operator',
  status: 'Status',
  statusPlaceholder: 'Select a status',
  statusSearchPlaceholder: 'Search a status',
  statusEmpty: 'No statuses found',
  statusError: 'Unable to load statuses',
  statusClearLabel: 'Remove status',
  statusRetry: 'Retry',
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
  reward_status: { id: 3, name: 'Approved', color: 'green' },
}

const NULL_FIELDS_REWARD: RewardDetailItem = {
  id: 11,
  assigned_at: '2026-07-02',
  notes: null,
  reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  source: null,
  context: null,
  reward_status: null,
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

  it('renders the origin as an open-mode button (not a link) when onOpenSource is given', () => {
    const onOpenSource = vi.fn()
    render(
      <MemoryRouter>
        <RewardCard reward={FULL_REWARD} labels={LABELS} onOpenSource={onOpenSource} />
      </MemoryRouter>,
    )

    expect(screen.queryByRole('link', { name: /Big Deal/ })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /Big Deal/ }))
    expect(onOpenSource).toHaveBeenCalledTimes(1)
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

describe('RewardCard — inline status edit (spec 0060)', () => {
  it('shows the status as a readonly badge, no select, without an edit permission (AC-030)', () => {
    renderCard(FULL_REWARD)

    expect(screen.getByText('Approved')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('shows the status as a readonly badge, no select, when the field is not editable even with canEditStatus unset (AC-030)', () => {
    render(
      <MemoryRouter>
        <RewardCard reward={FULL_REWARD} labels={LABELS} canEditStatus={false} onStatusChange={vi.fn()} />
      </MemoryRouter>,
    )

    expect(screen.getByText('Approved')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('renders an accessible select and forwards the picked status id when editable (AC-029/AC-031)', () => {
    const onStatusChange = vi.fn()
    render(
      <MemoryRouter>
        <RewardCard
          reward={FULL_REWARD}
          labels={LABELS}
          canEditStatus
          onStatusChange={onStatusChange}
        />
      </MemoryRouter>,
    )

    expect(screen.queryByText('Approved')).not.toBeInTheDocument()
    const select = screen.getByRole('combobox', { name: 'Status' })

    fireEvent.click(select)

    expect(onStatusChange).toHaveBeenCalledTimes(1)
    expect(onStatusChange).toHaveBeenCalledWith(99)
  })

  it('disables the select while the status update is in flight', () => {
    render(
      <MemoryRouter>
        <RewardCard
          reward={FULL_REWARD}
          labels={LABELS}
          canEditStatus
          onStatusChange={vi.fn()}
          isStatusUpdating
        />
      </MemoryRouter>,
    )

    expect(screen.getByRole('combobox', { name: 'Status' })).toBeDisabled()
  })

  it('still offers a select for a reward with no status yet (D-5 transitional null)', () => {
    render(
      <MemoryRouter>
        <RewardCard
          reward={NULL_FIELDS_REWARD}
          labels={LABELS}
          canEditStatus
          onStatusChange={vi.fn()}
        />
      </MemoryRouter>,
    )

    expect(screen.getByRole('combobox', { name: 'Status' })).toBeInTheDocument()
  })

  it('renders nothing for the status field when there is no status and the user cannot edit it', () => {
    renderCard(NULL_FIELDS_REWARD)

    expect(screen.queryByText('Status')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
})
