import { useState } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RewardAssignmentField, type RewardAssignmentValue } from '@/features/opportunities/reward-assignment-field'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/**
 * AC-029/030/031 (spec 0059 D-3): the shared "abbinamento buono" control,
 * mounted directly (both the Opportunity form and the Gestione Richiesta
 * attribution section reuse this exact component, so its own test suite is
 * the single source of truth for the interaction).
 */

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const fetchRewardTypeMock = vi.fn()
vi.mock('@/features/reward-types/api', () => ({
  fetchRewardType: (id: number) => fetchRewardTypeMock(id),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const AMAZON: RewardAssignmentRef = {
  id: 900,
  reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' },
  assigned_at: '2026-01-01',
  notes: null,
}

interface HarnessProps {
  initialValue?: RewardAssignmentValue[]
  initialAssignments?: RewardAssignmentRef[]
  reporterId?: number | null
}

function Harness({ initialValue = [], initialAssignments = [], reporterId = 1 }: HarnessProps) {
  const [value, setValue] = useState(initialValue)
  return (
    <RewardAssignmentField
      value={value}
      onChange={setValue}
      initialAssignments={initialAssignments}
      reporterId={reporterId}
      fieldLabel="Assigned rewards"
      disabledHint="Select a reporter first to assign a reward."
      addLabel="Add reward"
      removeLabel={(name) => `Remove ${name}`}
      searchPlaceholder="Search a reward type…"
      emptyLabel="No reward type found."
      errorLabel="Could not load the reward types."
      retryLabel="Retry"
      loadMoreLabel="Load more"
    />
  )
}

function renderField(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

async function openPicker() {
  fireEvent.click(screen.getByRole('button', { name: 'Add reward' }))
  await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  fetchRewardTypeMock.mockReset()
})

describe('RewardAssignmentField — reporter gating (AC-030)', () => {
  it('disables the add control and states the reason when there is no reporter', () => {
    renderField({ reporterId: null })

    const trigger = screen.getByRole('button', { name: 'Add reward' })
    const hint = screen.getByText('Select a reporter first to assign a reward.')
    expect(trigger).toBeDisabled()
    expect(trigger).toHaveAttribute('aria-describedby', hint.id)
  })

  it('enables the add control once a reporter is set, with no hint shown', () => {
    renderField({ reporterId: 5 })

    expect(screen.getByRole('button', { name: 'Add reward' })).toBeEnabled()
    expect(screen.queryByText('Select a reporter first to assign a reward.')).not.toBeInTheDocument()
  })

  it('keeps existing chips visible but read-only when disabled', () => {
    renderField({
      reporterId: null,
      initialValue: [{ reward_type_id: 3 }],
      initialAssignments: [AMAZON],
    })

    expect(screen.getByText('Amazon 10€')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove Amazon 10€' })).not.toBeInTheDocument()
  })
})

describe('RewardAssignmentField — add/remove (AC-029)', () => {
  it('adds a picked reward type as a chip and excludes it from further picks', async () => {
    fetchForSelectMock.mockResolvedValue({
      items: [{ id: 5, label: 'Buono spesa' }],
      pagination: { offset: 0, limit: 25, total: 1 },
      export_link: null,
    })
    fetchRewardTypeMock.mockResolvedValue({
      id: 5,
      name: 'Buono spesa',
      color: 'green',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    })

    renderField({ reporterId: 1 })
    await openPicker()

    fireEvent.click(await screen.findByRole('option', { name: 'Buono spesa' }))

    await waitFor(() => expect(fetchRewardTypeMock).toHaveBeenCalledWith(5))
    expect(await screen.findByText('Buono spesa')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove Buono spesa' })).toBeInTheDocument()
  })

  it('removes a chip when its remove button is clicked', () => {
    renderField({
      reporterId: 1,
      initialValue: [{ reward_type_id: 3 }],
      initialAssignments: [AMAZON],
    })

    fireEvent.click(screen.getByRole('button', { name: 'Remove Amazon 10€' }))

    expect(screen.queryByText('Amazon 10€')).not.toBeInTheDocument()
  })

  it('excludes an already-assigned type from the option list', async () => {
    fetchForSelectMock.mockResolvedValue({
      items: [
        { id: 3, label: 'Amazon 10€' },
        { id: 5, label: 'Buono spesa' },
      ],
      pagination: { offset: 0, limit: 25, total: 2 },
      export_link: null,
    })

    renderField({
      reporterId: 1,
      initialValue: [{ reward_type_id: 3 }],
      initialAssignments: [AMAZON],
    })
    await openPicker()

    expect(await screen.findByRole('option', { name: 'Buono spesa' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Amazon 10€' })).not.toBeInTheDocument()
  })
})
