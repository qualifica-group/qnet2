import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { ForSelectItem } from '@/features/for-select/types'
import { PurchaseRequestForm } from '@/features/purchase-requests/purchase-request-form'
import { makeLine, makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'
import type { PurchaseRequest, PurchaseRequestPayload } from '@/features/purchase-requests/types'

const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  users: [
    { id: 3, label: 'Mario Rossi' },
    { id: 9, label: 'Luca Neri' },
  ],
  'business-functions': [{ id: 6, label: 'IT', manager: { id: 9, name: 'Luca Neri' } }],
  companies: [{ id: 1, label: 'Qualifica Srl' }],
  'company-sites': [{ id: 2, label: 'Napoli' }],
  'operational-sites': [{ id: 3, label: 'Sede Napoli' }],
}

const forSelectMock = vi.fn(async (resource: string) => ({
  items: (FOR_SELECT_ITEMS[resource] ?? []) as ForSelectItem[],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
}))
vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: (resource: string) => forSelectMock(resource),
}))

const createMock = vi.fn<(payload: PurchaseRequestPayload) => Promise<PurchaseRequest>>()
vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  createPurchaseRequest: (payload: PurchaseRequestPayload) => createMock(payload),
}))

const uploadMock = vi.fn()
vi.mock('@/features/attachments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attachments/api')>()),
  uploadAttachment: (payload: unknown) => uploadMock(payload),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

async function pick(triggerName: RegExp, optionName: RegExp) {
  const trigger = screen.getByRole('combobox', { name: triggerName })
  fireEvent.click(trigger)
  fireEvent.click(await screen.findByRole('option', { name: optionName }))
  // The popup must be gone and the label shown before the next picker is touched.
  await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument())
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  forSelectMock.mockClear()
  uploadMock.mockReset().mockResolvedValue({})
  createMock.mockReset().mockResolvedValue(
    makeRequest({ id: 41, lines: [makeLine({ id: 101, position: 1, description: 'Cables' })] }),
  )
})

describe('PurchaseRequestForm submit (AC-016, D-15)', () => {
  it('creates the RDA with the lines payload and uploads the queued documents afterwards', async () => {
    const onSaved = vi.fn()
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <PurchaseRequestForm
            currentUser={{ id: 3, name: 'Mario Rossi' }}
            onSaved={onSaved}
            onCancel={vi.fn()}
            onDeleted={vi.fn()}
          />
        </ConfirmDialogProvider>
      </QueryClientProvider>,
    )

    fireEvent.change(screen.getByLabelText(/^Subject/), { target: { value: 'Cables' } })
    await pick(/Business function/, /IT/)
    await pick(/^Company$/, /Qualifica/)
    await pick(/Company site/, /Napoli/)
    await pick(/Operational site/, /Sede Napoli/)
    fireEvent.change(screen.getByLabelText('Description 1'), { target: { value: 'Cables' } })
    fireEvent.change(screen.getByLabelText('Unit price 1'), { target: { value: '12.5' } })

    const header = new File(['a'], 'quote.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByLabelText('RDA documents'), { target: { files: [header] } })
    const lineFile = new File(['b'], 'datasheet.pdf', { type: 'application/pdf' })
    fireEvent.click(screen.getByRole('button', { name: 'Line documents 1' }))
    fireEvent.change(await screen.findByLabelText('Line documents 1', { selector: 'input' }), {
      target: { files: [lineFile] },
    })
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    const payload = createMock.mock.calls[0]?.[0]
    expect(payload).toMatchObject({
      subject: 'Cables',
      priority: 'medium',
      requester_id: 3,
      function_manager_id: 9,
      company_id: 1,
      company_site_id: 2,
      operational_site_id: 3,
      business_function_id: 6,
      customer_id: null,
      notes: null,
      lines: [
        {
          product_id: null,
          description: 'Cables',
          reason: null,
          unit_of_measure_id: null,
          quantity: 1,
          unit_price: 12.5,
          vat_rate_id: null,
        },
      ],
    })
    expect(payload?.lines[0]).not.toHaveProperty('id')

    await waitFor(() => expect(uploadMock).toHaveBeenCalledTimes(2))
    expect(uploadMock).toHaveBeenCalledWith({ resource: 'purchase_request', id: 41, collection: 'documents', file: header })
    expect(uploadMock).toHaveBeenCalledWith({ resource: 'purchase_request_line', id: 101, collection: 'documents', file: lineFile })
    await waitFor(() => expect(onSaved).toHaveBeenCalledTimes(1))
  })

  it('blocks the save and marks the required fields when the draft is incomplete', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <PurchaseRequestForm
            currentUser={{ id: 3, name: 'Mario Rossi' }}
            onSaved={vi.fn()}
            onCancel={vi.fn()}
            onDeleted={vi.fn()}
          />
        </ConfirmDialogProvider>
      </QueryClientProvider>,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('The subject is required.')).toBeInTheDocument()
    expect(screen.getByText('The company is required.')).toBeInTheDocument()
    expect(screen.getByText('The description is required.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })
})
