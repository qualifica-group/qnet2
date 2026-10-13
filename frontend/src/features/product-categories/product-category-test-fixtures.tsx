import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import i18n from '@/i18n'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'

/** Shared fixtures of the product category create/detail suites (spec 0229). */

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
export function permissionsFor(fields: Record<string, FieldPermission> = {}, update = true): ResourcePermissions {
  return {
    resource: { view: true, create: true, update, delete: true, export: true, import: true },
    fields,
    actions: {},
  }
}

export function treeNode(overrides: Partial<ProductCategoryTreeNode> = {}): ProductCategoryTreeNode {
  return {
    id: 1,
    name: 'Node',
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
    is_reportable: false,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    ...overrides,
  }
}

/** A root category with every rule at its default; tests override what they exercise. */
export function category(overrides: Partial<ProductCategoryDetailWithPermissions> = {}): ProductCategoryDetailWithPermissions {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: null,
    parent: null,
    inherits_product_attributes: true,
    inherits_quote_attributes: true,
    inherits_work_order_attributes: true,
    description: null,
    attributes: [],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    business_function: null,
    effective_business_function: null,
    requires_quote: false,
    requires_quote_source_category: null,
    is_selectable: true,
    is_active: true,
    is_reportable: false,
    effective_is_reportable: false,
    is_reportable_source_category: null,
    report_columns: null,
    effective_report_columns: [],
    report_columns_source_category: null,
    inherited_report_columns: [],
    inherited_report_columns_source_category: null,
    management_mode: 'multiple',
    management_mode_source_category: null,
    single_quote_per_opportunity: false,
    single_quote_per_opportunity_source_category: null,
    generates_contract: true,
    generates_contract_source_category: null,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    simplified_offer_line_source_category: null,
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    permissions: permissionsFor(),
    ...overrides,
  }
}

/** A category under the root "Electronics" (id 1), which carries the root-owned rules it mirrors. */
export function childCategory(overrides: Partial<ProductCategoryDetailWithPermissions> = {}): ProductCategoryDetailWithPermissions {
  return category({
    parent_id: 1,
    parent: { id: 1, name: 'Electronics' },
    requires_quote_source_category: { id: 1, name: 'Electronics' },
    management_mode_source_category: { id: 1, name: 'Electronics' },
    single_quote_per_opportunity_source_category: { id: 1, name: 'Electronics' },
    generates_contract_source_category: { id: 1, name: 'Electronics' },
    ...overrides,
  })
}

/** One QueryClient per test, so the cache never leaks across cases. */
export function categoryWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return { client, wrapper }
}

/** The row's pencil, by the label the row shows (an i18n key under `productCategories.form`). */
export function pencilOf(key: string) {
  return screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(`productCategories.form.${key}`) }) })
}

export const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))
export const cancelRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))
export const applyRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
