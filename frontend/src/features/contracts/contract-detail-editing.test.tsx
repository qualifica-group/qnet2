import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import i18n from '@/i18n'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'
import type { QuoteLine } from '@/features/quotes/types'

/**
 * Spec 0360: the contract in force edits its title and revenue rows in place
 * (AC-013), a row assigned to a work order is locked (AC-014) and a refusal
 * stays inside the open editor (AC-015).
 */

const updateContractMock = vi.fn()
vi.mock('@/features/contracts/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/contracts/api')>()),
  updateContract: (...args: unknown[]) => updateContractMock(...args),
}))

const toastError = vi.fn()
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: (...args: unknown[]) => toastError(...args) } }))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/attachments/documents-section', () => ({ DocumentsSection: () => null }))
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => null }))
vi.mock('@/features/contracts/contract-work-orders-section', () => ({
  ContractWorkOrdersSection: forwardRef((_props, ref) => {
    useImperativeHandle(ref, () => ({ refresh: () => undefined }))
    return null
  }),
}))
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: () =>
    Promise.resolve({
      items: [],
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 1, has_more: false },
      export_link: null,
    }),
}))

function line(id: number, name: string, workOrders: QuoteLine['work_orders'] = []): QuoteLine {
  return {
    id,
    product_id: id * 10,
    product: { id: id * 10, code: `P-${id}`, name, category: null, product_typology: null, business_function: null },
    quantity: '2.00',
    unit_of_measure: { id: 1, name: 'Ora', symbol: 'h' },
    additional_description: null,
    unit_price: '100.00',
    vat_rate_id: 7,
    vat_rate: { id: 7, name: 'IVA 22%', rate: '22.00' },
    net_amount: '200.00',
    vat_amount: '44.00',
    total_amount: '244.00',
    sort_order: id,
    supplier_id: null,
    supplier: null,
    work_orders: workOrders,
  }
}

function contract(
  group: 'open' | 'pending' | 'closed_won' | 'closed_lost',
  overrides: Partial<ContractDetailWithPermissions> = {},
): ContractDetailWithPermissions {
  return {
    id: 4,
    quote_id: 10,
    quote: { id: 10, code: 'OFF-1', title: 'Fornitura 2026', created_at: '2026-01-01T00:00:00Z' },
    opportunity: null,
    registry: null,
    referent: null,
    company: null,
    company_site: null,
    operational_site: null,
    commercial: null,
    reporter: null,
    supervisor: null,
    payment_method: null,
    source: null,
    product_lines: [],
    rewards: [],
    managers: [],
    manager_labels: {},
    layout: null,
    contract_status_id: 1,
    contract_status: { id: 1, name: 'Stato', color: 'slate', group },
    is_suspended: false,
    accepted_at: '2026-01-02',
    validated_at: '2026-01-03',
    validated_by: null,
    renewal_date: null,
    expiry_date: '2026-12-31',
    terminated_at: null,
    terminated_by: null,
    termination_reason: null,
    payment_notes: null,
    comments: null,
    renewal_number: 0,
    renewals: [],
    initial_period_work_orders_count: 0,
    offer_lines: [line(1, 'Corso Base'), line(2, 'Corso Avanzato', [{ id: 90, code: 'COM-090' }])],
    created_at: '2026-01-02T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: true, import: false },
      fields: {},
      actions: { validate: false, program: true, terminate: true, reactivate: true, change_status: false, view_activity: false },
    },
    ...overrides,
  } as ContractDetailWithPermissions
}

function renderView(data: ContractDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>
          <ContractDetailView contract={data} />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function unprocessable(errors: Record<string, string[]>): AxiosError {
  const response = { status: 422, data: { success: false, message: 'Invalid', errors } } as AxiosResponse
  return new AxiosError('Unprocessable', 'ERR_BAD_REQUEST', undefined, undefined, response)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateContractMock.mockReset()
  toastError.mockReset()
})

describe('ContractDetailView — editing the contract in force (spec 0360)', () => {
  it.each(['open', 'pending', 'closed_won'] as const)('offers the title and rows pencils on %s (AC-013)', (group) => {
    renderView(contract(group))

    expect(screen.getByRole('button', { name: 'Edit Title' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /^Edit .*offer/i })).toBeInTheDocument()
  })

  it('offers no pencil on a cancelled contract (AC-013)', () => {
    renderView(contract('closed_lost'))

    expect(screen.queryByRole('button', { name: 'Edit Title' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Edit .*offer/i })).not.toBeInTheDocument()
  })

  it('offers no pencil on a field the actor cannot edit (AC-013)', () => {
    const base = contract('closed_won')
    renderView(
      contract('closed_won', {
        permissions: {
          ...base.permissions,
          fields: {
            title: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
            offer_lines: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
          },
        },
      }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Title' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Edit .*offer/i })).not.toBeInTheDocument()
  })

  it('PATCHes the title alone, and a blank title as null (automatic title)', async () => {
    updateContractMock.mockResolvedValue(contract('closed_won'))
    renderView(contract('closed_won'))

    fireEvent.click(screen.getByRole('button', { name: 'Edit Title' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Title' }), { target: { value: 'Nuovo titolo' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateContractMock).toHaveBeenCalledWith(4, { title: 'Nuovo titolo' }))
    await waitFor(() => expect(screen.queryByRole('textbox', { name: 'Title' })).not.toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'Edit Title' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Title' }), { target: { value: '  ' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateContractMock).toHaveBeenLastCalledWith(4, { title: null }))
  })

  it('locks the assigned row in the editor and sends only the rows when one is removed (AC-014)', async () => {
    updateContractMock.mockResolvedValue(contract('closed_won'))
    renderView(contract('closed_won'))

    fireEvent.click(screen.getByRole('button', { name: /^Edit .*offer/i }))

    expect(await screen.findByText('Assigned to COM-090')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove row 2' })).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: 'Remove row 1' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateContractMock).toHaveBeenCalledTimes(1))
    const payload = updateContractMock.mock.calls[0][1] as { offer_lines: Array<{ id: number }>; title?: string }
    expect(Object.keys(payload)).toEqual(['offer_lines'])
    expect(payload.offer_lines.map((row) => row.id)).toEqual([2])
  })

  it('keeps the editor open with the server refusal on the row, changes preserved (AC-015)', async () => {
    updateContractMock.mockRejectedValue(
      unprocessable({ 'offer_lines.0': ['The row is assigned to work order COM-090 and cannot be changed.'] }),
    )
    renderView(contract('closed_won'))

    fireEvent.click(screen.getByRole('button', { name: /^Edit .*offer/i }))
    fireEvent.click(await screen.findByRole('button', { name: 'Remove row 1' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('assigned to work order COM-090')
    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument()
    expect(screen.getAllByText('Corso Avanzato').length).toBeGreaterThan(0)
    expect(screen.queryByText('Corso Base')).not.toBeInTheDocument()
    expect(toastError).toHaveBeenCalled()
  })
})
