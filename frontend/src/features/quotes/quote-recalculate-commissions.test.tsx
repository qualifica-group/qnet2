import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteDetailHeader } from '@/features/quotes/quote-detail-header'
import { WORKFLOW_STATUS_OPEN } from '@/features/quotes/quote-fixtures'
import { quoteDetailQueryKey } from '@/features/quotes/api'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/** Spec 0308 AC-014: the "Recalculate commissions" button on the quote detail. */

const impactMock = vi.fn()
const recalculateMock = vi.fn()
const toastSuccessMock = vi.fn()

vi.mock('@/features/quotes/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/quotes/api')>()),
  fetchQuoteCommissionRecalculation: (...args: unknown[]) => impactMock(...args),
  recalculateQuoteCommissions: (...args: unknown[]) => recalculateMock(...args),
}))
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: vi.fn() },
}))

function quoteWith(recalculate: boolean): QuoteDetailWithPermissions {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Sample quote',
    old_id: null,
    quote_workflow_status: WORKFLOW_STATUS_OPEN,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { recalculate_commissions: recalculate },
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  impactMock.mockResolvedValue({ lines_count: 4, commissions_count: 9, manual_commissions_count: 2, closed: false })
})

describe('QuoteDetailHeader recalculate commissions', () => {
  it('hides the button without the recalculate_commissions action', () => {
    renderHeader(quoteWith(false))

    expect(screen.queryByRole('button', { name: 'Recalculate commissions' })).not.toBeInTheDocument()
  })

  it('opens a dismissible dialog with the counts and no preselection', async () => {
    renderHeader(quoteWith(true))
    fireEvent.click(screen.getByRole('button', { name: 'Recalculate commissions' }))

    const dialog = await screen.findByRole('dialog')
    await waitFor(() => expect(dialog).toHaveTextContent('4 lines, 9 commissions, 2 of them edited by hand'))
    expect(screen.getAllByRole('radio')).toHaveLength(2)
    screen.getAllByRole('radio').forEach((radio) => expect(radio).not.toBeChecked())
    expect(screen.getByRole('button', { name: 'Recalculate' })).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(recalculateMock).not.toHaveBeenCalled()
  })

  it('sends the chosen mode and puts the returned detail in the cache', async () => {
    const refreshed = { ...quoteWith(true), title: 'Refreshed' }
    recalculateMock.mockResolvedValue(refreshed)
    const onChanged = vi.fn()
    renderHeader(quoteWith(true), onChanged)
    fireEvent.click(screen.getByRole('button', { name: 'Recalculate commissions' }))
    await screen.findByRole('dialog')
    await waitFor(() => expect(screen.getAllByRole('radio')[0]).toBeEnabled())

    fireEvent.click(screen.getByRole('radio', { name: /keep the 2 manual ones/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Recalculate' }))

    await waitFor(() => expect(recalculateMock).toHaveBeenCalledWith(9, 'KEEP_MANUAL'))
    await waitFor(() => expect(client.getQueryData(quoteDetailQueryKey(9))).toEqual(refreshed))
    expect(toastSuccessMock).toHaveBeenCalledWith('Commissions recalculated.')
    expect(onChanged).toHaveBeenCalledTimes(1)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})
