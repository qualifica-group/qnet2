import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RewardTypeForm } from '@/features/reward-types/reward-type-form'
import type { RewardTypeDetailWithPermissions } from '@/features/reward-types/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * The form is create-only, a replica of the detail with its rows closed
 * (spec 0235); the in-place edits live in `reward-type-detail.test.tsx`.
 */

const createRewardTypeMock = vi.fn()

vi.mock('@/features/reward-types/api', () => ({
  createRewardType: (...args: unknown[]) => createRewardTypeMock(...args),
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

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: FULL_ACCESS_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function rewardType(): RewardTypeDetailWithPermissions {
  return {
    id: 9,
    name: 'Buono Amazon',
    color: 'green',
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

/** Picks a token from the `ColorTokenPicker` popover by its localized name. */
function pickColor(name: string) {
  openRow('rewardTypes.form.color')
  fireEvent.click(screen.getByRole('button', { name: /choose a color/i }))
  fireEvent.click(screen.getByRole('option', { name }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRewardTypeMock.mockReset()
})

describe('RewardTypeForm — create (spec 0058, spec 0235)', () => {
  it('AC-016: starts with every row closed; the name row opens on its own control', () => {
    render(<RewardTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    openRow('rewardTypes.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
  })

  it('AC-016: an empty name shows an inline error on the closed row and does NOT call the API', async () => {
    render(<RewardTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')
    expect(createRewardTypeMock).not.toHaveBeenCalled()
  })

  it('AC-016 (D-5): an unselected color shows an inline error and does NOT call the API — color is required', async () => {
    render(<RewardTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('rewardTypes.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Amazon' } })
    save()

    await waitFor(() => expect(screen.getByText('Color is required.')).toBeInTheDocument())
    expect(createRewardTypeMock).not.toHaveBeenCalled()
  })

  it('AC-016: submits { name, color } on a valid create and invokes onSuccess', async () => {
    createRewardTypeMock.mockResolvedValue(rewardType())
    const onSuccess = vi.fn()

    render(<RewardTypeForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('rewardTypes.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Amazon' } })
    pickColor('Green')
    save()

    await waitFor(() => expect(createRewardTypeMock).toHaveBeenCalledTimes(1))
    expect(createRewardTypeMock).toHaveBeenCalledWith({ name: 'Buono Amazon', color: 'green' })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(rewardType()))
  })

  it('AC-016: maps a 422 name error onto the name row', async () => {
    createRewardTypeMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )

    render(<RewardTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('rewardTypes.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Duplicato' } })
    pickColor('Green')
    save()

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
    expect(createRewardTypeMock).toHaveBeenCalledTimes(1)
  })
})
