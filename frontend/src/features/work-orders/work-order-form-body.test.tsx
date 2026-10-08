import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { WorkOrderFormBody } from '@/features/work-orders/work-order-form-body'
import { createWorkOrder } from '@/features/work-orders/api'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * REQUIREMENT CHANGED (user directive 2026-10-06, spec 0195 applied to
 * Commesse): the create form is a replica of the detail — every row closed
 * until its pencil opens the field's control; "Done" keeps the value in the
 * draft, "Revert" restores it; the header's Save validates and creates. The
 * edit-mode cases moved to `work-order-detail-inline-edit.test.tsx`; the
 * forced closure is not a field any more (`work-order-force-close-dialog`).
 */

const fetchWorkOrderFormContextMock = vi.fn()

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>(
    '@/features/work-orders/api',
  )
  return {
    ...actual,
    createWorkOrder: vi.fn(),
    updateWorkOrder: vi.fn(),
    fetchWorkOrderFormContext: (...args: [number[]]) => fetchWorkOrderFormContextMock(...args),
  }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

// `quote_id` resolves to `quotes`, which has no registered quick-create entry,
// but `RelationSelectField` still reads abilities to gate any action slot.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const pencil = (field: string) => screen.getByRole('button', { name: label('common.inlineEdit.edit', { field }) })

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm(initialCode = 'COM-0002') {
  return render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <WorkOrderFormBody onSuccess={vi.fn()} onCancel={vi.fn()} initialCode={initialCode} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(createWorkOrder).mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  fetchWorkOrderFormContextMock.mockReset()
  fetchWorkOrderFormContextMock.mockResolvedValue({ applicable_attributes: [], attribute_layout: null })
})

describe('WorkOrderFormBody — a replica of the detail', () => {
  it('starts with every row closed, the suggested code prefilled', () => {
    renderForm()

    expect(screen.queryByRole('textbox', { name: label('workOrders.form.title') })).not.toBeInTheDocument()
    expect(pencil(label('workOrders.form.title'))).toBeInTheDocument()
    // Header subtitle and the closed "Work order no." row both show the suggestion.
    expect(screen.getAllByText('COM-0002').length).toBeGreaterThan(0)
  })

  it('keeps a value in the draft on "Done" and shows it on the closed row and the header', () => {
    renderForm()

    fireEvent.click(pencil(label('workOrders.form.title')))
    fireEvent.change(screen.getByRole('textbox', { name: label('workOrders.form.title') }), {
      target: { value: 'Impianto nuovo' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.apply') }))

    expect(screen.queryByRole('textbox', { name: label('workOrders.form.title') })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Impianto nuovo' })).toBeInTheDocument()
  })

  it('puts the draft back as the row found it on "Revert"', () => {
    renderForm()

    fireEvent.click(pencil(label('workOrders.form.title')))
    fireEvent.change(screen.getByRole('textbox', { name: label('workOrders.form.title') }), {
      target: { value: 'Da scartare' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.revert') }))

    expect(screen.queryByText('Da scartare')).not.toBeInTheDocument()
  })

  it('validates the whole draft on Save: no POST, the errors under the closed rows', async () => {
    renderForm()

    fireEvent.click(screen.getAllByRole('button', { name: label('workOrders.form.save') })[0])

    expect(await screen.findByText(label('workOrders.form.titleRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('workOrders.form.quoteRequired'))).toBeInTheDocument()
    expect(createWorkOrder).not.toHaveBeenCalled()
  })

  it('offers no forced closure: it is an action of the created record', () => {
    renderForm()

    expect(screen.queryByRole('switch')).not.toBeInTheDocument()
    expect(screen.queryByText(label('workOrders.form.isForceClosed'))).not.toBeInTheDocument()
  })
})

describe('WorkOrderFormBody — offer and lines (AC-071)', () => {
  it('keeps the lines picker disabled until an offer is chosen', async () => {
    renderForm()

    fireEvent.click(pencil(label('workOrders.detail.lines')))

    expect(screen.getByRole('button', { name: 'Product lines' })).toBeDisabled()
    await waitFor(() => expect(fetchForSelectMock).not.toHaveBeenCalledWith('quote-offer-lines', expect.anything()))
  })
})

/** Spec 0199: "New work order" from the anagrafica detail's Commesse tab. */
describe('WorkOrderFormBody — anagrafica scope (spec 0199)', () => {
  it("narrows the Offer picker to the anagrafica's offers", async () => {
    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <WorkOrderFormBody onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="COM-0002" registryId={8} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    fireEvent.click(pencil(label('workOrders.form.quoteId')))
    fireEvent.click(screen.getByRole('combobox', { name: label('workOrders.form.quoteId') }))

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith('quotes', expect.objectContaining({ params: { registry_id: 8 } })),
    )
  })
})

/** Spec 0096: the participant slots, relabelled "Participant n" through `ManagerSlotsField`'s own `labels`. */
describe('WorkOrderFormBody — participants (spec 0096)', () => {
  it('names the people being assigned "participants" everywhere, not "account managers"', () => {
    renderForm()

    fireEvent.click(pencil(label('workOrders.detail.participants')))

    expect(screen.getByText('Participant 1')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add participant' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /account manager/i })).not.toBeInTheDocument()
    expect(screen.getByText(/Participants are ordered/)).toBeInTheDocument()
  })
})

/** Spec 0124 D-9/AC-027: "Modello di Task" is a plain, editable picker on create. */
describe('WorkOrderFormBody — task template (spec 0124)', () => {
  it('opens an enabled, empty picker', () => {
    renderForm()

    fireEvent.click(pencil(label('workOrders.detail.taskTemplate')))

    expect(screen.getByRole('combobox', { name: 'Task template' })).not.toBeDisabled()
    expect(screen.getByText('Select a task template')).toBeInTheDocument()
  })
})
