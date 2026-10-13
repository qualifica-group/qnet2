import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductTypologyForm } from '@/features/product-typologies/product-typology-form'
import type { ProductTypologyDetailWithPermissions } from '@/features/product-typologies/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Wave 3 of the "no edit pages" plan (spec 0236): the form is create-only, a
 * replica of the detail with its rows closed plus the create-only `code`; the
 * in-place edits live in `product-typology-detail.test.tsx`.
 */

const createProductTypologyMock = vi.fn()

vi.mock('@/features/product-typologies/api', () => ({
  createProductTypology: (...args: unknown[]) => createProductTypologyMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field editable, matching the create-context metadata (`code.editable === true` while `$model === null`). */
const CREATE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: CREATE_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function productTypology(): ProductTypologyDetailWithPermissions {
  return {
    id: 9,
    name: 'Kilogram',
    code: 'kilogram',
    description: null,
    color: 'gray',
    supplier_commission_enabled: false,
    supplier_commission_direction: null,
    proforma_kind: null,
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: CREATE_PERMISSIONS,
  }
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function fillNameAndCode(name: string, code: string) {
  openRow('productTypologies.form.name')
  fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: name } })
  openRow('productTypologies.form.code')
  fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: code } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductTypologyMock.mockReset()
})

describe('ProductTypologyForm — create (spec 0099)', () => {
  it('starts with every row closed; each opens on its own control', () => {
    render(<ProductTypologyForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow('productTypologies.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    openRow('productTypologies.form.code')
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    openRow('productTypologies.form.description')
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
  })

  it('shows the error on the closed row and does not call the API when name is empty', async () => {
    render(<ProductTypologyForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')

    openRow('productTypologies.form.name')
    const nameInput = screen.getByLabelText(/^Name/)
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createProductTypologyMock).not.toHaveBeenCalled()
  })

  it('starts with the gray color and sends the one picked (spec 0204 D-3)', async () => {
    createProductTypologyMock.mockResolvedValue(productTypology())

    render(<ProductTypologyForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fillNameAndCode('Kilogram', 'kilogram')
    openRow('productTypologies.form.color')
    fireEvent.click(screen.getByRole('button', { name: /gray/i }))
    fireEvent.click(await screen.findByRole('option', { name: 'Blue' }))
    save()

    await waitFor(() => expect(createProductTypologyMock).toHaveBeenCalledWith(expect.objectContaining({ color: 'blue' })))
  })

  it('submits the create payload on save', async () => {
    createProductTypologyMock.mockResolvedValue(productTypology())
    const onSuccess = vi.fn()

    render(<ProductTypologyForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fillNameAndCode('Kilogram', 'kilogram')
    save()

    await waitFor(() => expect(createProductTypologyMock).toHaveBeenCalledTimes(1))
    expect(createProductTypologyMock).toHaveBeenCalledWith({
      name: 'Kilogram',
      code: 'kilogram',
      description: null,
      color: 'gray',
      supplier_commission_enabled: false,
      supplier_commission_direction: null,
      proforma_kind: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(productTypology()))
  })
})

describe('ProductTypologyForm — supplier commission (spec 0202)', () => {
  it('hides the direction until the switch is on and requires it then', async () => {
    render(<ProductTypologyForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('productTypologies.form.supplierCommissionEnabled')
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('switch', { name: /Supplier commission calculation/ }))
    expect(await screen.findByRole('combobox', { name: /Direction/ })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))

    fillNameAndCode('Ente', 'ente')
    save()

    // The closed row carries the direction's message: the editor is gone.
    expect(
      await screen.findByText('The direction is required when the Supplier commission is enabled.'),
    ).toBeInTheDocument()
    expect(createProductTypologyMock).not.toHaveBeenCalled()
  })
})
