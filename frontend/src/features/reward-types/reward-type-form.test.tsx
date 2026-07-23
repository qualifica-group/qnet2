import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RewardTypeForm } from '@/features/reward-types/reward-type-form'
import type { RewardTypeDetailWithPermissions } from '@/features/reward-types/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createRewardTypeMock = vi.fn()
const updateRewardTypeMock = vi.fn()

vi.mock('@/features/reward-types/api', () => ({
  createRewardType: (...args: unknown[]) => createRewardTypeMock(...args),
  updateRewardType: (...args: unknown[]) => updateRewardTypeMock(...args),
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

vi.mock('@/features/reward-types/use-reward-type-form-meta', () => ({
  useRewardTypeFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function rewardType(
  overrides: Partial<RewardTypeDetailWithPermissions> = {},
): RewardTypeDetailWithPermissions {
  return {
    id: 9,
    name: 'Buono Amazon',
    color: 'blue',
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

/** Picks a token from the `ColorTokenPicker` popover by its localized name. */
function pickColor(name: string) {
  fireEvent.click(screen.getByRole('button', { name: /choose a color/i }))
  fireEvent.click(screen.getByRole('option', { name }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRewardTypeMock.mockReset()
  updateRewardTypeMock.mockReset()
})

describe('RewardTypeForm — create/edit (spec 0058)', () => {
  it('AC-016: renders the name field and the color picker in create mode', () => {
    render(<RewardTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
  })

  it('AC-016: an empty name shows an inline error and does NOT call the API', async () => {
    render(<RewardTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    pickColor('Green')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name is required.')).toBeInTheDocument())
    expect(createRewardTypeMock).not.toHaveBeenCalled()
  })

  it('AC-016 (D-5): an unselected color shows an inline error and does NOT call the API — color is required, unlike the opportunity-statuses template', async () => {
    render(<RewardTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Amazon' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Color is required.')).toBeInTheDocument())
    expect(createRewardTypeMock).not.toHaveBeenCalled()
  })

  it('AC-017 (D-5b): clearing the color with the picker "X" makes the form invalid', async () => {
    render(
      <RewardTypeForm
        mode={{ type: 'edit', rewardType: rewardType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Clear color' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Color is required.')).toBeInTheDocument())
    expect(updateRewardTypeMock).not.toHaveBeenCalled()
  })

  it('AC-016: submits { name, color } on a valid create and invokes onSuccess', async () => {
    createRewardTypeMock.mockResolvedValue(rewardType({ color: 'green' }))
    const onSuccess = vi.fn()

    render(<RewardTypeForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Amazon' } })
    pickColor('Green')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRewardTypeMock).toHaveBeenCalledTimes(1))
    expect(createRewardTypeMock).toHaveBeenCalledWith({ name: 'Buono Amazon', color: 'green' })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(rewardType({ color: 'green' })))
  })

  it('hydrates name and color in edit mode', () => {
    render(
      <RewardTypeForm
        mode={{ type: 'edit', rewardType: rewardType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Buono Amazon')
    expect(screen.getByRole('button', { name: /Blue/ })).toBeInTheDocument()
  })

  it('submits only the changed name on a partial update', async () => {
    updateRewardTypeMock.mockResolvedValue(rewardType({ name: 'Buono Esselunga' }))

    render(
      <RewardTypeForm
        mode={{ type: 'edit', rewardType: rewardType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Esselunga' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRewardTypeMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateRewardTypeMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Buono Esselunga' })
  })
})

describe('RewardTypeForm — server-side validation (spec 0058)', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('AC-016: maps a 422 name error onto the name field', async () => {
    updateRewardTypeMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <RewardTypeForm
        mode={{ type: 'edit', rewardType: rewardType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Duplicato' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
    expect(updateRewardTypeMock).toHaveBeenCalledTimes(1)
  })

  it('AC-016: maps a 422 color error onto the color field', async () => {
    updateRewardTypeMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { color: ['Color is not allowed.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <RewardTypeForm
        mode={{ type: 'edit', rewardType: rewardType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Buono Amazon 2' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Color is not allowed.')).toBeInTheDocument())
    expect(updateRewardTypeMock).toHaveBeenCalledTimes(1)
  })
})
