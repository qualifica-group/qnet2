import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { WorkOrderQuoteLinesField } from '@/features/work-orders/work-order-quote-lines-field'

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

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderField(props: Partial<Parameters<typeof WorkOrderQuoteLinesField>[0]> = {}) {
  return render(
    <WorkOrderQuoteLinesField value={[]} onChange={vi.fn()} quoteId={7} {...props} />,
    { wrapper: wrapper() },
  )
}

/** Opens the picker popup, which is what triggers the paginated query. */
async function openPicker() {
  fireEvent.click(screen.getByRole('button', { name: 'Product lines' }))
  await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('WorkOrderQuoteLinesField (AC-071)', () => {
  it('is disabled until an offer is chosen, and says so', () => {
    renderField({ quoteId: null })

    expect(screen.getByRole('button', { name: 'Product lines' })).toBeDisabled()
    expect(
      screen.getByText('Select an offer first to pick its product lines.'),
    ).toBeInTheDocument()
  })

  it('scopes the options to the chosen offer once one is selected', async () => {
    renderField({ quoteId: 7 })

    await openPicker()

    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'quote-offer-lines',
      expect.objectContaining({ params: { quote_id: 7 } }),
    )
    expect(screen.getByRole('button', { name: 'Product lines' })).not.toBeDisabled()
    expect(
      screen.getByText('Only revenue lines of the selected offer are available.'),
    ).toBeInTheDocument()
  })

  it('sends except_work_order_id when editing an existing work order (D-7)', async () => {
    renderField({ quoteId: 7, exceptWorkOrderId: 42 })

    await openPicker()

    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'quote-offer-lines',
      expect.objectContaining({ params: { quote_id: 7, except_work_order_id: 42 } }),
    )
  })
})
