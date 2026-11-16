import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteDetailHeader } from '@/features/quotes/quote-detail-header'
import { QuoteRegistryTransferNotice } from '@/features/quotes/registry-transfer/quote-registry-transfer-notice'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/** Spec 0404, part B (AC-015): the transfer action, its dialog and the detail notice. */

const transferMock = vi.fn()
const toastSuccessMock = vi.fn()
const DESTINATION_ID = 42

vi.mock('@/features/quotes/registry-transfer/api', () => ({
  transferQuoteRegistry: (...args: unknown[]) => transferMock(...args),
}))
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: vi.fn() },
}))
// The real picker pages the registries endpoint; a single button is enough here.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ onChange }: { onChange: (value: number) => void }) => (
    <button type="button" onClick={() => onChange(DESTINATION_ID)}>
      pick destination
    </button>
  ),
}))

function quoteWith(transferRegistry: boolean): QuoteDetailWithPermissions {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Sample quote',
    old_id: null,
    registry: { id: 7, name: 'Acme' },
    applicable_attributes: [],
    attribute_values: {},
    // The status badge of the header, whichever status shape the detail carries.
    quote_workflow_status: { id: 1, name: 'Open', color: null },
    quote_system_status: { id: 1, name: 'Open', color: null, behavior: 'open' },
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { transfer_registry: transferRegistry },
    },
  } as unknown as QuoteDetailWithPermissions
}

let client: QueryClient

function renderHeader(quote: QuoteDetailWithPermissions, onChanged?: () => void) {
  return render(
    <QueryClientProvider client={client}>
      <QuoteDetailHeader quote={quote} onChanged={onChanged} />
    </QueryClientProvider>,
  )
}

function openDialog() {
  fireEvent.click(screen.getByRole('button', { name: 'Transfer to another registry' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
})

describe('transfer to another registry', () => {
  it('shows the action only with transfer_registry', () => {
    const { unmount } = renderHeader(quoteWith(false))
    expect(screen.queryByRole('button', { name: 'Transfer to another registry' })).not.toBeInTheDocument()
    unmount()

    renderHeader(quoteWith(true))
    expect(screen.getByRole('button', { name: 'Transfer to another registry' })).toBeInTheDocument()
  })

  it('requires the destination before sending anything', async () => {
    renderHeader(quoteWith(true))
    openDialog()

    fireEvent.click(screen.getByRole('button', { name: 'Transfer' }))

    expect(await screen.findByText('Choose the destination registry')).toBeInTheDocument()
    expect(transferMock).not.toHaveBeenCalled()
  })

  it('sends the destination on the Offerte route and reports the success', async () => {
    transferMock.mockResolvedValue('Offer transferred to Beta')
    const onChanged = vi.fn()
    renderHeader(quoteWith(true), onChanged)
    openDialog()

    fireEvent.click(screen.getByRole('button', { name: 'pick destination' }))
    fireEvent.click(screen.getByRole('button', { name: 'Transfer' }))

    await waitFor(() => expect(onChanged).toHaveBeenCalled())
    expect(transferMock).toHaveBeenCalledWith('/quotes', 9, { registry_id: DESTINATION_ID, attribute_values: {} })
    expect(toastSuccessMock).toHaveBeenCalledWith('Offer transferred')
  })

  it('shows the server refusal on the registry field', async () => {
    transferMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        statusText: 'Unprocessable',
        headers: {},
        config: { headers: new AxiosHeaders() },
        data: { success: false, message: 'Invalid', errors: { registry_id: ['The offer is under contract.'] } },
      }),
    )
    renderHeader(quoteWith(true))
    openDialog()

    fireEvent.click(screen.getByRole('button', { name: 'pick destination' }))
    fireEvent.click(screen.getByRole('button', { name: 'Transfer' }))

    expect(await screen.findByText('The offer is under contract.')).toBeInTheDocument()
  })
})

describe('QuoteRegistryTransferNotice', () => {
  it('names both registries, the day and who moved it', () => {
    render(
      <QuoteRegistryTransferNotice
        transfer={{
          from_registry_name: 'Acme',
          to_registry_name: 'Beta',
          transferred_at: '2026-11-16T10:00:00+01:00',
          causer_name: 'Mario Rossi',
        }}
      />,
    )

    expect(screen.getByRole('status')).toHaveTextContent('Transferred from Acme to Beta on 16/11/2026 by Mario Rossi')
  })
})
