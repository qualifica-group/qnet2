import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RewardStatusForm } from '@/features/reward-statuses/reward-status-form'
import type { RewardStatusDetailWithPermissions } from '@/features/reward-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createRewardStatusMock = vi.fn()
const updateRewardStatusMock = vi.fn()

vi.mock('@/features/reward-statuses/api', () => ({
  createRewardStatus: (...args: unknown[]) => createRewardStatusMock(...args),
  updateRewardStatus: (...args: unknown[]) => updateRewardStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Every field resolves as visible+editable (the `MetaField` fallback, since
 * `fields` is empty) — not about authorization metadata.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/reward-statuses/use-reward-status-form-meta', () => ({
  useRewardStatusFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function rewardStatus(
  overrides: Partial<RewardStatusDetailWithPermissions> = {},
): RewardStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'Approvato',
    description: 'Buono approvato',
    color: 'green',
    group: 'pending',
    sort_order: 10,
    is_active: true,
    system_key: null,
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRewardStatusMock.mockReset()
  updateRewardStatusMock.mockReset()
})

describe('RewardStatusForm — create/edit (spec 0060, AC-024)', () => {
  it('renders name, description, color and is_active fields in create mode, with no order input (D-3)', () => {
    render(
      <RewardStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('renders the group picker, hydrated in edit mode and disabled on a system row (spec 0073, AC-016)', () => {
    const { unmount } = render(
      <RewardStatusForm
        mode={{ type: 'edit', rewardStatus: rewardStatus({ group: 'closed_lost' }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const picker = screen.getByRole('combobox', { name: /^Group/ })
    expect(picker).toHaveTextContent('Closed (negative)')
    expect(picker).not.toBeDisabled()
    unmount()

    render(
      <RewardStatusForm
        mode={{ type: 'edit', rewardStatus: rewardStatus({ system_key: 'pending' }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('combobox', { name: /^Group/ })).toBeDisabled()
  })

  it('shows an inline error and does not call the API when name is empty and color is unchosen', async () => {
    render(
      <RewardStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name is required.')).toBeInTheDocument())
    expect(screen.getByText('Color is required.')).toBeInTheDocument()
    expect(createRewardStatusMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, without sort_order', async () => {
    createRewardStatusMock.mockResolvedValue(rewardStatus())
    const onSuccess = vi.fn()

    render(
      <RewardStatusForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Approvato' } })
    fireEvent.click(screen.getByRole('button', { name: /choose a color/i }))
    fireEvent.click(screen.getByRole('option', { name: 'Green' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRewardStatusMock).toHaveBeenCalledTimes(1))
    expect(createRewardStatusMock).toHaveBeenCalledWith({
      name: 'Approvato',
      description: null,
      color: 'green',
      // spec 0073: the picker defaults to the open phase, submitted as-is.
      group: 'open',
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(rewardStatus()))
  })

  it('hydrates name, description, color and is_active in edit mode', () => {
    render(
      <RewardStatusForm
        mode={{ type: 'edit', rewardStatus: rewardStatus({ is_active: false }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Approvato')
    expect(screen.getByLabelText(/^Description/)).toHaveValue('Buono approvato')
    expect(screen.getByRole('button', { name: /Green/ })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).not.toBeChecked()
  })

  it('submits only the changed name on a partial update', async () => {
    updateRewardStatusMock.mockResolvedValue(rewardStatus({ name: 'Rifiutato' }))

    render(
      <RewardStatusForm
        mode={{ type: 'edit', rewardStatus: rewardStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Rifiutato' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRewardStatusMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateRewardStatusMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Rifiutato' })
  })
})

describe('RewardStatusForm — system row (spec 0060 D-2)', () => {
  it('disables description and is_active while name/color stay editable', () => {
    render(
      <RewardStatusForm
        mode={{
          type: 'edit',
          rewardStatus: rewardStatus({ name: 'In attesa', system_key: 'pending' }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).not.toBeDisabled()
    expect(screen.getByRole('button', { name: /Green/ })).not.toBeDisabled()
    expect(screen.getByLabelText(/^Description/)).toBeDisabled()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeDisabled()
  })
})
