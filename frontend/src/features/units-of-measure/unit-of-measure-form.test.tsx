import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { UnitOfMeasureForm } from '@/features/units-of-measure/unit-of-measure-form'
import type { UnitOfMeasureDetailWithPermissions } from '@/features/units-of-measure/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

const createUnitOfMeasureMock = vi.fn()
const updateUnitOfMeasureMock = vi.fn()

vi.mock('@/features/units-of-measure/api', () => ({
  createUnitOfMeasure: (...args: unknown[]) => createUnitOfMeasureMock(...args),
  updateUnitOfMeasure: (...args: unknown[]) => updateUnitOfMeasureMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const READONLY: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

/** Every field editable, matching the create-context metadata (`code.editable === true` while `$model === null`). */
const CREATE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

/** `code` readonly, everything else editable, matching an existing record's metadata (D-1). */
const EDIT_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    name: EDITABLE,
    symbol: EDITABLE,
    code: READONLY,
    description: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = CREATE_PERMISSIONS

vi.mock('@/features/units-of-measure/use-unit-of-measure-form-meta', () => ({
  useUnitOfMeasureFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function unitOfMeasure(
  overrides: Partial<UnitOfMeasureDetailWithPermissions> = {},
): UnitOfMeasureDetailWithPermissions {
  return {
    id: 9,
    name: 'Kilogram',
    symbol: 'kg',
    code: 'kilogram',
    description: 'Mass unit',
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: EDIT_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUnitOfMeasureMock.mockReset()
  updateUnitOfMeasureMock.mockReset()
  metaPermissions = CREATE_PERMISSIONS
})

describe('UnitOfMeasureForm — create (spec 0088)', () => {
  it('renders name, symbol, code and description', () => {
    render(
      <UnitOfMeasureForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Symbol/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(
      <UnitOfMeasureForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Symbol/), { target: { value: 'kg' } })
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'kilogram' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const nameInput = await screen.findByLabelText(/^Name/)
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText('Name is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createUnitOfMeasureMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save', async () => {
    createUnitOfMeasureMock.mockResolvedValue(unitOfMeasure())
    const onSuccess = vi.fn()

    render(
      <UnitOfMeasureForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Kilogram' } })
    fireEvent.change(screen.getByLabelText(/^Symbol/), { target: { value: 'kg' } })
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'kilogram' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createUnitOfMeasureMock).toHaveBeenCalledTimes(1))
    expect(createUnitOfMeasureMock).toHaveBeenCalledWith({
      name: 'Kilogram',
      symbol: 'kg',
      code: 'kilogram',
      description: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(unitOfMeasure()))
  })
})

describe('UnitOfMeasureForm — edit (spec 0088, D-1)', () => {
  it('hydrates every field and renders code disabled', () => {
    metaPermissions = EDIT_PERMISSIONS

    render(
      <UnitOfMeasureForm
        mode={{ type: 'edit', unitOfMeasure: unitOfMeasure() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Kilogram')
    expect(screen.getByLabelText(/^Symbol/)).toHaveValue('kg')
    expect(screen.getByLabelText(/^Code/)).toHaveValue('kilogram')
    expect(screen.getByLabelText(/^Code/)).toBeDisabled()
    expect(screen.getByLabelText(/^Description/)).toHaveValue('Mass unit')
  })

  it('submits only the changed field on a partial update, never code', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updateUnitOfMeasureMock.mockResolvedValue(unitOfMeasure({ name: 'Kilograms' }))

    render(
      <UnitOfMeasureForm
        mode={{ type: 'edit', unitOfMeasure: unitOfMeasure() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Kilograms' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateUnitOfMeasureMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateUnitOfMeasureMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Kilograms' })
    expect(payload).not.toHaveProperty('code')
  })

  it('maps a server 422 onto the matching form field', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updateUnitOfMeasureMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { name: ['Name already taken.'] },
        },
      } as never),
    )

    render(
      <UnitOfMeasureForm
        mode={{ type: 'edit', unitOfMeasure: unitOfMeasure() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Duplicate name' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })
})
