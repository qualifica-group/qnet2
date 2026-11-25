import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { WorkOrderFormBody } from '@/features/work-orders/work-order-form-body'
import { createStandaloneProjectWorkOrder, createWorkOrder } from '@/features/work-orders/api'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0431: the create form of a project work order born without an offer
 * picks its own client, company, sites and business functions instead of the
 * offer, its type is always "project", and it saves through its own endpoint.
 */

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return {
    ...actual,
    createWorkOrder: vi.fn(),
    createStandaloneProjectWorkOrder: vi.fn(),
    fetchWorkOrderFormContext: vi.fn().mockResolvedValue({ applicable_attributes: [], attribute_layout: null, locked_attributes: [] }),
  }
})

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: vi.fn().mockResolvedValue({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
  }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const queryPencil = (field: string) => screen.queryByRole('button', { name: label('common.inlineEdit.edit', { field }) })

function renderStandaloneForm() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <WorkOrderFormBody onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="COM-0002" standalone />
    </ResourcePermissionsProvider>,
    { wrapper },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(createWorkOrder).mockReset()
  vi.mocked(createStandaloneProjectWorkOrder).mockReset()
})

describe('WorkOrderFormBody — project work order without an offer (spec 0431)', () => {
  it('picks client, company, sites and business functions in place of the offer', () => {
    renderStandaloneForm()

    for (const field of [
      'workOrders.form.references.registry',
      'workOrders.detail.company',
      'workOrders.detail.companySite',
      'workOrders.detail.operationalSite',
      'workOrders.form.references.productLines',
      'workOrders.form.references.products',
    ]) {
      expect(queryPencil(label(field))).toBeInTheDocument()
    }
    expect(queryPencil(label('workOrders.form.quoteId'))).not.toBeInTheDocument()
    // The offer lines are absent: the one "Product lines" row is the product-line editor (same English name).
    expect(screen.getAllByRole('button', { name: label('common.inlineEdit.edit', { field: label('workOrders.detail.lines') }) })).toHaveLength(1)
  })

  it('is always a project work order: the type is not editable', () => {
    renderStandaloneForm()

    expect(queryPencil(label('workOrders.form.type'))).not.toBeInTheDocument()
    expect(screen.getAllByText(label('workOrders.options.type.project')).length).toBeGreaterThan(0)
  })

  it('asks for client, company, title and a business function before any request', async () => {
    renderStandaloneForm()

    fireEvent.click(screen.getAllByRole('button', { name: label('workOrders.form.save') })[0])

    expect(await screen.findByText(label('workOrders.form.registryRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('workOrders.form.companyRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('workOrders.form.titleRequired'))).toBeInTheDocument()
    expect(screen.queryByText(label('workOrders.form.quoteRequired'))).not.toBeInTheDocument()
    expect(createStandaloneProjectWorkOrder).not.toHaveBeenCalled()
    expect(createWorkOrder).not.toHaveBeenCalled()
  })

  // D-18: the product line comes first, the function is read from it, the products follow within it.
  it('reads the business function from the product lines and locks the products until a line is chosen', () => {
    renderStandaloneForm()

    expect(screen.getByText(label('workOrders.form.references.businessFunctionDerived'))).toBeInTheDocument()
    expect(queryPencil(label('workOrders.detail.businessFunction'))).not.toBeInTheDocument()

    fireEvent.click(queryPencil(label('workOrders.form.references.products'))!)

    expect(screen.getByText(label('products.ofInterest.hintNoCategories'))).toBeInTheDocument()
  })
})
