import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import i18n from '@/i18n'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/**
 * Spec 0219: the contract's own fields (comments, renewal and expiry dates,
 * payment notes) edit in place, one at a time, each save a PATCH of that
 * field alone — the former "Edit data" dialog sent all four every time.
 */

const updateContractMock = vi.fn()
vi.mock('@/features/contracts/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/contracts/api')>()),
  updateContract: (...args: unknown[]) => updateContractMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
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

function contract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
  return {
    id: 4,
    quote_id: 10,
    quote: { id: 10, code: 'OFF-1', title: 'Fornitura 2026', created_at: '2026-01-01T00:00:00Z' },
    opportunity: { id: 3, name: 'Accordo Acme' },
    registry: { id: 2, name: 'Acme' },
    company: null,
    company_site: null,
    operational_site: null,
    commercial: null,
    reporter: null,
    supervisor: null,
    payment_method: null,
    contract_status_id: 1,
    contract_status: { id: 1, name: 'Aperto', color: 'slate', group: 'open' },
    is_suspended: false,
    accepted_at: '2026-01-02',
    validated_at: null,
    validated_by: null,
    renewal_date: null,
    expiry_date: '2026-12-31',
    terminated_at: null,
    terminated_by: null,
    termination_reason: null,
    payment_notes: 'Bonifico a 30 giorni',
    comments: null,
    offer_lines: [],
    created_at: '2026-01-02T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: true, import: false },
      fields: {},
      actions: { validate: true, program: true, terminate: true, reactivate: true, change_status: true, view_activity: false },
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateContractMock.mockReset()
})

describe('ContractDetailView — in-place editing (spec 0219)', () => {
  it('PATCHes the edited date alone and shows the saved value', async () => {
    updateContractMock.mockResolvedValue(contract({ renewal_date: '2027-01-01' }))
    renderView(contract())

    fireEvent.click(screen.getByRole('button', { name: 'Edit Renewal' }))
    fireEvent.change(screen.getByLabelText(/^Renewal/), { target: { value: '2027-01-01' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateContractMock).toHaveBeenCalledWith(4, { renewal_date: '2027-01-01' }))
    await waitFor(() => expect(screen.queryByLabelText(/^Renewal/)).not.toBeInTheDocument())
  })

  it('writes the comments inside their callout', async () => {
    updateContractMock.mockResolvedValue(contract({ comments: 'Rinnovo da discutere.' }))
    renderView(contract())

    fireEvent.click(screen.getByRole('button', { name: 'Edit Comments' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Comments' }), { target: { value: 'Rinnovo da discutere.' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateContractMock).toHaveBeenCalledWith(4, { comments: 'Rinnovo da discutere.' }))
  })

  // Browser check 2026-10-12: the API sends the dates as full ISO timestamps, which a date input cannot show.
  it('opens a date row on the day part of the ISO timestamp the API sends', () => {
    renderView(contract({ expiry_date: '2026-12-31T00:00:00.000000Z' }))

    fireEvent.click(screen.getByRole('button', { name: 'Edit Expiry' }))

    expect(screen.getByLabelText(/^Expiry/)).toHaveValue('2026-12-31')
  })

  it('closes on Cancel without calling the server', () => {
    renderView(contract())

    fireEvent.click(screen.getByRole('button', { name: 'Edit Payment notes' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Payment notes' }), { target: { value: 'Contanti' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getByText('Bonifico a 30 giorni')).toBeInTheDocument()
    expect(updateContractMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    const base = contract()
    renderView(
      contract({
        permissions: {
          ...base.permissions,
          fields: {
            expiry_date: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
          },
        },
      }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Expiry' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Renewal' })).toBeInTheDocument()
  })
})
