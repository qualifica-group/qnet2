import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { CostTypeForm } from '@/features/cost-types/cost-type-form'
import type { CostTypeDetailWithPermissions } from '@/features/cost-types/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createCostTypeMock = vi.fn()
const updateCostTypeMock = vi.fn()

vi.mock('@/features/cost-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/cost-types/api')>()),
  createCostType: (...args: unknown[]) => createCostTypeMock(...args),
  updateCostType: (...args: unknown[]) => updateCostTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/cost-types/use-cost-type-form-meta', () => ({
  useCostTypeFormMeta: () => ({ status: 'ready', permissions: PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function costType(overrides: Partial<CostTypeDetailWithPermissions> = {}): CostTypeDetailWithPermissions {
  return {
    id: 9,
    name: 'Fitti',
    description: 'Rents',
    sort_order: 2,
    is_active: true,
    created_at: '2026-10-12T08:00:00Z',
    updated_at: '2026-10-12T08:00:00Z',
    abilities: { update: true, delete: true },
    permissions: PERMISSIONS,
    ...overrides,
  }
}

const UNPROCESSABLE_RESPONSE = {
  status: 422,
  data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createCostTypeMock.mockReset()
  updateCostTypeMock.mockReset()
})

describe('CostTypeForm - create (spec 0210)', () => {
  it('renders name, description, order and the active switch', () => {
    render(<CostTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Order/)).toHaveValue(0)
    expect(screen.getByRole('switch', { name: /^Active/ })).toBeChecked()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(<CostTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const nameInput = await screen.findByLabelText(/^Name/)
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const message = screen.getByText('Name is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(nameInput.getAttribute('aria-describedby')).toContain(message.id)
    expect(createCostTypeMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save', async () => {
    createCostTypeMock.mockResolvedValue(costType())
    const onSuccess = vi.fn()

    render(<CostTypeForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Fitti' } })
    fireEvent.change(screen.getByLabelText(/^Order/), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCostTypeMock).toHaveBeenCalledTimes(1))
    expect(createCostTypeMock).toHaveBeenCalledWith({
      name: 'Fitti',
      description: null,
      sort_order: 3,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(costType()))
  })
})

describe('CostTypeForm - edit (spec 0210)', () => {
  it('hydrates every field and submits the whole resource', async () => {
    updateCostTypeMock.mockResolvedValue(costType({ name: 'Fitti 2' }))

    render(
      <CostTypeForm mode={{ type: 'edit', costType: costType() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Fitti')
    expect(screen.getByLabelText(/^Description/)).toHaveValue('Rents')
    expect(screen.getByLabelText(/^Order/)).toHaveValue(2)

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Fitti 2' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateCostTypeMock).toHaveBeenCalledTimes(1))
    expect(updateCostTypeMock).toHaveBeenCalledWith(9, {
      name: 'Fitti 2',
      description: 'Rents',
      sort_order: 2,
      is_active: true,
    })
  })

  it('maps a server 422 onto the matching form field', async () => {
    updateCostTypeMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, UNPROCESSABLE_RESPONSE as never),
    )

    render(
      <CostTypeForm mode={{ type: 'edit', costType: costType() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Utenze' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })
})
