import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetailWithPermissions } from '@/features/products/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: products is one of the universal custom-fields rollout
 * modules — mounting `<CustomFieldsSection>` is the ONLY products-specific
 * integration. This suite exercises the wiring (schema, defaults, payload,
 * 422) without touching the section's own per-type rendering (covered by
 * `CustomFieldsSection.test.tsx`).
 */

const createProductMock = vi.fn()
const updateProductMock = vi.fn()

vi.mock('@/features/products/api', () => ({
  createProduct: (...args: unknown[]) => createProductMock(...args),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: () => [
    { value: 'SERVICE', label: 'Service', color: null, icon: null, is_default: true, hidden_on_form: false },
  ],
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: () => {} }),
}))

// Isolates the form from the real category picker popover with a lightweight
// controllable stub, so this suite focuses on the form's own logic, not the
// network-backed select (covered by its own component test).
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({ onChange }: { onChange: (id: number) => void }) => (
    <button type="button" onClick={() => onChange(3)}>
      select-category-3
    </button>
  ),
}))

// Isolates the form from the real VAT rate / supplier pickers with a
// lightweight controllable stub (mirrors `registry-form-custom-fields.test.tsx`),
// so this suite focuses on the form's own logic, not the network-backed
// select (covered by its own component test).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ value }: { value: number | null }) => (
    <div data-testid="product-select-value">{value ?? ''}</div>
  ),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// Spec 0062: this suite is not about the attribute layout, so no layout is
// ever configured — keeps the category-picked flow deterministic (no real
// network round-trip) without touching the custom-fields assertions below.
vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithNotes(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.notes': {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: {},
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function product(overrides: Partial<ProductDetailWithPermissions> = {}): ProductDetailWithPermissions {
  return {
    id: 5,
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    category: { id: 3, name: 'Laptops' },
    product_type: 'SERVICE',
    created_at: '2026-01-01T00:00:00Z',
    vat_rate_id: null,
    vat_rate: null,
    supplier_id: null,
    supplier: null,
    state_id: null,
    state: null,
    permissions: permissionsWithNotes(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductMock.mockReset()
  updateProductMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('ProductForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createProductMock.mockResolvedValue(product())
    const onSuccess = vi.fn()

    render(
      <ProductForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'ThinkPad X1' } })
    fireEvent.click(screen.getByText('select-category-3'))
    fireEvent.change(screen.getByLabelText(/^Cost/), { target: { value: '800' } })
    fireEvent.change(screen.getByLabelText(/^Price/), { target: { value: '1200' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key product' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    const payload = createProductMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key product' })
  })

  it('seeds the custom field value from the loaded product detail in edit mode', async () => {
    render(
      <ProductForm
        mode={{ type: 'edit', product: product({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })

  it('sends only the changed custom field on a partial update', async () => {
    const original = product({ custom_fields: { notes: 'Existing note' } })
    updateProductMock.mockResolvedValue(original)

    render(
      <ProductForm mode={{ type: 'edit', product: original }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const notes = await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.change(notes, { target: { value: 'Updated note' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductMock.mock.calls[0]
    expect(payload).toEqual({ custom_fields: { notes: 'Updated note' } })
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    updateProductMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: {
            success: false,
            message: 'Validation failed',
            errors: { 'custom_fields.notes': ['Notes must be shorter.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <ProductForm
        mode={{ type: 'edit', product: product({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Notes must be shorter.')).toBeInTheDocument())
    expect(updateProductMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
