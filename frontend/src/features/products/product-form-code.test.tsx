import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetailWithPermissions } from '@/features/products/types'
import type { FieldPermission, ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0065 AC-079/AC-080/AC-081: `code` is a manual, optional field — an
 * enabled input with a fallback-declaring placeholder in create (AC-079),
 * disabled/read-only showing the saved value in edit (AC-080), with a 422
 * duplicate mapped onto the field itself (AC-081). Mirrors
 * `project-form-body.test.tsx`'s equivalent suite 1:1.
 */

const createProductMock = vi.fn()
const updateProductMock = vi.fn()
const fetchProductNextCodeMock = vi.fn<() => Promise<string>>()

vi.mock('@/features/products/api', () => ({
  createProduct: (...args: unknown[]) => createProductMock(...args),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
  fetchProductNextCode: () => fetchProductNextCodeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: () => [
    { value: 'SERVICE', label: 'Service', color: null, icon: null, is_default: true, hidden_on_form: false },
  ],
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: () => {} }),
}))

/** Stubs the category picker as a button calling `onChange(3)`, mirroring `project-form-body.test.tsx`'s select stubs. */
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({
    value,
    onChange,
    disabled,
  }: {
    value: number | null
    onChange: (id: number) => void
    disabled?: boolean
  }) => (
    <button type="button" disabled={disabled} data-testid="category-select" onClick={() => onChange(3)}>
      {value ?? ''}
    </button>
  ),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const FULL_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

/** `code` field-permission fixtures mirroring the spec 0065 contract's create/update ceilings. */
const CODE_EDITABLE_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}
const CODE_READONLY_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
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
    code: 'PRD-0005',
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
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductMock.mockReset()
  updateProductMock.mockReset()
  fetchProductNextCodeMock.mockReset()
  fetchProductNextCodeMock.mockResolvedValue('PRD-0100')
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  canMock.mockReset()
  canMock.mockReturnValue(true)
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue({
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  })
})

/** Fills the create-form fields required alongside the code: name, cost, price, category. */
function completeRequiredCreateFields() {
  fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'ThinkPad X1' } })
  fireEvent.change(screen.getByLabelText('Cost'), { target: { value: '800' } })
  fireEvent.change(screen.getByLabelText('Price'), { target: { value: '1200' } })
  fireEvent.click(screen.getByTestId('category-select'))
}

describe('ProductForm — manual code (spec 0065 AC-079/AC-080)', () => {
  it('auto-fills the enabled, required code field with the next sequential suggestion on create (AC-079)', async () => {
    fetchProductNextCodeMock.mockResolvedValue('PRD-0042')
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Code' })).toBeInTheDocument())
    const code = screen.getByRole('textbox', { name: 'Code' }) as HTMLInputElement
    expect(code).not.toBeDisabled()
    expect(code.value).toBe('PRD-0042')
  })

  it('sends the trimmed manual code on create submit when the user overrides it (AC-079)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createProductMock.mockResolvedValue(product({ code: 'ACME-2026' }))

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Code' })).toBeInTheDocument())
    fireEvent.change(screen.getByRole('textbox', { name: 'Code' }), { target: { value: '  ACME-2026  ' } })
    completeRequiredCreateFields()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    const payload = createProductMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.code).toBe('ACME-2026')
  })

  it('shows the saved code value, disabled/read-only, on edit and never requests next-code (AC-080)', async () => {
    render(
      <ProductForm
        mode={{
          type: 'edit',
          product: product({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const code = await screen.findByRole('textbox', { name: 'Code' })
    expect(code).toBeDisabled()
    expect(code).toHaveAttribute('readonly')
    expect((code as HTMLInputElement).value).toBe('PRD-0005')
    expect(fetchProductNextCodeMock).not.toHaveBeenCalled()
  })

  it('never sends a code field on edit submit (AC-080)', async () => {
    updateProductMock.mockResolvedValue(product({ name: 'ThinkPad X1 Gen 2' }))

    render(
      <ProductForm
        mode={{
          type: 'edit',
          product: product({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText('Name'), { target: { value: 'ThinkPad X1 Gen 2' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledTimes(1))
    const payload = updateProductMock.mock.calls[0][1] as Record<string, unknown>
    expect(payload).not.toHaveProperty('code')
  })
})

describe('ProductForm — 422 duplicate code (spec 0065 AC-081)', () => {
  it('maps a 422 on the code field onto the field itself, not only a toast', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createProductMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: {
            success: false,
            message: 'Validation failed.',
            errors: { code: ['The code has already been taken.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Code' })).toBeInTheDocument())
    fireEvent.change(screen.getByRole('textbox', { name: 'Code' }), { target: { value: 'ACME-2026' } })
    completeRequiredCreateFields()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('The code has already been taken.')).toBeInTheDocument(),
    )
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()

    vi.restoreAllMocks()
  })
})
