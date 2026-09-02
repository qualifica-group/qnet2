import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { ProductTypologyForm } from '@/features/product-typologies/product-typology-form'
import type { ProductTypologyDetailWithPermissions } from '@/features/product-typologies/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

const createProductTypologyMock = vi.fn()
const updateProductTypologyMock = vi.fn()

vi.mock('@/features/product-typologies/api', () => ({
  createProductTypology: (...args: unknown[]) => createProductTypologyMock(...args),
  updateProductTypology: (...args: unknown[]) => updateProductTypologyMock(...args),
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
    code: READONLY,
    description: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = CREATE_PERMISSIONS

vi.mock('@/features/product-typologies/use-product-typology-form-meta', () => ({
  useProductTypologyFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function productTypology(
  overrides: Partial<ProductTypologyDetailWithPermissions> = {},
): ProductTypologyDetailWithPermissions {
  return {
    id: 9,
    name: 'Kilogram',
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
  createProductTypologyMock.mockReset()
  updateProductTypologyMock.mockReset()
  metaPermissions = CREATE_PERMISSIONS
})

describe('ProductTypologyForm — create (spec 0099)', () => {
  it('renders name, code and description', () => {
    render(
      <ProductTypologyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(
      <ProductTypologyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'kilogram' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const nameInput = await screen.findByLabelText(/^Name/)
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText('Name is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createProductTypologyMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save', async () => {
    createProductTypologyMock.mockResolvedValue(productTypology())
    const onSuccess = vi.fn()

    render(
      <ProductTypologyForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Kilogram' } })
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'kilogram' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProductTypologyMock).toHaveBeenCalledTimes(1))
    expect(createProductTypologyMock).toHaveBeenCalledWith({
      name: 'Kilogram',
        code: 'kilogram',
      description: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(productTypology()))
  })
})

describe('ProductTypologyForm — edit (spec 0099, D-1)', () => {
  it('hydrates every field and renders code disabled', () => {
    metaPermissions = EDIT_PERMISSIONS

    render(
      <ProductTypologyForm
        mode={{ type: 'edit', productTypology: productTypology() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Kilogram')
    expect(screen.getByLabelText(/^Code/)).toHaveValue('kilogram')
    expect(screen.getByLabelText(/^Code/)).toBeDisabled()
    expect(screen.getByLabelText(/^Description/)).toHaveValue('Mass unit')
  })

  it('submits only the changed field on a partial update, never code', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updateProductTypologyMock.mockResolvedValue(productTypology({ name: 'Kilograms' }))

    render(
      <ProductTypologyForm
        mode={{ type: 'edit', productTypology: productTypology() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Kilograms' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductTypologyMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateProductTypologyMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Kilograms' })
    expect(payload).not.toHaveProperty('code')
  })

  it('maps a server 422 onto the matching form field', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updateProductTypologyMock.mockRejectedValue(
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
      <ProductTypologyForm
        mode={{ type: 'edit', productTypology: productTypology() }}
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
