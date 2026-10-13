import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/** Shared fixtures of the product create/detail suites (spec 0218). */

export const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

export const READ_ONLY: FieldPermission = { ...EDITABLE, editable: false, readonly: true }

/** A permissions block with every listed field at its permission (unlisted = visible and editable). */
export function permissionsFor(
  fields: Record<string, FieldPermission> = {},
  resource: Partial<ResourcePermissions['resource']> = {},
): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true, ...resource },
    fields,
    actions: {},
  }
}

/** The config enums the product rows read (type and Offerta usages). */
export const PRODUCT_ENUMS: Record<string, EnumOption[]> = {
  product_type: [{ value: 'SERVICE', label: 'Service', color: null, icon: null, is_default: true, hidden_on_form: false }],
  product_usage: [
    { value: 'SALE', label: 'Sellable', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'COST', label: 'Usable as cost', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}

export const RAM_ATTRIBUTE: EffectiveAttribute = {
  id: 1,
  code: 'ram_gb',
  name: 'RAM (GB)',
  type: 'integer',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  inherited: false,
  context: 'product',
  options: [],
}

export function productFixture(overrides: Partial<ProductDetailWithPermissions> = {}): ProductDetailWithPermissions {
  return {
    id: 5,
    code: 'PRD-0005',
    name: 'ThinkPad X1',
    description: null,
    cost: '800.00',
    price: '1200.00',
    category_id: 3,
    category: { id: 3, name: 'Laptops' },
    product_type: 'SERVICE',
    usages: ['SALE'],
    created_at: '2026-01-01T00:00:00Z',
    vat_rate_id: null,
    vat_rate: null,
    supplier_id: null,
    supplier: null,
    unit_of_measure_id: 1,
    unit_of_measure: { id: 1, name: 'Unit', symbol: 'pz' },
    product_typology_id: 1,
    product_typology: { id: 1, name: 'Ente', color: 'violet' },
    permissions: permissionsFor({ code: READ_ONLY }),
    ...overrides,
  }
}

/** QueryClient (fresh per test) + router + confirm dialog, as the screens get them. */
export function productWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return {
    client,
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
        </MemoryRouter>
      </QueryClientProvider>
    ),
  }
}

/** The pencil of the in-place row labelled `field`. */
export function pencilOf(field: string): HTMLElement {
  return screen.getByRole('button', { name: `Edit ${field}` })
}

/** The open row's confirm: "Done" on the create draft, "Save" on the detail. */
export function confirmOpenRow(label: 'Done' | 'Save') {
  fireEvent.click(screen.getByRole('button', { name: label }))
}

/** The create form's header Save (the footer repeats it). */
export function clickCreateSave() {
  fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])
}

/** The text/number control labelled `field` (a field hint carries the same label on its own button). */
export function inputOf(field: string): HTMLElement {
  const control = screen
    .getAllByLabelText(new RegExp(`^${field}`))
    .find((element) => element.tagName === 'INPUT' || element.tagName === 'TEXTAREA')
  if (!control) {
    throw new Error(`No input labelled ${field}`)
  }
  return control
}

/** Opens a text/number row, types `value`, keeps it with `confirm`. */
export function typeInRow(field: string, value: string, confirm: 'Done' | 'Save' = 'Done') {
  fireEvent.click(pencilOf(field))
  fireEvent.change(inputOf(field), { target: { value } })
  confirmOpenRow(confirm)
}

/** The create rows a product needs besides its code: name, price (Sellable by default) and category (stub id 3). */
export function completeRequiredCreateRows() {
  typeInRow('Name', 'ThinkPad X1')
  typeInRow('Price', '1200')
  fireEvent.click(pencilOf('Category'))
  fireEvent.click(screen.getByTestId('category-select'))
  confirmOpenRow('Done')
}
