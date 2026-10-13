import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RewardStatusForm } from '@/features/reward-statuses/reward-status-form'
import type { RewardStatusDetailWithPermissions } from '@/features/reward-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * The form is create-only, a replica of the detail with its rows closed
 * (spec 0235); the in-place edits live in `reward-status-detail.test.tsx`.
 */

const createRewardStatusMock = vi.fn()

vi.mock('@/features/reward-statuses/api', () => ({
  createRewardStatus: (...args: unknown[]) => createRewardStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field resolves as visible+editable (the `MetaField` fallback, since `fields` is empty). */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: FULL_ACCESS_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

/** Picks a token from the `ColorTokenPicker` popover by its localized name. */
function pickColor(name: string) {
  openRow('rewardStatuses.form.color')
  fireEvent.click(screen.getByRole('button', { name: /choose a color/i }))
  fireEvent.click(screen.getByRole('option', { name }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function rewardStatus(): RewardStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Granted',
    description: 'Voucher handed over',
    color: 'blue',
    group: 'pending',
    sort_order: 3,
    is_active: true,
    system_key: null,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRewardStatusMock.mockReset()
})

describe('RewardStatusForm — create', () => {
  it('starts with every row closed, with no order input', () => {
    render(<RewardStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    openRow('rewardStatuses.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('shows the errors on the closed rows and does not call the API when name and color are missing', async () => {
    render(<RewardStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')
    expect(screen.getByText('Color is required.')).toBeInTheDocument()
    expect(createRewardStatusMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, without sort_order', async () => {
    createRewardStatusMock.mockResolvedValue(rewardStatus())
    const onSuccess = vi.fn()

    render(<RewardStatusForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('rewardStatuses.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Granted' } })
    pickColor('Green')
    save()

    await waitFor(() => expect(createRewardStatusMock).toHaveBeenCalledTimes(1))
    expect(createRewardStatusMock).toHaveBeenCalledWith({ name: 'Granted', description: null, color: 'green', group: 'pending', is_active: true })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(rewardStatus()))
  })

  it('opens the group row on its select, defaulting to the pending phase', () => {
    render(<RewardStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('rewardStatuses.form.group.label')

    expect(screen.getByRole('combobox', { name: 'Group' })).toHaveTextContent('Pending')
  })
})
